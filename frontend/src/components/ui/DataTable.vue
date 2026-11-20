<script setup lang="ts" generic="T extends TableRecord">
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import type {
  DataTableProps,
  DataTableSlots,
  TableColumn,
  TableRecord,
} from '../../types/components/ui/DataTable';

const props = withDefaults(defineProps<DataTableProps<T>>(), {
  page: 1,
  pageSize: 20,
  emptyText: 'Записи не найдены.',
});
const emit = defineEmits<{ pageChange: [page: number] }>();

defineSlots<DataTableSlots<T>>();
const router = useRouter();
const pages = computed(() => Math.max(1, Math.ceil((props.total ?? 0) / props.pageSize)));
const emptyMessage = computed(() =>
  props.error ? 'Не удалось загрузить данные.' : props.emptyText,
);
const tableRows = computed(() =>
  props.rows.map((record) => ({
    record,
    key: record.id,
    clickable: !!props.rowRoute && !props.loading,
    tabIndex: props.rowRoute && !props.loading ? 0 : undefined,
    label: props.rowRoute ? (props.rowLabel?.(record) ?? 'Открыть карточку записи') : undefined,
  })),
);

function cellValue(row: T, column: TableColumn<T>) {
  if (column.value) return column.value(row);

  if (column.key === 'actions') return '';

  const value = row[column.key];

  return value === null || value === undefined ? '' : String(value);
}

function openRow(row: T) {
  if (props.loading || !props.rowRoute) return;

  void router.push(props.rowRoute(row));
}

function rowClicked(event: MouseEvent, row: T) {
  if (
    event.defaultPrevented ||
    event.button !== 0 ||
    event.ctrlKey ||
    event.metaKey ||
    event.altKey
  )
    return;

  const target = event.target;

  if (
    target instanceof Element &&
    target.closest(
      'a, button, input, select, textarea, label, summary, [role="button"], [role="link"], [contenteditable="true"], [data-table-action]',
    )
  )
    return;

  if (window.getSelection()?.toString()) return;

  openRow(row);
}

function rowKeydown(event: KeyboardEvent, row: T) {
  if (event.target !== event.currentTarget || !['Enter', ' '].includes(event.key)) return;

  event.preventDefault();
  openRow(row);
}
</script>

<template>
  <div class="card-header data-table-header">
    <div class="data-table-header-content">
      <slot name="header">
        <h2 class="card-title">
          {{ caption }}
        </h2>
      </slot>
    </div>
    <div
      v-if="$slots.actions"
      class="data-table-actions"
    >
      <slot name="actions">
      </slot>
    </div>
  </div>
  <div class="table-responsive data-table-container">
    <table
      class="table table-vcenter card-table data-table"
      :aria-busy="loading"
      role="table"
    >
      <caption class="visually-hidden">
        {{ caption }}
      </caption>
      <thead role="rowgroup">
        <tr role="row">
          <th
            v-for="column in columns"
            :key="column.key"
            scope="col"
            role="columnheader"
            :class="column.class"
          >
            {{ column.label }}
          </th>
        </tr>
      </thead>
      <tbody role="rowgroup">
        <tr
          v-for="entry in tableRows"
          :key="entry.key"
          role="row"
          :class="{ 'data-table-row': entry.clickable }"
          :tabindex="entry.tabIndex"
          :aria-label="entry.label"
          @click="rowClicked($event, entry.record)"
          @keydown="rowKeydown($event, entry.record)"
        >
          <td
            v-for="(column, index) in columns"
            :key="column.key"
            role="cell"
            :data-label="column.label"
            :class="[column.class, { 'data-table-primary': index === 0 }]"
          >
            <div class="data-table-cell">
              <slot
                name="cell"
                :row="entry.record"
                :column="column"
              >
                {{ cellValue(entry.record, column) }}
              </slot>
            </div>
          </td>
        </tr>
        <tr v-if="loading">
          <td
            :colspan="columns.length"
            class="text-center text-secondary py-4"
            role="status"
          >
            Загрузка…
          </td>
        </tr>
        <tr v-else-if="!rows.length">
          <td
            :colspan="columns.length"
            class="text-center text-secondary py-5"
          >
            <slot name="empty">
              {{ emptyMessage }}
            </slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
  <div
    v-if="total !== undefined"
    class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2"
  >
    <span class="text-secondary">
      Всего: {{ total }} · Страница {{ page }} из {{ pages }}
    </span>
    <div class="d-flex gap-2">
      <button
        type="button"
        class="btn btn-sm"
        :disabled="loading || page <= 1"
        @click="emit('pageChange', page - 1)"
      >
        Назад
      </button>
      <button
        type="button"
        class="btn btn-sm"
        :disabled="loading || page >= pages"
        @click="emit('pageChange', page + 1)"
      >
        Далее
      </button>
    </div>
  </div>
</template>
