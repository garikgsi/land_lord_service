<script setup lang="ts">
import { computed } from 'vue';
import { IconChevronLeft, IconChevronRight } from '@tabler/icons-vue';
import { paginationPages } from '../../use/pagination';
import type { PaginationProps } from '../../types/components/ui/Pagination';

const props = defineProps<PaginationProps>();
const emit = defineEmits<{ change: [page: number] }>();
const previousDisabled = computed(() => props.disabled || props.page <= 1);
const nextDisabled = computed(() => props.disabled || props.page >= props.pages);
const links = computed(() =>
  paginationPages(props.page, props.pages).map((value, index) => ({
    key: index,
    value,
    gap: value === 'ellipsis',
    active: value === props.page,
    current: value === props.page ? ('page' as const) : undefined,
    label: 'Страница ' + value,
  })),
);

function select(page: number | 'ellipsis') {
  if (
    props.disabled ||
    typeof page !== 'number' ||
    page < 1 ||
    page > props.pages ||
    page === props.page
  )
    return;

  emit('change', page);
}
</script>

<template>
  <nav aria-label="Страницы таблицы">
    <ul class="pagination pagination-sm m-0">
      <li
        class="page-item"
        :class="{ disabled: previousDisabled }"
      >
        <button
          type="button"
          class="page-link"
          aria-label="Предыдущая страница"
          :disabled="previousDisabled"
          @click="select(page - 1)"
        >
          <IconChevronLeft
            :size="20"
            aria-hidden="true"
          />
        </button>
      </li>
      <li
        v-for="link in links"
        :key="link.key"
        class="page-item"
        :class="{ active: link.active, disabled: link.gap }"
      >
        <span
          v-if="link.gap"
          class="page-link"
          aria-hidden="true"
        >
          …
        </span>
        <button
          v-else
          type="button"
          class="page-link"
          :aria-label="link.label"
          :aria-current="link.current"
          :disabled="disabled"
          @click="select(link.value)"
        >
          {{ link.value }}
        </button>
      </li>
      <li
        class="page-item"
        :class="{ disabled: nextDisabled }"
      >
        <button
          type="button"
          class="page-link"
          aria-label="Следующая страница"
          :disabled="nextDisabled"
          @click="select(page + 1)"
        >
          <IconChevronRight
            :size="20"
            aria-hidden="true"
          />
        </button>
      </li>
    </ul>
  </nav>
</template>
