<script setup lang="ts" generic="T extends TableRecord">
import { computed, shallowRef, ref } from 'vue';
import { IconTrash } from '@tabler/icons-vue';
import Pagination from './Pagination.vue';
import DeleteConfirmModal from './DeleteConfirmModal.vue';
import useApi from '../../use/api';
import { useRoute, useRouter } from 'vue-router';
import type {
  DataTableProps,
  DataTableSlots,
  TableColumn,
  TableRecord,
} from '../../types/components/ui/DataTable';

const props = withDefaults(defineProps<DataTableProps<T>>(), {
  page: 1,
  pageSize: 20,
  clientPagination: undefined,
  emptyText: 'Записи не найдены.',
});
const emit = defineEmits<{ pageChange: [page: number]; deleted: [row: T] }>();

const slots = defineSlots<DataTableSlots<T>>();
const router = useRouter();
const route = useRoute();
const selected = shallowRef<T | null>(null);
const deleting = ref(false);
const deleteError = ref('');
const { api, errorText } = useApi();
const localPage = ref(1);
const clientPagination = computed(() => props.clientPagination ?? props.total === undefined);
const totalCount = computed(() =>
  clientPagination.value ? props.rows.length : (props.total ?? 0),
);
const pages = computed(() => Math.max(1, Math.ceil(totalCount.value / props.pageSize)));
const currentPage = computed(() =>
  clientPagination.value ? Math.min(localPage.value, pages.value) : props.page,
);
const visibleRows = computed(() =>
  clientPagination.value
    ? props.rows.slice((currentPage.value - 1) * props.pageSize, currentPage.value * props.pageSize)
    : props.rows,
);
const hasRowActions = computed(() => !!(props.deleteEndpoint || slots.rowActions));
const columnCount = computed(() => props.columns.length + (hasRowActions.value ? 1 : 0));
const selectedName = computed(() =>
  selected.value && 'name' in selected.value
    ? String(selected.value.name)
    : selected.value && 'contractNum' in selected.value
      ? String(selected.value.contractNum)
      : String(selected.value?.id ?? ''),
);

function requestDelete(row: T) {
  selected.value = row;
  deleteError.value = '';
}

function cancelDelete() {
  if (!deleting.value) selected.value = null;
}

async function remove() {
  if (!selected.value || !props.deleteEndpoint || deleting.value) return;

  const row = selected.value;

  deleting.value = true;
  deleteError.value = '';

  try {
    await api(props.deleteEndpoint + '/' + row.id, { method: 'DELETE' });
    selected.value = null;
    emit('deleted', row);
  } catch (e) {
    deleteError.value = errorText(e);
  } finally {
    deleting.value = false;
  }
}

const emptyMessage = computed(() =>
  props.error ? 'Не удалось загрузить данные.' : props.emptyText,
);
const tableRows = computed(() =>
  visibleRows.value.map((record) => ({
    record,
    key: record.id,
    clickable: !!(props.rowRoute || props.rowAction) && !props.loading,
    tabIndex: (props.rowRoute || props.rowAction) && !props.loading ? 0 : undefined,
    label:
      props.rowRoute || props.rowAction
        ? (props.rowLabel?.(record) ?? 'Открыть карточку записи')
        : undefined,
  })),
);

function changePage(value: number) {
  if (props.loading || value < 1 || value > pages.value) return;

  if (clientPagination.value) localPage.value = value;
  else emit('pageChange', value);
}

function cellValue(row: T, column: TableColumn<T>) {
  if (column.value) return column.value(row);

  if (column.key === 'actions') return '';

  const value = row[column.key];

  return value === null || value === undefined ? '' : String(value);
}

function openRow(row: T) {
  if (props.loading) return;

  if (props.rowAction) {
    props.rowAction(row);

    return;
  }

  if (!props.rowRoute) return;

  const target = router.resolve(props.rowRoute(row));

  void router.push({
    path: target.path,
    query: { ...target.query, returnTo: route.fullPath },
    hash: target.hash,
  });
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
          <th
            v-if="hasRowActions"
            scope="col"
            class="w-1"
          >
            Действия
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
          <td
            v-if="hasRowActions"
            data-label="Действия"
            class="text-end"
          >
            <div class="d-inline-flex align-items-center gap-1 text-nowrap">
              <slot
                name="rowActions"
                :row="entry.record"
              />
              <button
                type="button"
                class="btn btn-sm btn-icon text-danger"
                v-if="deleteEndpoint && (!deleteAllowed || deleteAllowed(entry.record))"
                aria-label="Удалить запись"
                title="Удалить"
                :disabled="loading || deleting"
                @click.stop="requestDelete(entry.record)"
              >
                <IconTrash
                  :size="18"
                  aria-hidden="true"
                />
              </button>
            </div>
          </td>
        </tr>
        <tr v-if="loading">
          <td
            :colspan="columnCount"
            class="text-center text-secondary py-4"
            role="status"
          >
            Загрузка…
          </td>
        </tr>
        <tr v-else-if="!rows.length">
          <td
            :colspan="columnCount"
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
  <div class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
    <span class="text-secondary">
      Всего: {{ totalCount }} · Страница {{ currentPage }} из {{ pages }}
    </span>
    <Pagination
      :page="currentPage"
      :pages="pages"
      :disabled="loading"
      @change="changePage"
    />
  </div>
  <DeleteConfirmModal
    v-if="selected"
    :item-name="selectedName"
    message="Запись будет помечена удалённой. Данные сохранятся."
    :busy="deleting"
    :error="deleteError"
    @cancel="cancelDelete"
    @confirm="remove"
  />
</template>
