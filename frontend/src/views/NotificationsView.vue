<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { IconCheck } from '@tabler/icons-vue';
import DataTable from '../components/ui/DataTable.vue';
import useApi from '../use/api';
import { useNotificationsStore } from '../store/notifications';
import type { Notification, NotificationRow } from '../types/models/Notification';
import type { RegistryList } from '../types/api/Registry';
import type { TableColumn } from '../types/components/ui/DataTable';

const { api, errorText } = useApi();
const notifications = useNotificationsStore();
const items = ref<Notification[]>([]);
const page = ref(1);
const total = ref(0);
const query = ref('');
const status = ref('all');
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const columns: TableColumn<NotificationRow>[] = [
  { key: 'title', label: 'Сообщение' },
  { key: 'dateLabel', label: 'Получено' },
  { key: 'dueLabel', label: 'Дата исполнения' },
  { key: 'actions', label: 'Статус' },
];
const rows = computed<NotificationRow[]>(() =>
  items.value.map((item) => ({
    ...item,
    dateLabel: new Date(item.createdAt).toLocaleString('ru-RU'),
    dueLabel: item.dueDate ? new Date(item.dueDate + 'T00:00:00').toLocaleDateString('ru-RU') : '—',
    statusLabel: item.readAt ? 'Прочитано' : 'Новое',
    unread: !item.readAt,
  })),
);
const allReadDisabled = computed(() => saving.value || notifications.unreadCount === 0);
let controller: AbortController | undefined;
let timer: ReturnType<typeof setTimeout> | undefined;

function rowRoute(row: NotificationRow) {
  return '/notifications/' + row.id;
}

async function load(nextPage = page.value) {
  controller?.abort();
  const current = new AbortController();

  controller = current;
  loading.value = true;
  error.value = '';

  try {
    const result = await api<RegistryList<Notification>>(
      '/api/notifications?' +
        new URLSearchParams({ page: String(nextPage), query: query.value, status: status.value }),
      { signal: current.signal },
    );

    if (current.signal.aborted) return;

    items.value = result.items;
    page.value = result.page;
    total.value = result.total;
    await notifications.refresh();
  } catch (e) {
    if (!current.signal.aborted) error.value = errorText(e);
  } finally {
    if (!current.signal.aborted) loading.value = false;
  }
}

function search() {
  clearTimeout(timer);
  timer = setTimeout(() => load(1), 300);
}

async function read(id: number) {
  if (saving.value) return;

  saving.value = true;

  try {
    await notifications.markRead(id);
    await load();
  } catch (e) {
    error.value = errorText(e);
  } finally {
    saving.value = false;
  }
}

async function readAll() {
  if (allReadDisabled.value) return;

  saving.value = true;

  try {
    await notifications.markAllRead();
    await load(1);
  } catch (e) {
    error.value = errorText(e);
  } finally {
    saving.value = false;
  }
}

onMounted(() => load());
onBeforeUnmount(() => {
  controller?.abort();
  clearTimeout(timer);
});
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>
        Уведомления
      </h1>
    </div>
  </div>
  <div
    v-if="error"
    class="alert alert-danger"
    role="alert"
  >
    {{ error }}
    <button
      class="btn ms-2"
      @click="load()"
    >
      Повторить
    </button>
  </div>
  <section class="card notifications-card">
    <DataTable
      :rows="rows"
      :columns="columns"
      caption="Уведомления"
      :row-route="rowRoute"
      :loading="loading"
      :error="!!error"
      :total="total"
      :page="page"
      empty-text="Уведомлений пока нет."
      @page-change="load"
    >
      <template #header>
        <label
          for="notification-search"
          class="visually-hidden"
        >
          Поиск уведомлений
        </label>
        <input
          id="notification-search"
          v-model="query"
          type="search"
          class="form-control"
          placeholder="Заголовок или текст"
          @input="search"
        />
      </template>
      <template #actions>
        <div class="d-flex flex-wrap gap-2">
          <label
            for="notification-status"
            class="visually-hidden"
          >
            Состояние уведомлений
          </label>
          <select
            id="notification-status"
            v-model="status"
            class="form-select w-auto"
            @change="load(1)"
          >
            <option value="all">
              Все сообщения
            </option>
            <option value="unread">
              Непрочитанные
            </option>
          </select>
          <button
            type="button"
            class="btn"
            :disabled="allReadDisabled"
            @click="readAll"
          >
            Прочитать все
          </button>
        </div>
      </template>
      <template #cell="{ row, column }">
        <div
          v-if="column.key === 'title'"
          :class="{ 'fw-semibold': row.unread }"
          class="notification-summary"
        >
          <span
            v-if="row.unread"
            class="status-dot bg-blue me-2"
            aria-label="Непрочитанное"
          >
          </span>
          {{ row.title }}
        </div>
        <template v-else-if="column.key === 'dateLabel'">
          {{ row.dateLabel }}
        </template>
        <template v-else-if="column.key === 'dueLabel'">
          {{ row.dueLabel }}
        </template>
        <template v-else-if="column.key === 'actions'">
          <button
            v-if="row.unread"
            type="button"
            class="btn btn-sm"
            :disabled="saving"
            title="Отметить прочитанным"
            aria-label="Отметить прочитанным"
            @click.stop="read(row.id)"
          >
            <IconCheck :size="16" />
            <span class="ms-1">
              Прочитать
            </span>
          </button>
          <span
            v-else
            class="text-secondary"
          >
            {{ row.statusLabel }}
          </span>
        </template>
      </template>
    </DataTable>
  </section>
</template>
