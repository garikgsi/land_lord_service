<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import FormModal from './ui/FormModal.vue';
import ContractDetails from './ContractDetails.vue';
import UnityProviderContractDetails from './UnityProviderContractDetails.vue';
import useApi from '../use/api';
import type { Contract } from '../types/models/Contract';
import type { UnityProviderContract } from '../types/models/UnityProviderContract';
import type { ContractDetailsModalProps } from '../types/components/ContractDetails';

const props = defineProps<ContractDetailsModalProps>();
const emit = defineEmits<{ close: [] }>();
const route = useRoute();
const { api, errorText } = useApi();
const record = ref<Contract | UnityProviderContract>();
const loading = ref(true);
const error = ref('');
const rental = computed(() => (record.value && 'space' in record.value ? record.value : undefined));
const utility = computed(() =>
  record.value && !('space' in record.value) ? record.value : undefined,
);
const basePath = computed(() =>
  props.kind === 'rental' ? '/contracts/' : '/unity-provider-contracts/',
);
const title = computed(
  () =>
    (props.kind === 'rental' ? 'Договор аренды' : 'Договор коммунального поставщика') +
    (record.value?.contractNum ? ' № ' + record.value.contractNum : ''),
);
const cardRoute = computed(() => ({
  path: basePath.value + props.contractId,
  query: { returnTo: route.fullPath },
}));
let controller: AbortController | undefined;

async function load() {
  controller?.abort();
  const current = new AbortController();

  controller = current;
  loading.value = true;
  error.value = '';

  try {
    const result = await api<Contract | UnityProviderContract>(
      '/api' + basePath.value + props.contractId,
      { signal: current.signal },
    );

    if (!current.signal.aborted) record.value = result;
  } catch (e) {
    if (!current.signal.aborted) error.value = errorText(e);
  } finally {
    if (!current.signal.aborted) loading.value = false;
  }
}

onMounted(load);
onBeforeUnmount(() => controller?.abort());
</script>

<template>
  <FormModal
    :title="title"
    wide
    @close="emit('close')"
  >
    <div class="modal-body">
      <p
        v-if="loading"
        role="status"
      >
        Загрузка…
      </p>
      <div
        v-if="error"
        class="alert alert-danger"
        role="alert"
      >
        {{ error }}
        <button
          type="button"
          class="btn ms-2"
          @click="load"
        >
          Повторить
        </button>
      </div>
      <ContractDetails
        v-if="rental"
        :contract="rental"
      />
      <UnityProviderContractDetails
        v-else-if="utility"
        :contract="utility"
      />
    </div>
    <div class="modal-footer">
      <RouterLink
        v-if="record"
        :to="cardRoute"
        class="btn btn-primary"
      >
        К договору
      </RouterLink>
      <button
        type="button"
        class="btn"
        @click="emit('close')"
      >
        Закрыть
      </button>
    </div>
  </FormModal>
</template>
