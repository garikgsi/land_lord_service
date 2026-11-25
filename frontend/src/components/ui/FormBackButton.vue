<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import useCreateNavigation from '../../use/createNavigation';
import type { FormBackButtonProps } from '../../types/components/ui/FormBackButton';

const props = defineProps<FormBackButtonProps>();
const { returnTarget } = useCreateNavigation();
const target = computed(() => returnTarget(props.to));
</script>

<template>
  <RouterLink
    :to="target"
    class="btn ms-auto"
  >
    К списку
  </RouterLink>
</template>
