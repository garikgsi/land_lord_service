<script setup lang="ts">
import { computed, ref } from 'vue';
import Badge from './ui/Badge.vue';
import DetailsGrid from './ui/DetailsGrid.vue';
import MeterDetailsModal from './MeterDetailsModal.vue';
import {
  unityProviderContractStatusLabels,
  unityProviderContractStatusColors,
} from '../store/unityProviderContracts';
import { meterReadingDate } from '../use/meterReading';
import type { UnityProviderContractDetailsProps } from '../types/components/ContractDetails';
import type { DetailField } from '../types/components/ui/DetailsGrid';

const props = defineProps<UnityProviderContractDetailsProps>();
const meterId = ref<number | null>(null);
const status = computed(() => unityProviderContractStatusLabels[props.contract.status]);
const statusColor = computed(() => unityProviderContractStatusColors[props.contract.status]);
const fields = computed<DetailField[]>(() => [
  {
    label: 'Объект',
    value: props.contract.object.name,
    to: '/objects/' + props.contract.object.id,
  },
  {
    label: 'Поставщик',
    value: props.contract.unityProvider.name,
    to: '/catalogues/unity-providers/' + props.contract.unityProvider.id,
  },
  { label: 'Номер', value: props.contract.contractNum ?? '—' },
  {
    label: 'Дата договора',
    value: props.contract.contractDate ? meterReadingDate(props.contract.contractDate) : '—',
  },
  { label: 'Дата окончания', value: meterReadingDate(props.contract.expiryDate) },
  {
    label: 'Коммуникации',
    value: props.contract.unities.map((item) => item.name).join(', ') || '—',
  },
]);
</script>

<template>
  <Badge
    :color="statusColor"
    class="mb-3"
  >
    {{ status }}
  </Badge>
  <DetailsGrid :fields="fields" />
  <h3 class="card-title mt-4">
    Счетчики
  </h3>
  <div class="d-flex flex-wrap gap-2">
    <Badge
      v-for="meter in contract.meters"
      :key="meter.id"
      action
      wrap
      @click="meterId = meter.id"
    >
      {{ meter.name }}
    </Badge>
    <p
      v-if="!contract.meters.length"
      class="text-secondary mb-0"
    >
      Нет счетчиков
    </p>
  </div>
  <MeterDetailsModal
    v-if="meterId !== null"
    :meter-id="meterId"
    @close="meterId = null"
  />
</template>
