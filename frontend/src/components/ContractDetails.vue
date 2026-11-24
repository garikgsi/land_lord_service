<script setup lang="ts">
import { computed } from 'vue';
import Badge from './ui/Badge.vue';
import DetailsGrid from './ui/DetailsGrid.vue';
import ContractFiles from './ContractFiles.vue';
import { contractStatusColors, contractStatusLabels } from '../store/contracts';
import { meterReadingDate } from '../use/meterReading';
import type { ContractDetailsProps } from '../types/components/ContractDetails';
import type { DetailField } from '../types/components/ui/DetailsGrid';

const props = defineProps<ContractDetailsProps>();
const status = computed(() => contractStatusLabels[props.contract.status]);
const statusColor = computed(() => contractStatusColors[props.contract.status]);
const signedLabel = computed(() => (props.contract.isSigned ? 'Подписан' : 'Не подписан'));
const signedColor = computed(() => (props.contract.isSigned ? 'green' : 'secondary'));
const fields = computed<DetailField[]>(() => {
  const contract = props.contract;
  const color = contract.landLord.color ?? 'indigo';

  return [
    { label: 'Номер', value: contract.contractNum },
    { label: 'Тип договора', value: contract.contractType.name },
    {
      label: 'Объект',
      value: contract.space.object.name,
      to: '/objects/' + contract.space.object.id,
      color,
    },
    { label: 'Помещение', value: contract.space.name, to: '/spaces/' + contract.space.id, color },
    { label: 'Площадь', value: contract.space.square.replace('.', ',') + ' м²' },
    {
      label: 'Арендодатель',
      value: contract.landLord.name,
      to: '/objects/land-lords/' + contract.landLord.id,
      color,
    },
    { label: 'Арендатор', value: contract.renter.name, to: '/renters/' + contract.renter.id },
    { label: 'Начало аренды', value: meterReadingDate(contract.contractDate) },
    { label: 'Окончание аренды', value: meterReadingDate(contract.expiryDate) },
    {
      label: 'Дата аннулирования',
      value: contract.annulmentDate ? meterReadingDate(contract.annulmentDate) : '—',
    },
    { label: 'Ежемесячный платёж', value: contract.monthlyPayment.replace('.', ',') + ' ₽' },
    { label: 'Обеспечительный платёж', value: contract.securityPayment.replace('.', ',') + ' ₽' },
    {
      label: 'Дата индексации',
      value: contract.increaseDate ? meterReadingDate(contract.increaseDate) : '—',
    },
    {
      label: 'Процент индексации',
      value:
        contract.increasePercent === null ? '—' : contract.increasePercent.replace('.', ',') + ' %',
    },
    {
      label: 'Последняя индексация',
      value: contract.lastIndexationDate ? meterReadingDate(contract.lastIndexationDate) : '—',
    },
    {
      label: 'Период напоминания об индексации',
      value: contract.increaseIntervalMonths
        ? String(contract.increaseIntervalMonths) + ' мес.'
        : '—',
    },
    {
      label: 'Следующее напоминание',
      value: contract.nextIndexationReminderDate
        ? meterReadingDate(contract.nextIndexationReminderDate)
        : '—',
    },
  ];
});
</script>

<template>
  <div class="d-flex flex-wrap gap-2 mb-3">
    <Badge :color="statusColor">
      {{ status }}
    </Badge>
    <Badge :color="signedColor">
      {{ signedLabel }}
    </Badge>
  </div>
  <DetailsGrid :fields="fields" />
  <div class="mt-4">
    <ContractFiles
      :contract-id="contract.id"
      readonly
    />
  </div>
</template>
