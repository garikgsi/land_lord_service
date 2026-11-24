<script setup lang="ts">
import { computed } from 'vue';
import Badge from './ui/Badge.vue';
import { meterReadingDate } from '../use/meterReading';
import type { MeterDetailsProps } from '../types/components/MeterDetails';

const props = defineProps<MeterDetailsProps>();
const reading = computed(() =>
  props.meter.nextReadingDueDate
    ? meterReadingDate(props.meter.nextReadingDueDate)
    : 'Не требуются',
);
const verification = computed(() => meterReadingDate(props.meter.nextVerificationDate));
</script>

<template>
  <div class="d-flex flex-wrap gap-2 mb-3">
    <Badge color="azure">
      Следующие показания {{ reading }}
    </Badge>
    <Badge color="azure">
      Следующая поверка {{ verification }}
    </Badge>
  </div>
</template>
