<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import useBadge from '../../use/badge';
import type { BadgeProps } from '../../types/components/ui/Badge';

const props = withDefaults(defineProps<BadgeProps>(), {
  color: 'azure',
  size: 'normal',
});
const { appearance } = useBadge();
const component = computed(() => (props.to ? RouterLink : props.action ? 'button' : 'span'));
const buttonType = computed(() => (props.action ? 'button' : undefined));
const palette = computed(() => appearance(props.color));
const classes = computed(() => [
  palette.value.className,
  {
    'project-badge--wrap': props.wrap,
    'project-badge--pill': props.pill,
    'project-badge--small': props.size === 'small',
  },
]);
</script>

<template>
  <component
    :is="component"
    :to="to"
    :type="buttonType"
    class="badge project-badge"
    :class="classes"
    :style="palette.style"
  >
    <slot>
    </slot>
  </component>
</template>
