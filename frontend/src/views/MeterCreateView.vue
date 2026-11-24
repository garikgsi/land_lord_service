<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();
const component = computed(() =>
  route.query.objectId || route.query.spaceId ? MeterAssignment : MeterCreation,
);
const MeterAssignment = defineAsyncComponent(() => import('./MeterAssignmentView.vue'));
const MeterCreation = defineAsyncComponent(() => import('./MeterEditView.vue'));
</script>

<template>
  <component :is="component" />
</template>
