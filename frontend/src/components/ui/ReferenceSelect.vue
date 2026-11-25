<script setup lang="ts">
import { computed, defineAsyncComponent, ref } from 'vue';
import { IconPlus } from '@tabler/icons-vue';
import ReferenceInput from './ReferenceInput.vue';
import FormModal from './FormModal.vue';
import { useAuthStore } from '../../store/auth';
import { referenceCreationKind } from '../../use/referenceSelect';
import type { ReferenceSelectProps } from '../../types/components/ui/ReferenceSelect';
import type { ReferenceOption } from '../../types/components/ui/ReferenceInput';
import type { FormState } from '../../types/components/FormState';

const props = withDefaults(defineProps<ReferenceSelectProps>(), {
  allowCreate: true,
  required: true,
  validateOnBlur: true,
});
const model = defineModel<number | null>({ default: null });
const emit = defineEmits<{ created: [record: ReferenceOption] }>();
const auth = useAuthStore();
const open = ref(false);
const created = ref<ReferenceOption>();
const form = ref<FormState | null>(null);
const kind = computed(() => referenceCreationKind(props.endpoint));
const canCreate = computed(
  () =>
    props.allowCreate &&
    !!kind.value &&
    (kind.value === 'meters'
      ? auth.canInstallMeters
      : ['unities', 'unity-providers', 'object-provider'].includes(kind.value ?? '')
        ? auth.canManageMeters
        : auth.canManageRentalRegistry),
);
const initial = computed(() => (created.value?.id === model.value ? created.value : props.initial));
const busy = computed(() => form.value?.saving ?? false);
const creationTitle = computed(() =>
  kind.value === 'meters' ? (form.value?.title ?? 'Новый счетчик') : 'Добавить: ' + props.label,
);
const compactCreation = computed(() => kind.value === 'renters' || kind.value === 'contract-types');
const components = {
  meters: defineAsyncComponent(() => import('../MeterCreateForm.vue')),
  'land-lords': defineAsyncComponent(() => import('../LandLordForm.vue')),
  renters: defineAsyncComponent(() => import('../RenterForm.vue')),
  unities: defineAsyncComponent(() => import('../UnityForm.vue')),
  'unity-providers': defineAsyncComponent(() => import('../UnityProviderForm.vue')),
  ownerships: defineAsyncComponent(() => import('../NamedCatalogueForm.vue')),
  'contract-types': defineAsyncComponent(() => import('../NamedCatalogueForm.vue')),
  'object-provider': defineAsyncComponent(() => import('../ObjectUnityProviderForm.vue')),
};
const creationComponent = computed(() => (kind.value ? components[kind.value] : null));
const creationProps = computed(() => {
  if (kind.value === 'meters')
    return {
      modal: true,
      utilityId:
        Number(new URLSearchParams(props.endpoint.split('?')[1]).get('utilityId')) || undefined,
    };

  if (kind.value === 'unities')
    return { modal: true, meterOnly: props.endpoint.includes('meterOnly=1') };

  if (kind.value === 'ownerships' || kind.value === 'contract-types')
    return { modal: true, kind: kind.value };

  if (kind.value === 'object-provider')
    return {
      modal: true,
      objectId: Number(props.endpoint.match(/\/objects\/(\d+)/)?.[1]),
      utilityId: Number(new URLSearchParams(props.endpoint.split('?')[1]).get('unityId')),
    };

  return { modal: true };
});

function close() {
  if (!busy.value) open.value = false;
}

function saved(record: ReferenceOption) {
  created.value = record;
  model.value = record.id;
  open.value = false;
  emit('created', record);
}
</script>

<template>
  <ReferenceInput
    :id="id"
    v-model="model"
    :label="label"
    :endpoint="endpoint"
    :required="required"
    :validate-on-blur="validateOnBlur"
    :placeholder="placeholder"
    :initial="initial"
    :extra-options="extraOptions"
    :disabled="disabled || open"
    :error="error"
  >
    <template
      v-if="canCreate"
      #append
    >
      <button
        type="button"
        class="btn btn-icon btn-primary"
        :disabled="disabled || open"
        :aria-label="'Добавить: ' + label"
        :title="'Добавить: ' + label"
        @click="open = true"
      >
        <IconPlus
          :size="18"
          aria-hidden="true"
        />
      </button>
    </template>
  </ReferenceInput>
  <FormModal
    v-if="open && creationComponent"
    :title="creationTitle"
    :wide="!compactCreation"
    :compact="compactCreation"
    :busy="busy"
    @close="close"
  >
    <component
      :is="creationComponent"
      ref="form"
      v-bind="creationProps"
      @saved="saved"
      @cancel="close"
    />
  </FormModal>
</template>
