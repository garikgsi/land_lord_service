<script setup lang="ts">
import useCreateNavigation from '../use/createNavigation';
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute, onBeforeRouteLeave } from 'vue-router';
import UnityForm from '../components/UnityForm.vue';
import UnityProviderForm from '../components/UnityProviderForm.vue';
import useApi from '../use/api';
import { useAuthStore } from '../store/auth';
import type { FormState } from '../types/components/FormState';
import type { Unity, UnityProvider } from '../types/models/Unity';
import type { UtilityRegistryProps } from '../types/forms/Meter';

const props = defineProps<UtilityRegistryProps>();
const { returnAfterCreate, returnAfterSave } = useCreateNavigation();
const route = useRoute();
const auth = useAuthStore();
const { api, errorText } = useApi();
const record = ref<Unity | UnityProvider>();
const form = ref<FormState | null>(null);
const loading = ref(true);
const error = ref('');
const notice = ref('');
const editing = computed(() => !!route.params.id);
const base = computed(() => '/catalogues/' + props.kind);
const isUnity = computed(() => props.kind === 'unities');
const title = computed(() =>
  editing.value
    ? (record.value?.name ?? 'Загрузка…')
    : isUnity.value
      ? 'Добавить коммуникацию'
      : 'Добавить поставщика',
);
const unityRecord = computed(() =>
  record.value && 'useMeter' in record.value ? record.value : undefined,
);
const providerRecord = computed(() =>
  record.value && !('useMeter' in record.value) ? record.value : undefined,
);

async function load() {
  try {
    if (editing.value)
      record.value = await api<Unity | UnityProvider>('/api/' + props.kind + '/' + route.params.id);
  } catch (e) {
    error.value = errorText(e);
  } finally {
    loading.value = false;
  }
}

async function saved(value: Unity | UnityProvider) {
  record.value = value;
  notice.value = 'Запись сохранена.';

  if (!editing.value) await returnAfterCreate(base.value);
  else await returnAfterSave();
}

onBeforeRouteLeave(() => !auth.user || !form.value?.saving);
onMounted(load);
</script>

<template>
  <div class="page-heading">
    <h1>
      {{ title }}
    </h1>
    <RouterLink
      :to="base"
      class="btn"
    >
      К списку
    </RouterLink>
  </div>
  <div
    v-if="error"
    class="alert alert-danger"
    role="alert"
  >
    {{ error }}
  </div>
  <div
    v-if="notice"
    class="alert alert-success"
    role="status"
  >
    {{ notice }}
  </div>
  <p
    v-if="loading"
    role="status"
  >
    Загрузка…
  </p>
  <section
    v-else-if="!editing || record"
    class="card"
  >
    <UnityForm
      v-if="isUnity"
      ref="form"
      :initial="unityRecord"
      @saved="saved"
      @cancel="$router.push(base)"
    />
    <UnityProviderForm
      v-else
      ref="form"
      :initial="providerRecord"
      @saved="saved"
      @cancel="$router.push(base)"
    />
  </section>
</template>
