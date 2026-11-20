<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { IconPlus } from '@tabler/icons-vue';
import useApi from '../use/api';
import { useAuthStore } from '../store/auth';
import { useRolesStore } from '../store/roles';
import UserAvatar from '../components/UserAvatar.vue';
import type { User } from '../types/models/User';
import type { UserList } from '../types/api/User';
import type { MessageResponse } from '../types/use/Api';

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
const notice = ref('');
const mailingId = ref<number | null>(null);
const noticeWarning = ref(false);
const noticeClass = computed(() => (noticeWarning.value ? 'alert-warning' : 'alert-success'));
const rows = computed(() =>
  items.value.map((user) => ({
    ...user,
    fullName: [user.firstName, user.lastName].filter(Boolean).join(' '),
    statusLabel: user.blocked ? 'Заблокирован' : user.active ? 'Активен' : 'Ожидает активации',
    statusClass: user.blocked ? 'bg-red-lt' : user.active ? 'bg-green-lt' : 'bg-yellow-lt',
    invitationLabel: mailingId.value === user.id ? 'Отправляем…' : 'Отправить приглашение повторно',
  })),
);

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
      <div class="page-pretitle">
        Администрирование
      </div>
      <h1>
        Пользователи
      </h1>
      <p>
        Учётные записи, доступ и контактные данные
      </p>
    </div>
    <RouterLink
      class="btn btn-primary"
      to="/admin/users/new"
    >
      <IconPlus :size="19" />
      Добавить пользователя
    </RouterLink>
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
    <div class="card-body user-filters">
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
    <div
      v-if="loading"
      class="text-secondary px-3 pb-2"
      role="status"
    >
      Загрузка…
    </div>
    <div class="table-responsive">
      <table class="table table-vcenter card-table users-table">
        <thead>
          <tr>
            <th>
              Пользователь
            </th>
            <th>
              Роли
            </th>
            <th>
              Статус
            </th>
            <th>
              Действия
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="user in rows"
            :key="user.id"
          >
            <td>
              <div class="d-flex align-items-center gap-3">
                <UserAvatar
                  :url="user.avatarUrl"
                  :first-name="user.firstName"
                  :last-name="user.lastName"
                />
                <div>
                  <div
                    v-if="user.firstName || user.lastName"
                    class="fw-semibold text-break"
                  >
                    {{ user.fullName }}
                  </div>
                  <strong class="text-break">
                    {{ user.email }}
                  </strong>
                  <div class="text-secondary small">
                    {{ user.position || 'Должность не указана' }}
                  </div>
                  <div
                    v-if="user.phone"
                    class="text-secondary small"
                  >
                    {{ user.phone }}
                  </div>
                </div>
              </div>
            </td>
            <td>
              <span
                v-for="role in user.roles"
                :key="role"
                class="badge me-1 mb-1"
                :class="roles.colors[role]"
              >
                {{ roles.labels[role] ?? role }}
              </span>
              <span
                v-if="!user.roles.length"
                class="text-secondary"
              >
                Не назначены
              </span>
            </td>
            <td>
              <span
                class="badge"
                :class="user.statusClass"
              >
                {{ user.statusLabel }}
              </span>
            </td>
            <td>
              <div class="user-actions">
                <RouterLink
                  v-if="canEdit(user)"
                  class="btn btn-sm"
                  :to="'/admin/users/' + user.id"
                >
                  Изменить
                </RouterLink>
                <span
                  v-else
                  class="text-secondary small"
                >
                  Другой администратор
                </span>
                <button
                  v-if="!user.active"
                  class="btn btn-sm"
                  :disabled="user.blocked || mailingId !== null"
                  @click="sendInvitation(user)"
                >
                  {{ user.invitationLabel }}
                </button>
                <button
                  v-else-if="canEdit(user)"
                  class="btn btn-sm"
                  :disabled="user.blocked"
                  @click="sendReset(user)"
                >
                  Ссылка сброса
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!loading && !items.length">
            <td
              colspan="4"
              class="text-center text-secondary py-5"
            >
              Пользователи не найдены.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-3">
      <span class="text-secondary">
        Всего: {{ total }} · Страница {{ page }} из {{ pages }}
      </span>
      <div class="d-flex gap-2">
        <button
          class="btn btn-sm"
          :disabled="page <= 1 || loading"
          @click="changePage(page - 1)"
        >
          Назад
        </button>
        <button
          class="btn btn-sm"
          :disabled="page >= pages || loading"
          @click="changePage(page + 1)"
        >
          Далее
        </button>
      </div>
    </div>
  </section>
</template>
