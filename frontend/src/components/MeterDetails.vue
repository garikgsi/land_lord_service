<script setup lang="ts">
import Badge from './ui/Badge.vue';
import MeterConsumption from './MeterConsumption.vue';
import { computed } from 'vue';
import DetailsGrid from './ui/DetailsGrid.vue';
import type { MeterDetailsProps, MeterDetailGroup } from '../types/components/MeterDetails';

const props = defineProps<MeterDetailsProps>();
const installationLabel = computed(() =>
  props.meter.installation ? 'Установлен' : 'Не установлен',
);
const verificationLabel = computed(() =>
  props.meter.verificationExpired ? 'Требуется поверка' : 'Поверка действительна',
);
const verificationColor = computed(() => (props.meter.verificationExpired ? 'orange' : 'green'));
const readingLabel = computed(() => {
  if (!props.meter.installation) return 'Передача показаний не требуется';

  if (props.meter.readingSubmittedLate) return 'Показания переданы с просрочкой';

  if (props.meter.readingSubmitted) return 'Показания переданы';

  if (props.meter.readingOverdue) return 'Показания просрочены';

  return props.meter.readingAttention ? 'Требуется передать показания' : 'Ожидаются показания';
});
const readingColor = computed(() =>
  props.meter.readingSubmittedLate || props.meter.readingOverdue || props.meter.readingAttention
    ? 'orange'
    : props.meter.readingSubmitted
      ? 'green'
      : 'secondary',
);
const verificationDate = computed(() => date(props.meter.nextVerificationDate));
const groups = computed<MeterDetailGroup[]>(() => {
  const meter = props.meter;
  const place = meter.installation;

  return [
    {
      id: 'details',
      title: 'Сведения о счетчике',
      fields: [
        { label: 'Коммуникация', value: meter.utility.name },
        { label: 'Модель', value: meter.model ?? 'Не указана' },
        { label: 'Заводской номер', value: meter.serialNumber ?? 'Не указан' },
        { label: 'Единица измерения', value: meter.utility.unit ?? 'Не указана' },
        { label: 'Дата производства', value: date(meter.createDate) },
        { label: 'Начальные показания', value: reading(meter.startRead) },
        { label: 'Комментарий', value: meter.comment ?? '—' },
      ],
    },
    {
      id: 'location',
      title: 'Место установки',
      fields: [
        {
          label: 'Установка',
          value: place ? (place.type === 'space' ? 'В помещении' : 'На объекте') : 'Не установлен',
        },
        {
          label: 'Объект',
          value: place?.object.name ?? '—',
          to: place ? '/objects/' + place.object.id : undefined,
        },
        {
          label: 'Помещение',
          value: place?.type === 'space' ? place.name : '—',
          to: place?.type === 'space' ? '/spaces/' + place.id : undefined,
        },
        { label: 'Поставщик', value: place?.provider?.unityProvider.name ?? 'Не выбран' },
        { label: 'Комментарий к установке', value: place?.comment ?? '—' },
      ],
    },
    {
      id: 'readings',
      title: 'Показания и график передачи',
      fields: [
        {
          label: 'Последние показания',
          value: meter.lastReading ? reading(meter.lastReading.meterRead) : 'Ещё не передавались',
        },
        {
          label: 'Дата снятия последних показаний',
          value: date(meter.lastReading?.meterReadingDate),
        },
        {
          label: 'График',
          value:
            meter.readingDay === null
              ? 'Общий график объекта'
              : 'Собственный график: ' + meter.readingDay + ' числа',
        },
        {
          label: 'День передачи показаний',
          value:
            meter.effectiveReadingDay === null ? 'Не требуется' : String(meter.effectiveReadingDay),
        },
        { label: 'Срок в текущем месяце', value: date(meter.readingDueDate) },
        { label: 'Следующий срок передачи', value: date(meter.nextReadingDueDate) },
      ],
    },
    {
      id: 'verification',
      title: 'Поверки',
      fields: [
        {
          label: 'Последняя поверка',
          value: meter.verificationDate ? date(meter.verificationDate) : 'Не проводилась',
        },
        { label: 'Межповерочный интервал', value: meter.verificationIntervalDays + ' дней' },
        { label: 'Следующая поверка', value: date(meter.nextVerificationDate) },
      ],
    },
  ];
});

function date(value: string | null | undefined) {
  return value ? value.slice(0, 10).split('-').reverse().join('.') : '—';
}

function reading(value: number) {
  return (
    value.toLocaleString('ru-RU') + (props.meter.utility.unit ? ' ' + props.meter.utility.unit : '')
  );
}
</script>

<template>
  <div class="meter-details-layout">
    <MeterConsumption
      :key="meter.id"
      :meter="meter"
      class="meter-details-widget"
    />
    <div class="meter-details-content">
      <div class="d-flex flex-wrap gap-2 mb-4">
        <Badge color="green">
          {{ installationLabel }}
        </Badge>
        <Badge :color="readingColor">
          {{ readingLabel }}
        </Badge>
        <Badge :color="verificationColor">
          {{ verificationLabel }}
        </Badge>
      </div>
      <div
        v-if="meter.verificationExpired"
        class="alert alert-warning"
        role="alert"
      >
        Требуется поверка: срок истёк {{ verificationDate }}. Прибор можно использовать с
        предупреждением до проведения поверки.
      </div>
      <section
        v-for="(group, index) in groups"
        :key="group.id"
        :class="{ 'mt-4': index > 0 }"
      >
        <h2 class="card-title mb-3">
          {{ group.title }}
        </h2>
        <DetailsGrid :fields="group.fields" />
      </section>
    </div>
  </div>
</template>

<style scoped>
.meter-details-layout {
  display: flow-root;
}

.meter-details-layout > .meter-details-widget {
  margin-bottom: 1.5rem;
}

.meter-details-content {
  min-width: 0;
}

@media (min-width: 992px) {
  .meter-details-layout > .meter-details-widget {
    float: right;
    width: 18rem;
    margin: 0 0 1.5rem 1.5rem;
  }
}
</style>
