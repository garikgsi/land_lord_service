<script setup lang="ts">
import useCreateNavigation from '../use/createNavigation';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink, onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import NamedCatalogueForm from '../components/NamedCatalogueForm.vue';
import useApi from '../use/api';
import { useAuthStore } from '../store/auth';
import type { NamedRecord } from '../types/models/NamedRecord';
import type { NamedRegistryProps } from '../types/components/NamedRegistry';
import type { FormState } from '../types/components/FormState';

const props = defineProps<NamedRegistryProps>();
const { returnAfterCreate, returnAfterSave } = useCreateNavigation();
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const { api, errorText } = useApi();
const record = ref<NamedRecord>();
const form = ref<FormState | null>(null);
const loading = ref(true);
const loadError = ref('');
const notice = ref('');
const creating = computed(() => !route.params.id);
const basePath = computed(() => '/catalogues/' + props.kind);
const title = computed(() =>
  creating.value ? 'Добавить запись' : (record.value?.name ?? 'Загрузка…'),
);
let controller: AbortController | undefined;

async function load() {
  controller?.abort();
  const current = new AbortController();

  controller = current;
  loading.value = true;
  loadError.value = '';

  try {
    if (!creating.value)
      record.value = await api<NamedRecord>('/api/' + props.kind + '/' + route.params.id, {
        signal: current.signal,
      });
  } catch (e) {
    if (!current.signal.aborted) loadError.value = errorText(e);
  } finally {
    if (!current.signal.aborted) loading.value = false;
  }
}

async function saved(value: NamedRecord) {
  record.value = value;
  notice.value = 'Изменения сохранены.';

  if (creating.value) await returnAfterCreate(basePath.value);
  else await returnAfterSave();
}

function cancel() {
  void router.push(basePath.value);
}

onMounted(load);
onBeforeUnmount(() => controller?.abort());
onBeforeRouteLeave(() => !auth.user || !form.value?.saving);
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>
        {{ title }}
      </h1>
    </div>
    <RouterLink
      :to="basePath"
      class="btn"
    >
      К списку
    </RouterLink>
  </div>
  <p
    v-if="loading"
    role="status"
  >
    Загрузка…
  </p>
  <div
    v-else-if="loadError"
    class="alert alert-danger"
    role="alert"
  >
    {{ loadError }}
    <button
      class="btn ms-2"
      @click="load"
    >
      Повторить
    </button>
  </div>
  <template v-else>
    <div
      v-if="notice"
      class="alert alert-success"
      role="status"
    >
      {{ notice }}
    </div>
    <section class="card">
      <NamedCatalogueForm
        ref="form"
        :kind="kind"
        :initial="record"
        @saved="saved"
        @cancel="cancel"
      />
    </section>
  </template>
</template>
