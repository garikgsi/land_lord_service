<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute, onBeforeRouteLeave } from 'vue-router';
import Badge from '../components/ui/Badge.vue';
import ReferenceSelect from '../components/ui/ReferenceSelect.vue';
import FormBackButton from '../components/ui/FormBackButton.vue';
import MeterSelect from '../components/MeterSelect.vue';
import useApi, { ApiError } from '../use/api';
import useCreateNavigation, { creationReturnPath } from '../use/createNavigation';
import { useAuthStore } from '../store/auth';
import type { NamedRecord } from '../types/models/NamedRecord';
import type { Space } from '../types/models/Space';

const route = useRoute();
const auth = useAuthStore();
const { api, errorText } = useApi();
const { returnAfterCreate } = useCreateNavigation();
const object = ref<NamedRecord>();
const space = ref<Space>();
const utilityId = ref<number | null>(null);
const meterId = ref<number | null>(null);
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const errors = ref<Record<string, string>>({});
const backRoute = computed(() => creationReturnPath(route.query.returnTo, '/meters'));
const disabled = computed(() => loading.value || saving.value || !auth.canInstallMeters);
const canSave = computed(
  () =>
    !disabled.value &&
    !!utilityId.value &&
    !!meterId.value &&
    !Object.values(errors.value).some(Boolean),
);
const saveLabel = computed(() => (saving.value ? 'Сохраняем…' : 'Добавить'));
const controller = new AbortController();

function utilityChanged() {
  meterId.value = null;
  errors.value = {};
}

async function save() {
  if (!canSave.value || !meterId.value) return;

  saving.value = true;
  error.value = '';

  try {
    await api('/api/meters/' + meterId.value + '/installation', {
      method: 'PUT',
      body: JSON.stringify({
        objectId: space.value ? null : object.value?.id,
        spaceId: space.value?.id ?? null,
        utilityId: utilityId.value,
        requireUninstalled: true,
      }),
    });
    saving.value = false;
    await returnAfterCreate(backRoute.value);
  } catch (e) {
    error.value = errorText(e);

    if (e instanceof ApiError) errors.value = e.errors;
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  try {
    if (route.query.spaceId) {
      space.value = await api<Space>('/api/spaces/' + route.query.spaceId, {
        signal: controller.signal,
      });
      object.value = space.value.object;
    } else
      object.value = await api<NamedRecord>('/api/objects/' + route.query.objectId, {
        signal: controller.signal,
      });
  } catch (e) {
    if (!controller.signal.aborted) error.value = errorText(e);
  } finally {
    if (!controller.signal.aborted) loading.value = false;
  }
});
onBeforeUnmount(() => controller.abort());
onBeforeRouteLeave(() => !auth.user || !saving.value);
</script>

<template>
  <div class="page-heading">
    <h1>
      Добавить счетчик
    </h1>
  </div>
  <div
    v-if="error"
    class="alert alert-danger"
    role="alert"
  >
    {{ error }}
  </div>
  <section class="card">
    <form
      class="card-body"
      novalidate
      @submit.prevent="save"
      @change="errors = {}"
    >
      <div class="d-flex flex-wrap gap-2 mb-3">
        <Badge
          v-if="object"
          wrap
        >
          Объект: {{ object.name }}
        </Badge>
        <Badge
          v-if="space"
          wrap
        >
          Помещение: {{ space.name }}
        </Badge>
      </div>
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <ReferenceSelect
            id="assign-utility"
            v-model="utilityId"
            label="Коммуникация"
            endpoint="/api/unities?meterOnly=1"
            :disabled="disabled"
            :error="errors.utilityId"
            @update:model-value="utilityChanged"
          />
        </div>
        <div class="col-12 col-md-6">
          <MeterSelect
            id="assign-meter"
            v-model="meterId"
            :utility-id="utilityId"
            :disabled="disabled"
            :error="errors.meterId"
            @update:model-value="errors = {}"
          />
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-3">
        <button
          type="submit"
          class="btn btn-primary"
          :disabled="!canSave"
        >
          {{ saveLabel }}
        </button>
        <FormBackButton :to="backRoute" />
      </div>
    </form>
  </section>
</template>
