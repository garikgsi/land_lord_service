<script setup lang="ts">
import FormBackButton from '../components/ui/FormBackButton.vue';
import useCreateNavigation from '../use/createNavigation';
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute, onBeforeRouteLeave } from 'vue-router';
import TextInput from '../components/ui/TextInput.vue';
import IntInput from '../components/ui/IntInput.vue';
import useApi, { ApiError } from '../use/api';
import { useAuthStore } from '../store/auth';
import { useRolesStore } from '../store/roles';
import type { NotificationType } from '../types/models/NotificationType';

const { returnAfterSave } = useCreateNavigation();
const route = useRoute();
const auth = useAuthStore();
const rolesStore = useRolesStore();
const { api, errorText } = useApi();
const record = ref<NotificationType>();
const name = ref('');
const days = ref('');
const roles = ref<string[]>([]);
const isActive = ref(true);
const original = ref('');
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const errors = ref<Record<string, string>>({});
const notice = ref('');
const repeating = computed(() => record.value?.timing === 'repeat');
const dayLabel = computed(() => (repeating.value ? 'Повторять через, дней' : 'За сколько дней'));
const title = computed(() => record.value?.name ?? 'Настройки уведомления');
const payload = computed(() => ({
  name: name.value.trim(),
  days: Number(days.value),
  roles: roles.value,
  isActive: isActive.value,
}));
const dayError = computed(() =>
  /^\d+$/.test(days.value) &&
  Number(days.value) <= 2147483647 &&
  (!repeating.value || Number(days.value) >= 1)
    ? ''
    : repeating.value
      ? 'Укажите целое число дней от 1 до 2147483647.'
      : 'Укажите неотрицательное целое число дней.',
);
const canSave = computed(
  () =>
    !loading.value &&
    !saving.value &&
    auth.canManageNotificationTypes &&
    !!name.value.trim() &&
    !dayError.value &&
    !Object.values(errors.value).some(Boolean) &&
    JSON.stringify(payload.value) !== original.value,
);
const saveLabel = computed(() => (saving.value ? 'Сохраняем…' : 'Сохранить'));

function assign(value: NotificationType) {
  record.value = value;
  name.value = value.name;
  days.value = String(value.days);
  roles.value = [...value.roles];
  isActive.value = value.isActive;
  original.value = JSON.stringify(payload.value);
}

function clearErrorsFromInput(event: Event) {
  if (event.target instanceof HTMLInputElement && event.target.type === 'checkbox') return;

  clearErrors();
}

function clearErrors() {
  errors.value = {};
}

async function load() {
  try {
    await rolesStore.load();
    assign(await api<NotificationType>('/api/notification-types/' + route.params.id));
  } catch (e) {
    error.value = errorText(e);
  } finally {
    loading.value = false;
  }
}

async function save() {
  if (!canSave.value) return;

  saving.value = true;
  error.value = '';

  try {
    assign(
      await api<NotificationType>('/api/notification-types/' + route.params.id, {
        method: 'PUT',
        body: JSON.stringify(payload.value),
      }),
    );
    notice.value = 'Настройки сохранены. Они применятся при следующей генерации.';
    saving.value = false;
    await returnAfterSave();
  } catch (e) {
    error.value = errorText(e);

    if (e instanceof ApiError) errors.value = e.errors;
  } finally {
    saving.value = false;
  }
}

onBeforeRouteLeave(() => !auth.user || !saving.value);
onMounted(load);
</script>

<template>
  <div class="page-heading">
    <h1>
      {{ title }}
    </h1>
    <RouterLink
      to="/settings/notification-types"
      class="btn"
    >
      К списку
    </RouterLink>
  </div>
  <div
    v-if="error"
    class="alert alert-danger"
    role="alert"
  >
    {{ error }}
  </div>
  <div
    v-if="notice"
    class="alert alert-success"
    role="status"
  >
    {{ notice }}
  </div>
  <p
    v-if="loading"
    role="status"
  >
    Загрузка…
  </p>
  <section
    v-else-if="record"
    class="card"
  >
    <form
      class="card-body"
      novalidate
      @submit.prevent="save"
      @input="clearErrorsFromInput"
      @change="clearErrors"
    >
      <div class="row g-3 align-items-start">
        <div class="col-12 col-md-8">
          <TextInput
            id="notification-type-name"
            v-model="name"
            label="Наименование"
            required
            :readonly="saving"
            :error="errors.name"
            maxlength="255"
          />
        </div>
        <div class="col-12 col-md-4">
          <IntInput
            id="notification-type-days"
            v-model="days"
            :label="dayLabel"
            required
            :readonly="saving"
            :error="errors.days"
            :validation-error="dayError"
          />
        </div>
      </div>
      <fieldset class="mt-4">
        <legend class="form-label">
          Кого уведомлять
        </legend>
        <div class="d-flex flex-wrap gap-3">
          <label
            v-for="role in rolesStore.items"
            :key="role.code"
            class="form-check"
          >
            <input
              v-model="roles"
              type="checkbox"
              :value="role.code"
              class="form-check-input"
              :disabled="saving"
            />
            <span class="form-check-label">
              {{ role.label }}
            </span>
          </label>
        </div>
        <div
          v-if="errors.roles"
          class="invalid-feedback d-block"
        >
          {{ errors.roles }}
        </div>
      </fieldset>
      <div class="hr-text">
        Состояние
      </div>
      <label class="form-check">
        <input
          v-model="isActive"
          type="checkbox"
          class="form-check-input"
          :disabled="saving"
        />
        <span class="form-check-label">
          Уведомления включены
        </span>
      </label>
      <div class="d-flex flex-wrap gap-2 mt-4">
        <button
          type="submit"
          class="btn btn-primary"
          :disabled="!canSave"
        >
          {{ saveLabel }}
        </button>
        <FormBackButton to="/settings/notification-types" />
      </div>
    </form>
  </section>
</template>
