<script setup lang="ts">
import Badge from './Badge.vue';
import type { DetailsGridProps } from '../../types/components/ui/DetailsGrid';

defineProps<DetailsGridProps>();
</script>

<template>
  <dl class="datagrid mb-0">
    <div
      v-for="field in fields"
      :key="field.label"
      class="datagrid-item"
    >
      <dt class="datagrid-title">
        {{ field.label }}
      </dt>
      <dd class="datagrid-content text-break mb-0">
        <Badge
          v-if="field.to"
          :to="field.to"
          :color="field.color"
          wrap
        >
          {{ field.value }}
        </Badge>
        <template v-else>
          {{ field.value }}
        </template>
      </dd>
    </div>
  </dl>
</template>
