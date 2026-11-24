<script setup lang="ts">
import { computed } from 'vue';
import ReferenceSelect from './ui/ReferenceSelect.vue';
import type { MeterSelectProps } from '../types/components/MeterSelect';

const props = defineProps<MeterSelectProps>();
const model = defineModel<number | null>({ default: null });
const endpoint = computed(
  () => '/api/meters?state=uninstalled&utilityId=' + (props.utilityId ?? 0),
);
const disabled = computed(() => props.disabled || !props.utilityId);
</script>

<template>
  <ReferenceSelect
    :key="endpoint"
    :id="id"
    v-model="model"
    label="Со счетчиком"
    :endpoint="endpoint"
    :initial="initial"
    :disabled="disabled"
    :error="error"
    :required="false"
    placeholder="Без счетчика"
  />
</template>
