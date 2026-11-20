<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import {
  RouterLink,
  onBeforeRouteLeave,
  onBeforeRouteUpdate,
  useRoute,
  useRouter,
} from 'vue-router';
import { IconArrowLeft, IconTrash } from '@tabler/icons-vue';
import EmailInput from '../components/ui/EmailInput.vue';
import PhoneInput from '../components/ui/PhoneInput.vue';
import UserAvatar from '../components/UserAvatar.vue';
import { useAuthStore } from '../store/auth';
import { useRolesStore } from '../store/roles';
import useApi, { ApiError } from '../use/api';
import useValidation from '../use/validation';
import type { ApiErrors } from '../types/use/Api';
import useEvent from '../use/event';
import { userFormPayload } from '../userForm';
import type { User } from '../types/models/User';
import type { Role } from '../types/models/Role';
import type { UserSaveResponse } from '../types/api/User';
import type { UserFormDraft } from '../types/forms/User';

const auth = useAuthStore();
const roles = useRolesStore();
const route = useRoute();
const router = useRouter();
const { api, errorText } = useApi();
const events = useEvent();
const selected = ref<User | null>(null);
const loading = ref(false);
const loadError = ref('');
const formError = ref('');
const saving = ref(false);
const notice = ref('');
const noticeWarning = ref(false);
const noticeClass = computed(() => (noticeWarning.value ? 'alert-warning' : 'alert-success'));
const avatarBusy = ref(false);
const avatarError = ref('');
const avatarNotice = ref('');
const avatarPreview = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const form = reactive<UserFormDraft>({
  email: '',
  firstName: '',
  lastName: '',
  phone: '',
  position: '',
  roles: [],
  blocked: false,
});
const originalValues = ref('');
const serverErrors = ref<ApiErrors>({});
const nameTouched = ref(false);
const validationErrors = computed(() => useValidation().userProfile(form));
const nameError = computed(
  () =>
    serverErrors.value.firstName ||
    (nameTouched.value ? validationErrors.value.firstName : '') ||
    '',
);

function clearServerErrors() {
  serverErrors.value = {};
}

const creating = computed(() => !route.params.id);
const pageTitle = computed(() =>
  creating.value ? 'Пригласить пользователя' : 'Изменить пользователя',
);
const saveLabel = computed(() =>
  saving.value ? 'Сохраняем…' : creating.value ? 'Создать и отправить приглашение' : 'Сохранить',
);
const avatarPrompt = computed(() =>
  !selected.value
    ? 'Фото можно загрузить после создания пользователя.'
    : avatarBusy.value
      ? 'Сохраняем фото…'
      : 'Нажмите на кружок, чтобы изменить фото.',
);
const visibleRoles = computed(() =>
  roles.items.filter((role) => role.code !== 'ROLE_SUPER_ADMIN' || form.roles.includes(role.code)),
);
const payload = computed(() => userFormPayload(form));
const isDirty = computed(() => JSON.stringify(payload.value) !== originalValues.value);
const editingSelf = computed(() => selected.value !== null && selected.value.id === auth.user?.id);
const canEdit = computed(
  () =>
    !selected.value ||
    auth.isSuperAdmin ||
    !selected.value.roles.some((role) => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'].includes(role)) ||
    editingSelf.value,
);
const canSave = computed(
  () =>
    canEdit.value &&
    isDirty.value &&
    Object.keys(validationErrors.value).length === 0 &&
    Object.keys(serverErrors.value).length === 0 &&
    !saving.value &&
    !avatarBusy.value &&
    !loading.value,
);
let controller: AbortController | undefined;

function roleDisabled(role: Role) {
  return (
    !roles.items.find((item) => item.code === role)?.assignable ||
    (role === 'ROLE_ADMIN' && form.roles.includes('ROLE_SUPER_ADMIN'))
  );
}

function fill(user: User | null) {
  selected.value = user;
  nameTouched.value = false;
  serverErrors.value = {};
  Object.assign(form, {
    email: user?.email ?? '',
    firstName: user?.firstName ?? '',
    lastName: user?.lastName ?? '',
    phone: user?.phone ?? '',
    position: user?.position ?? '',
    roles: [...(user?.roles ?? [])],
    blocked: user?.blocked ?? false,
  });
  originalValues.value = JSON.stringify(payload.value);
}

async function load(id = route.params.id, query = route.query) {
  controller?.abort();
  const current = new AbortController();

  controller = current;
  loading.value = true;
  loadError.value = '';
  formError.value = '';
  avatarError.value = '';
  avatarNotice.value = '';
  notice.value = '';
  fill(null);

  if (query.created === '1') {
    noticeWarning.value = query.invitation === 'failed';
    notice.value = noticeWarning.value
      ? 'Пользователь создан. Письмо не отправлено: отправьте приглашение повторно из списка пользователей после настройки почты.'
      : 'Пользователь создан. Приглашение отправлено.';
  }

  try {
    if (id) {
      const user = await api<User>('/api/admin/users/' + id, { signal: current.signal });

      if (!current.signal.aborted) fill(user);
    }
  } catch (e) {
    if (!current.signal.aborted) loadError.value = errorText(e);
  } finally {
    if (!current.signal.aborted) loading.value = false;
  }
}

onMounted(() => {
  void load();
});

onBeforeRouteUpdate(async (to) => {
  if (saving.value || avatarBusy.value) return false;

  await load(to.params.id, to.query);
});

async function close() {
  if (!saving.value && !avatarBusy.value) await router.push('/admin/users');
}

async function chooseAvatar(event: Event) {
  const input = event.target as HTMLInputElement;
  const chosen = input.files?.[0];

  input.value = '';

  if (!chosen || !selected.value || avatarBusy.value) return;

  avatarError.value = '';
  avatarNotice.value = '';

  if (!['image/jpeg', 'image/png', 'image/webp'].includes(chosen.type) || chosen.size > 2000000) {
    avatarError.value = 'Выберите JPEG, PNG или WebP размером не более 2 МБ.';

    return;
  }

  avatarBusy.value = true;
  avatarPreview.value = URL.createObjectURL(chosen);

  try {
    const body = new FormData();

    body.append('avatar', chosen);
    const updated = await api<User>('/api/admin/users/' + selected.value.id + '/avatar', {
      method: 'POST',
      body,
    });

    selected.value = { ...selected.value, avatarUrl: updated.avatarUrl };

    if (auth.user?.id === updated.id) auth.user = { ...auth.user, avatarUrl: updated.avatarUrl };

    avatarNotice.value = 'Фото сохранено.';
  } catch (e) {
    avatarError.value = errorText(e);
  } finally {
    if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value);

    avatarPreview.value = null;
    avatarBusy.value = false;
  }
}

async function deleteAvatar() {
  if (!selected.value || avatarBusy.value) return;

  avatarBusy.value = true;
  avatarError.value = '';
  avatarNotice.value = '';

  try {
    await api('/api/admin/users/' + selected.value.id + '/avatar', { method: 'DELETE' });
    selected.value = { ...selected.value, avatarUrl: null };

    if (auth.user?.id === selected.value.id) auth.user = { ...auth.user, avatarUrl: null };

    avatarNotice.value = 'Фото удалено.';
  } catch (e) {
    avatarError.value = errorText(e);
  } finally {
    avatarBusy.value = false;
  }
}

async function save() {
  if (!canSave.value) return;

  formError.value = '';
  saving.value = true;
  const wasCreating = creating.value;
  const credentialsChanged =
    selected.value !== null &&
    (payload.value.email !== selected.value.email ||
      payload.value.blocked !== selected.value.blocked ||
      JSON.stringify(payload.value.roles) !==
        JSON.stringify(userFormPayload(selected.value).roles));
  const ownCredentialsChanged = credentialsChanged && editingSelf.value;
  const pendingLinkChanged = credentialsChanged && !selected.value?.active;

  try {
    const saved = await api<UserSaveResponse>(
      wasCreating ? '/api/admin/users' : '/api/admin/users/' + route.params.id,
      { method: wasCreating ? 'POST' : 'PUT', body: JSON.stringify(payload.value) },
    );

    if (ownCredentialsChanged) {
      events.emit('session-expired');

      return;
    }

    if (auth.user?.id === saved.id) auth.user = saved;

    fill(saved);

    if (wasCreating) {
      saving.value = false;
      await router.replace({
        path: '/admin/users/' + saved.id,
        query: { created: '1', invitation: saved.invitationSent === false ? 'failed' : 'sent' },
      });
    } else {
      noticeWarning.value = pendingLinkChanged;
      notice.value = pendingLinkChanged
        ? 'Данные сохранены. Для активации отправьте приглашение повторно из списка пользователей.'
        : 'Изменения сохранены.';
    }
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) {
      serverErrors.value = e.errors;
      formError.value = e.message;
    } else formError.value = errorText(e);
  } finally {
    saving.value = false;
  }
}

onBeforeRouteLeave(() => !auth.user || (!saving.value && !avatarBusy.value));
onBeforeUnmount(() => {
  controller?.abort();

  if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value);
});
</script>

<template>
  <div class="page-heading">
    <div>
      <div class="page-pretitle">
        Администрирование
      </div>
      <h1>
        {{ pageTitle }}
      </h1>
      <p>
        {{ selected?.email || 'Учётная запись, контакты и доступ' }}
      </p>
    </div>
    <RouterLink
      to="/admin/users"
      class="btn"
    >
      <IconArrowLeft :size="18" />
      К списку пользователей
    </RouterLink>
  </div>
  <div
    v-if="loading"
    class="text-secondary py-4"
    role="status"
  >
    Загрузка пользователя…
  </div>
  <div
    v-else-if="loadError"
    class="alert alert-danger"
    role="alert"
  >
    {{ loadError }}
    <button
      class="btn btn-sm ms-2"
      @click="load()"
    >
      Повторить
    </button>
  </div>
  <div
    v-else-if="!canEdit"
    class="alert alert-warning"
  >
    Изменять другого администратора может только суперадминистратор.
  </div>
  <template v-else>
    <div
      v-if="notice"
      class="alert"
      :class="noticeClass"
      role="status"
    >
      {{ notice }}
    </div>
    <section class="card">
      <form
        class="card-body p-3 p-md-4"
        novalidate
        @input="clearServerErrors"
        @submit.prevent="save"
      >
        <div
          v-if="formError"
          class="alert alert-danger"
          role="alert"
        >
          {{ formError }}
        </div>
        <fieldset
          :disabled="saving || avatarBusy"
          class="border-0 p-0 m-0"
        >
          <template v-if="!selected">
            <p class="text-secondary">
              Укажите имя и почту. Пользователь получит ссылку, задаст пароль и проверит остальные
              данные. После этого учётная запись станет активной.
            </p>
          </template>
          <div
            class="avatar-editor mb-4"
            :aria-busy="avatarBusy"
          >
            <input
              id="user-avatar"
              ref="fileInput"
              type="file"
              hidden
              accept="image/jpeg,image/png,image/webp"
              @change="chooseAvatar"
              @cancel.stop
            />
            <button
              type="button"
              class="avatar-picker"
              aria-label="Выбрать фото пользователя"
              :disabled="!selected || avatarBusy || saving"
              @click="fileInput?.click()"
            >
              <UserAvatar
                :url="selected?.avatarUrl ?? null"
                :first-name="form.firstName"
                :last-name="form.lastName"
                :preview="avatarPreview"
              />
            </button>
            <div>
              <div class="fw-semibold mb-1">
                Фото профиля
              </div>
              <p class="text-secondary small mb-1">
                {{ avatarPrompt }}
              </p>
              <p
                v-if="selected"
                class="text-secondary small mb-0"
              >
                Фото сохраняется сразу. JPEG, PNG или WebP, до 2 МБ.
              </p>
              <button
                v-if="selected?.avatarUrl"
                type="button"
                class="btn btn-sm btn-outline-danger mt-2"
                @click="deleteAvatar"
              >
                <IconTrash :size="16" />
                Удалить фото
              </button>
            </div>
          </div>
          <p
            v-if="avatarError"
            class="text-danger small"
            role="alert"
          >
            {{ avatarError }}
          </p>
          <p
            v-if="avatarNotice"
            class="text-success small"
            role="status"
          >
            {{ avatarNotice }}
          </p>
          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <label
                class="form-label"
                for="user-first-name"
              >
                Имя
              </label>
              <input
                id="user-first-name"
                v-model="form.firstName"
                class="form-control"
                :class="{ 'is-invalid': !!nameError }"
                :aria-invalid="!!nameError"
                aria-describedby="user-first-name-error"
                @input="nameTouched = true"
                @blur="nameTouched = true"
                required
                maxlength="100"
                autocomplete="given-name"
              />
              <div
                v-if="nameError"
                id="user-first-name-error"
                class="invalid-feedback"
              >
                {{ nameError }}
              </div>
            </div>
            <div class="col-sm-6">
              <label
                class="form-label"
                for="user-last-name"
              >
                Фамилия
              </label>
              <input
                id="user-last-name"
                v-model="form.lastName"
                class="form-control"
                maxlength="100"
                autocomplete="family-name"
              />
            </div>
          </div>
          <label
            class="form-label"
            for="user-position"
          >
            Должность
          </label>
          <input
            id="user-position"
            v-model="form.position"
            class="form-control mb-3"
            maxlength="160"
          />
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label
                class="form-label"
                for="user-email"
              >
                Электронная почта
              </label>
              <EmailInput
                :error="serverErrors.email"
                id="user-email"
                v-model="form.email"
                required
                maxlength="254"
                autocomplete="off"
              />
            </div>
            <div class="col-md-6">
              <label
                class="form-label"
                for="user-phone"
              >
                Телефон
              </label>
              <PhoneInput
                :error="serverErrors.phone"
                id="user-phone"
                v-model="form.phone"
              />
            </div>
          </div>
          <fieldset class="mb-3">
            <legend class="form-label">
              Роли доступа
            </legend>
            <div class="role-options">
              <label
                v-for="role in visibleRoles"
                :key="role.code"
                class="form-check mb-0"
              >
                <input
                  v-model="form.roles"
                  :value="role.code"
                  :disabled="roleDisabled(role.code)"
                  type="checkbox"
                  class="form-check-input"
                />
                <span
                  class="form-check-label"
                  :class="{ 'badge bg-purple-lt': role.code === 'ROLE_SUPER_ADMIN' }"
                >
                  {{ role.label }}
                </span>
              </label>
            </div>
          </fieldset>
          <div class="hr-text hr-text-start my-4">
            Доступ к системе
          </div>
          <div class="mb-3">
            <label class="form-check mb-0">
              <input
                v-model="form.blocked"
                class="form-check-input"
                type="checkbox"
                :disabled="editingSelf"
              />
              <span class="form-check-label">
                Заблокировать вход
              </span>
            </label>
            <p
              v-if="editingSelf"
              class="text-secondary small mt-2 mb-0"
            >
              Свою учётную запись заблокировать нельзя.
            </p>
          </div>
          <p
            class="text-secondary small"
            v-if="!auth.isSuperAdmin"
          >
            Административные роли назначает и снимает суперадминистратор.
          </p>
          <p
            v-if="selected?.id === auth.user?.id"
            class="text-secondary small"
          >
            При изменении своей почты или ролей потребуется войти снова.
          </p>
          <div class="d-flex justify-content-end gap-2 mt-4">
            <button
              type="button"
              class="btn"
              @click="close"
            >
              Отмена
            </button>
            <button
              class="btn btn-primary"
              :disabled="!canSave"
            >
              {{ saveLabel }}
            </button>
          </div>
        </fieldset>
      </form>
    </section>
  </template>
</template>
