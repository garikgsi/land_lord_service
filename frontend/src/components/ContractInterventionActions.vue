<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
import ActionMenu from './ui/ActionMenu.vue';
import ContractActions from './ContractActions.vue';
import { contractActionItems } from '../store/contracts';
import useApi from '../use/api';
import type { Contract } from '../types/models/Contract';
import type { ContractAction } from '../types/components/ContractActions';
import type { ContractInterventionActionsProps } from '../types/components/ContractInterventionActions';

const props = defineProps<ContractInterventionActionsProps>();
const emit = defineEmits<{ updated: [contract: Contract] }>();
const { api, errorText } = useApi();
const record = ref<Contract>();
const actions = ref<InstanceType<typeof ContractActions> | null>(null);
const loading = ref(false);
const error = ref('');
const busy = computed(() => loading.value || !!actions.value?.busy);
let controller: AbortController | undefined;

async function open(action: ContractAction) {
  if (busy.value) return;

  controller?.abort();
  const current = new AbortController();

  controller = current;
  loading.value = true;
  error.value = '';

  try {
    const result = await api<Contract>('/api/contracts/' + props.contractId, {
      signal: current.signal,
    });

    if (current.signal.aborted) return;

    record.value = result;
    await nextTick();
    actions.value?.open(action);
  } catch (e) {
    if (!current.signal.aborted) error.value = errorText(e);
  } finally {
    if (!current.signal.aborted) loading.value = false;
  }
}

onBeforeRouteLeave(() => !busy.value);
onBeforeUnmount(() => controller?.abort());
</script>

<template>
  <ActionMenu
    label="Действия"
    :items="contractActionItems"
    :disabled="busy"
    floating
    @select="open"
  />
  <div
    v-if="error"
    class="text-danger mt-2"
    role="alert"
  >
    {{ error }}
  </div>
  <ContractActions
    v-if="record"
    ref="actions"
    :contract="record"
    hide-menu
    @updated="emit('updated', $event)"
  />
</template>
