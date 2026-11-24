<script setup lang="ts">
import Badge from '../components/ui/Badge.vue';
import { computed, onMounted, ref } from 'vue';
import DataTable from '../components/ui/DataTable.vue';
import useApi from '../use/api';
import { useRolesStore } from '../store/roles';
import type { NotificationTypeList } from '../types/api/NotificationTypeList';
import type { NotificationType } from '../types/models/NotificationType';
import type { TableColumn } from '../types/components/ui/DataTable';

const { api, errorText } = useApi();
const roles = useRolesStore();
const items = ref<NotificationType[]>([]);
const loading = ref(true);
const error = ref('');
const rows = computed(() =>
  items.value.map((item) => ({
    ...item,
    roleLabels: item.roles
      .map((role) => roles.labels[role as keyof typeof roles.labels] ?? role)
      .join(', '),
    dayLabel:
      item.timing === 'repeat' ? 'Каждые ' + item.days + ' дн.' : 'За ' + item.days + ' дн.',
    statusLabel: item.isActive ? 'Включено' : 'Выключено',
    statusColor: item.isActive ? 'green' : 'secondary',
  })),
);
const columns: TableColumn<(typeof rows.value)[number]>[] = [
  { key: 'name', label: 'Тип уведомления' },
  { key: 'dayLabel', label: 'Срок / периодичность' },
  { key: 'roleLabels', label: 'Получатели' },
  { key: 'statusLabel', label: 'Состояние' },
];

function rowRoute(row: NotificationType) {
  return '/settings/notification-types/' + row.id;
}

async function load() {
  loading.value = true;
  error.value = '';

  try {
    await roles.load();
    items.value = (await api<NotificationTypeList>('/api/notification-types')).items;
  } catch (e) {
    error.value = errorText(e);
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div class="page-heading">
    <h1>
      Настройки уведомлений
    </h1>
  </div>
  <div
    v-if="error"
    class="alert alert-danger"
    role="alert"
  >
    {{ error }}
    <button
      class="btn ms-2"
      @click="load"
    >
      Повторить
    </button>
  </div>
  <section class="card">
    <DataTable
      :rows="rows"
      :columns="columns"
      caption="Настройки уведомлений"
      :loading="loading"
      :error="!!error"
      :row-route="rowRoute"
    >
      <template #cell="{ row, column }">
        <Badge
          v-if="column.key === 'statusLabel'"
          :color="row.statusColor"
        >
          {{ row.statusLabel }}
        </Badge>
        <template v-else>
          {{ column.value?.(row) ?? row[column.key as keyof typeof row] }}
        </template>
      </template>
    </DataTable>
  </section>
</template>
