<script setup lang="ts">
import useCreateNavigation from '../use/createNavigation';
import Badge from '../components/ui/Badge.vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { IconPlus } from '@tabler/icons-vue';
import useApi from '../use/api';
import { useAuthStore } from '../store/auth';
import { useRolesStore } from '../store/roles';
import DataTable from '../components/ui/DataTable.vue';
import type { TableColumn } from '../types/components/ui/DataTable';
import type { UserTableRow } from '../types/components/UserTableRow';
import UserAvatar from '../components/UserAvatar.vue';
import type { User } from '../types/models/User';
import type { UserList } from '../types/api/User';
import type { MessageResponse } from '../types/use/Api';

const { fromTable } = useCreateNavigation();
const route = useRoute();
const auth = useAuthStore();
const roles = useRolesStore();
const { api, errorText } = useApi();
const items = ref<User[]>([]);
const total = ref(0);
const page = ref(1);
const pageSize = ref(20);
const query = ref('');
const state = ref('all');
const loading = ref(false);
const error = ref('');
const notice = ref(
  route.query.created === '1'
    ? route.query.invitation === 'failed'
      ? 'Пользователь создан, но приглашение не отправлено. Повторите отправку из списка.'
      : 'Пользователь создан. Приглашение отправлено.'
    : '',
);
const mailingId = ref<number | null>(null);
const noticeWarning = ref(route.query.created === '1' && route.query.invitation === 'failed');
const noticeClass = computed(() => (noticeWarning.value ? 'alert-warning' : 'alert-success'));
const rows = computed<UserTableRow[]>(() =>
  items.value.map((user) => ({
    ...user,
    fullName: [user.firstName, user.lastName].filter(Boolean).join(' '),
    statusLabel: user.blocked ? 'Заблокирован' : user.active ? 'Активен' : 'Ожидает активации',
    statusColor: user.blocked ? 'red' : user.active ? 'green' : 'yellow',
    invitationLabel: mailingId.value === user.id ? 'Отправляем…' : 'Отправить приглашение повторно',
  })),
);

const columns: TableColumn<UserTableRow>[] = [
  { key: 'email', label: 'Пользователь' },
  { key: 'roles', label: 'Роли' },
  { key: 'active', label: 'Статус', class: 'text-nowrap' },
  { key: 'actions', label: 'Действия' },
];

function rowRoute(user: UserTableRow) {
  return '/admin/users/' + user.id;
}

function rowLabel(user: UserTableRow) {
  return 'Открыть карточку: ' + (user.fullName || user.email);
}

function canEdit(user: User) {
  return (
    auth.isSuperAdmin ||
    !user.roles.some((role) => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'].includes(role)) ||
    user.id === auth.user?.id
  );
}

const pages = computed(() => Math.max(1, Math.ceil(total.value / pageSize.value)));
let searchTimer: ReturnType<typeof setTimeout> | undefined;
let controller: AbortController | undefined;

async function load() {
  controller?.abort();
  const current = new AbortController();

  controller = current;
  loading.value = true;
  error.value = '';

  try {
    const result = await api<UserList>(
      `/api/admin/users?${new URLSearchParams({ page: String(page.value), query: query.value, state: state.value })}`,
      { signal: current.signal },
    );

    if (current.signal.aborted) return;

    items.value = result.items;
    total.value = result.total;
    pageSize.value = result.pageSize;

    if (page.value > pages.value) {
      page.value = pages.value;
      await load();

      return;
    }
  } catch (e) {
    if (!current.signal.aborted) error.value = errorText(e);
  } finally {
    if (!current.signal.aborted) loading.value = false;
  }
}

function filtersChanged() {
  page.value = 1;
  void load();
}

function searchChanged() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(filtersChanged, 300);
}

function changePage(value: number) {
  page.value = value;
  void load();
}

async function sendInvitation(user: User) {
  if (mailingId.value !== null) return;

  mailingId.value = user.id;
  error.value = '';
  notice.value = '';
  noticeWarning.value = false;

  try {
    const result = await api<MessageResponse>(`/api/admin/users/${user.id}/invitation`, {
      method: 'POST',
    });

    notice.value = result.message;
  } catch (e) {
    error.value = errorText(e);
  } finally {
    mailingId.value = null;
  }
}

async function sendReset(user: User) {
  error.value = '';
  notice.value = '';
  noticeWarning.value = false;

  try {
    await api(`/api/admin/users/${user.id}/password-reset`, { method: 'POST' });
    notice.value = `Ссылка восстановления отправлена на ${user.email}.`;
  } catch (e) {
    error.value = errorText(e);
  }
}

onMounted(load);
onBeforeUnmount(() => {
  controller?.abort();
  clearTimeout(searchTimer);
});
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>
        Пользователи
      </h1>
      <p>
        Учётные записи, доступ и контактные данные
      </p>
    </div>
  </div>
  <div
    v-if="error"
    class="alert alert-danger"
    role="alert"
  >
    {{ error }}
    <button
      class="btn btn-sm ms-2"
      @click="load"
    >
      Повторить
    </button>
  </div>
  <div
    v-if="notice"
    class="alert"
    :class="noticeClass"
    role="status"
  >
    {{ notice }}
  </div>
  <section
    class="card"
    :aria-busy="loading"
  >
    <DataTable
      :rows="rows"
      :columns="columns"
      caption="Пользователи"
      :loading="loading"
      :error="!!error"
      empty-text="Пользователи не найдены."
      :row-route="rowRoute"
      :row-label="rowLabel"
      :total="total"
      :page="page"
      :page-size="pageSize"
      @page-change="changePage"
    >
      <template #header>
        <div class="user-filters">
          <input
            v-model="query"
            @input="searchChanged"
            class="form-control"
            type="search"
            aria-label="Поиск пользователей"
            placeholder="Имя, фамилия, почта или телефон"
            maxlength="120"
          />
          <select
            v-model="state"
            class="form-select"
            aria-label="Статус пользователя"
            @change="filtersChanged"
          >
            <option value="all">
              Все статусы
            </option>
            <option value="active">
              Активные
            </option>
            <option value="blocked">
              Заблокированные
            </option>
            <option value="pending">
              Ожидают активации
            </option>
          </select>
        </div>
      </template>
      <template #actions>
        <RouterLink
          class="btn btn-primary list-add-button"
          aria-label="Добавить пользователя"
          title="Добавить пользователя"
          :to="fromTable('/admin/users/new')"
        >
          <IconPlus :size="19" />
          <span class="list-add-label">
            Добавить
          </span>
        </RouterLink>
      </template>
      <template #cell="{ row: user, column }">
        <template v-if="column.key === 'email'">
          <div class="d-flex align-items-center gap-3 user-table-person">
            <UserAvatar
              :url="user.avatarUrl"
              :first-name="user.firstName"
              :last-name="user.lastName"
            />
            <div class="user-table-identity">
              <div
                v-if="user.fullName"
                class="fw-semibold text-truncate"
                :title="user.fullName"
              >
                {{ user.fullName }}
              </div>
              <div
                class="text-secondary small text-truncate"
                :title="user.email"
              >
                {{ user.email }}
              </div>
            </div>
          </div>
        </template>
        <template v-else-if="column.key === 'roles'">
          <Badge
            v-for="role in user.roles"
            :key="role"
            class="me-1 mb-1"
            :color="roles.colors[role]"
          >
            {{ roles.labels[role] ?? role }}
          </Badge>
          <span
            v-if="!user.roles.length"
            class="text-secondary"
          >
            Не назначены
          </span>
        </template>
        <template v-else-if="column.key === 'active'">
          <Badge :color="user.statusColor">
            {{ user.statusLabel }}
          </Badge>
        </template>
        <template v-else>
          <div class="user-actions">
            <button
              v-if="!user.active"
              class="btn btn-sm"
              :disabled="loading || user.blocked || mailingId !== null"
              @click.stop="sendInvitation(user)"
            >
              {{ user.invitationLabel }}
            </button>
            <button
              v-else-if="canEdit(user)"
              class="btn btn-sm"
              :disabled="loading || user.blocked"
              @click.stop="sendReset(user)"
            >
              Ссылка сброса
            </button>
          </div>
        </template>
      </template>
    </DataTable>
  </section>
</template>
