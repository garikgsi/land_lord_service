<script setup lang="ts">
import { computed } from 'vue';
import DonutChart from './ui/DonutChart.vue';
import type { DonutSeries } from '../types/components/ui/DonutChart';
import type { RentalObjectAreaProps } from '../types/components/RentalObjectArea';

const props = defineProps<RentalObjectAreaProps>();
const total = computed(() => props.area.square.replace('.', ',') + ' м²');
const series = computed<DonutSeries[]>(() => [
  {
    label: 'Занято',
    value: Number(props.area.occupiedSquare),
    valueLabel: props.area.occupiedSquare.replace('.', ',') + ' м²',
    color: 'green',
  },
  {
    label: 'Свободно',
    value: Number(props.area.availableSquare),
    valueLabel: props.area.availableSquare.replace('.', ',') + ' м²',
    color: 'orange',
  },
]);
</script>

<template>
  <section
    class="rental-object-area"
    aria-label="Площадь объекта"
  >
    <h2 class="card-title">
      Площадь объекта
    </h2>
    <DonutChart
      :series="series"
      :total="total"
      total-label="Всего"
    />
  </section>
</template>

<style scoped>
.rental-object-area {
  width: 100%;
  max-width: 22rem;
  min-width: 0;
}
</style>
