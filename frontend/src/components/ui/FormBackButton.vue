<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { creationReturnPath } from '../../use/createNavigation';
import type { FormBackButtonProps } from '../../types/components/ui/FormBackButton';

const props = defineProps<FormBackButtonProps>();
const route = useRoute();
const target = computed(() => creationReturnPath(route.query.returnTo, '') || props.to);
</script>

<template>
  <RouterLink
    :to="target"
    class="btn ms-auto"
  >
    К списку
  </RouterLink>
</template>
