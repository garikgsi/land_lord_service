<script setup lang="ts">
import ModalFormActions from './ui/ModalFormActions.vue';
import { computed, ref } from 'vue';
import ReferenceSelect from './ui/ReferenceSelect.vue';
import useApi, { ApiError } from '../use/api';
import type { ObjectUnityProviderFormProps } from '../types/components/ObjectUnityProviderForm';
import type { ObjectUnityProvider } from '../types/models/Unity';

const props = defineProps<ObjectUnityProviderFormProps>();
const emit = defineEmits<{ saved: [record: ObjectUnityProvider]; cancel: [] }>();
const providerId = ref<number | null>(null);
const unityId = ref<number | null>(props.utilityId ?? null);
const unityError = ref('');
const saving = ref(false);
const error = ref('');
const fieldError = ref('');
const { api, errorText } = useApi();
const canSave = computed(() => !!providerId.value && !!unityId.value && !saving.value);
const dirty = computed(() => providerId.value !== null);

async function save() {
  if (!providerId.value || !unityId.value || saving.value) return;

  saving.value = true;
  error.value = '';
  fieldError.value = '';
  unityError.value = '';

  try {
    const record = await api<ObjectUnityProvider>(
      '/api/objects/' + props.objectId + '/unity-providers',
      {
        method: 'POST',
        body: JSON.stringify({ unityProviderId: providerId.value, unityId: unityId.value }),
      },
    );

    saving.value = false;
    emit('saved', record);
  } catch (e) {
    error.value = errorText(e);

    if (e instanceof ApiError) {
      fieldError.value = e.errors.unityProviderId ?? '';
      unityError.value = e.errors.unityId ?? '';
    }
  } finally {
    saving.value = false;
  }
}

defineExpose({ saving, dirty });
</script>

<template>
  <form
    novalidate
    @submit.prevent="save"
  >
    <div class="modal-body">
      <div
        v-if="error"
        class="alert alert-danger"
        role="alert"
      >
        {{ error }}
      </div>
      <ReferenceSelect
        v-if="!utilityId"
        id="object-provider-create-unity"
        v-model="unityId"
        label="Коммуникация"
        endpoint="/api/unities"
        :disabled="saving"
        :error="unityError"
        @update:model-value="unityError = ''"
      />
      <ReferenceSelect
        id="object-provider-create"
        v-model="providerId"
        label="Поставщик"
        endpoint="/api/unity-providers"
        :disabled="saving"
        :error="fieldError"
        @update:model-value="fieldError = ''"
      />
    </div>
    <ModalFormActions
      :saving="saving"
      :can-save="canSave"
      @close="emit('cancel')"
    />
  </form>
</template>
