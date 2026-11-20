<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { IconUser } from '@tabler/icons-vue';
import useApi from '../use/api';
import type { UserAvatarProps } from '../types/components/UserAvatar';

const { request } = useApi();
const props = defineProps<UserAvatarProps>();
const initials = computed(() =>
  [props.firstName, props.lastName]
    .map((name) => Array.from(name?.trim() ?? '')[0] ?? '')
    .join('')
    .toLocaleUpperCase('ru-RU'),
);
const image = ref<string | null>(null);
const imageUrl = computed(() => props.preview || image.value || undefined);
let controller: AbortController | undefined;

// Exception: a private image URL change needs a cancellable fetch and blob URL cleanup.
watch(
  () => props.url,
  async (url) => {
    controller?.abort();
    controller = new AbortController();

    if (image.value) {
      URL.revokeObjectURL(image.value);
    }

    image.value = null;

    if (!url) {
      return;
    }

    const current = controller;

    try {
      const response = await request(url, { signal: current.signal });
      const blob = await response.blob();

      if (!current.signal.aborted) {
        image.value = URL.createObjectURL(blob);
      }
    } catch {
      // Initials remain available if the image is missing.
    }
  },
  { immediate: true },
);

onBeforeUnmount(() => {
  controller?.abort();

  if (image.value) {
    URL.revokeObjectURL(image.value);
  }
});
</script>

<template>
  <span
    class="avatar user-avatar bg-blue-lt"
    aria-hidden="true"
  >
    <img
      v-if="imageUrl"
      :src="imageUrl"
      alt=""
    />
    <template v-else-if="initials">
      {{ initials }}
    </template>
    <IconUser
      v-else
      :size="22"
      stroke="1.6"
    />
  </span>
</template>
