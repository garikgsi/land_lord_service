<script setup lang="ts">
import PhoneInput from '../components/ui/PhoneInput.vue';
import EmailInput from '../components/ui/EmailInput.vue';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { RouterLink, onBeforeRouteUpdate, useRoute, useRouter } from 'vue-router';
import AuthCard from '../components/AuthCard.vue';
import UserAvatar from '../components/UserAvatar.vue';
import { IconTrash } from '@tabler/icons-vue';
import useApi from '../use/api';
import useValidation from '../use/validation';
import { useAuthStore } from '../store/auth';
import type { InvitationProfile } from '../types/api/Auth';

const auth = useAuthStore();

const { api, request, errorText } = useApi();
const route = useRoute();
const router = useRouter();
const token = ref('');
const form = reactive({
  firstName: '',
  lastName: '',
  phone: '',
  position: '',
  password: '',
  confirm: '',
});
const profile = ref<InvitationProfile | null>(null);
const loading = ref(false);
const busy = ref(false);
const error = ref('');
const invalid = ref('');
const visible = ref(false);
const avatarBusy = ref(false);
const avatarImage = ref<string | null>(null);
const avatarMessage = ref('');
const avatarInput = ref<HTMLInputElement | null>(null);
const nameError = computed(() => useValidation().firstName(form.firstName));
const profileValid = computed(
  () => !busy.value && !avatarBusy.value && !nameError.value && !useValidation().phone(form.phone),
);
const submitLabel = computed(() => (busy.value ? 'Активируем…' : 'Сохранить и активировать'));
const avatarPrompt = computed(() =>
  avatarBusy.value ? 'Сохраняем фото…' : 'Нажмите на кружок, чтобы выбрать фото.',
);
const lifetime = new AbortController();

function setImage(blob: Blob | null) {
  if (avatarImage.value) URL.revokeObjectURL(avatarImage.value);

  avatarImage.value = blob ? URL.createObjectURL(blob) : null;
}

async function readAvatar() {
  if (!profile.value?.hasAvatar) {
    setImage(null);

    return;
  }

  try {
    const response = await request(
      '/api/auth/invitation/avatar',
      { headers: { 'X-Invitation-Token': token.value }, signal: lifetime.signal },
      false,
    );

    setImage(await response.blob());
  } catch {
    setImage(null);
  }
}

async function loadInvitation(rawValue: unknown) {
  const value = typeof rawValue === 'string' && /^[a-f0-9]{64}$/.test(rawValue) ? rawValue : '';

  loading.value = false;
  token.value = value;
  profile.value = null;
  invalid.value = '';
  error.value = '';
  setImage(null);
  form.password = '';
  form.confirm = '';

  if (!value) {
    invalid.value = 'Ссылка приглашения недействительна. Попросите администратора отправить новую.';

    return;
  }

  loading.value = true;

  try {
    const result = await api<InvitationProfile>(
      '/api/auth/invitation',
      { method: 'POST', body: JSON.stringify({ token: value }), signal: lifetime.signal },
      false,
    );

    if (token.value !== value) return;

    profile.value = result;
    Object.assign(form, {
      firstName: result.firstName ?? '',
      lastName: result.lastName ?? '',
      phone: result.phone ?? '',
      position: result.position ?? '',
    });
    await readAvatar();
  } catch (e) {
    if (token.value === value) invalid.value = errorText(e);
  } finally {
    if (token.value === value) loading.value = false;
  }
}

onMounted(() => {
  void loadInvitation(route.query.token);
});

onBeforeRouteUpdate((to) => {
  void loadInvitation(to.query.token);
});

function generatePassword() {
  const password = Array.from(crypto.getRandomValues(new Uint8Array(16)), (value) =>
    value.toString(16).padStart(2, '0'),
  ).join('');

  form.password = password;
  form.confirm = password;
  visible.value = true;
}

async function chooseAvatar(event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];

  input.value = '';

  if (!file || avatarBusy.value) return;

  error.value = '';
  avatarMessage.value = '';

  if (
    !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ||
    file.size > 2 * 1024 * 1024
  ) {
    error.value = 'Выберите JPEG, PNG или WebP размером не более 2 МБ.';

    return;
  }

  avatarBusy.value = true;
  setImage(file);

  try {
    const body = new FormData();

    body.append('avatar', file);
    await api(
      '/api/auth/invitation/avatar',
      {
        method: 'POST',
        body,
        headers: { 'X-Invitation-Token': token.value },
        signal: lifetime.signal,
      },
      false,
    );

    if (profile.value) profile.value.hasAvatar = true;

    avatarMessage.value = 'Фото сохранено.';
  } catch (e) {
    error.value = errorText(e);
    await readAvatar();
  } finally {
    avatarBusy.value = false;
  }
}

async function deleteAvatar() {
  avatarBusy.value = true;
  error.value = '';
  avatarMessage.value = '';

  try {
    await api(
      '/api/auth/invitation/avatar',
      { method: 'DELETE', headers: { 'X-Invitation-Token': token.value }, signal: lifetime.signal },
      false,
    );

    if (profile.value) profile.value.hasAvatar = false;

    setImage(null);
    avatarMessage.value = 'Фото удалено.';
  } catch (e) {
    error.value = errorText(e);
  } finally {
    avatarBusy.value = false;
  }
}

const passwordType = computed(() => (visible.value ? 'text' : 'password'));
const visibilityLabel = computed(() => (visible.value ? 'Скрыть' : 'Показать'));

async function submit() {
  if (!profileValid.value) return;

  error.value = '';

  if (form.password !== form.confirm) {
    error.value = 'Пароли не совпадают.';

    return;
  }

  busy.value = true;

  try {
    await api(
      '/api/auth/activate',
      {
        method: 'POST',
        body: JSON.stringify({
          token: token.value,
          firstName: form.firstName.trim(),
          lastName: form.lastName.trim() || null,
          phone: form.phone || null,
          position: form.position || null,
          password: form.password,
        }),
        signal: lifetime.signal,
      },
      false,
    );
    form.password = '';
    form.confirm = '';
    auth.clear();
    await router.replace('/login?activated=1');
  } catch (e) {
    error.value = errorText(e);
  } finally {
    busy.value = false;
  }
}

onBeforeUnmount(() => {
  lifetime.abort();
  setImage(null);
  form.password = '';
  form.confirm = '';
});
</script>

<template>
  <AuthCard
    title="Активация учётной записи"
    description="Проверьте данные и задайте пароль для входа."
  >
    <p
      v-if="loading"
      class="text-secondary"
      role="status"
    >
      Проверяем приглашение…
    </p>
    <div
      v-if="invalid"
      class="alert alert-warning"
      role="alert"
    >
      {{ invalid }}
    </div>
    <div
      v-if="error"
      class="alert alert-danger"
      role="alert"
    >
      {{ error }}
    </div>
    <form
      v-if="profile && !loading"
      @submit.prevent="submit"
    >
      <fieldset
        class="border-0 p-0 m-0"
        :disabled="busy || avatarBusy"
      >
        <div class="avatar-editor mb-3">
          <input
            ref="avatarInput"
            type="file"
            hidden
            accept="image/jpeg,image/png,image/webp"
            @change="chooseAvatar"
            @cancel.stop
          />
          <button
            type="button"
            class="avatar-picker"
            aria-label="Выбрать фото профиля"
            @click="avatarInput?.click()"
          >
            <UserAvatar
              :url="null"
              :first-name="form.firstName"
              :last-name="form.lastName"
              :preview="avatarImage"
            />
          </button>
          <div class="small text-secondary">
            {{ avatarPrompt }}
            <div>
              Фото сохраняется сразу, до 2 МБ.
            </div>
            <button
              v-if="profile.hasAvatar"
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
          v-if="avatarMessage"
          class="small text-success"
          role="status"
        >
          {{ avatarMessage }}
        </p>
        <label
          class="form-label"
          for="activation-email"
        >
          Электронная почта
        </label>
        <EmailInput
          id="activation-email"
          :model-value="profile.email"
          class="mb-3"
          readonly
          autocomplete="username"
        />
        <label
          class="form-label"
          for="activation-first-name"
        >
          Имя
        </label>
        <input
          id="activation-first-name"
          v-model="form.firstName"
          class="form-control"
          :class="{ 'is-invalid': !!nameError }"
          :aria-invalid="!!nameError"
          aria-describedby="activation-name-error"
          required
          maxlength="100"
          autocomplete="given-name"
        />
        <div
          v-if="nameError"
          id="activation-name-error"
          class="invalid-feedback d-block"
        >
          {{ nameError }}
        </div>
        <label
          class="form-label"
          for="activation-last-name"
        >
          Фамилия
        </label>
        <input
          id="activation-last-name"
          v-model="form.lastName"
          class="form-control mb-3"
          maxlength="100"
          autocomplete="family-name"
        />
        <label
          class="form-label"
          for="activation-phone"
        >
          Телефон
        </label>
        <PhoneInput
          id="activation-phone"
          v-model="form.phone"
          class="mb-3"
          autocomplete="tel"
        />
        <label
          class="form-label"
          for="activation-position"
        >
          Должность
        </label>
        <input
          id="activation-position"
          v-model="form.position"
          class="form-control mb-3"
          maxlength="160"
        />
        <label
          class="form-label"
          for="activation-password"
        >
          Пароль
        </label>
        <div class="input-group mb-2">
          <input
            id="activation-password"
            v-model="form.password"
            :type="passwordType"
            class="form-control"
            required
            minlength="10"
            maxlength="4096"
            autocomplete="new-password"
          />
          <button
            type="button"
            class="btn"
            :aria-pressed="visible"
            @click="visible = !visible"
          >
            {{ visibilityLabel }}
          </button>
        </div>
        <button
          type="button"
          class="btn btn-sm mb-3"
          @click="generatePassword"
        >
          Сгенерировать пароль
        </button>
        <label
          class="form-label"
          for="activation-confirm"
        >
          Повторите пароль
        </label>
        <input
          id="activation-confirm"
          v-model="form.confirm"
          :type="passwordType"
          class="form-control mb-4"
          required
          minlength="10"
          maxlength="4096"
          autocomplete="new-password"
        />
        <button
          class="btn btn-primary w-100"
          :disabled="!profileValid"
        >
          {{ submitLabel }}
        </button>
      </fieldset>
    </form>
    <RouterLink
      class="d-block text-center mt-3"
      to="/login"
    >
      Вернуться ко входу
    </RouterLink>
  </AuthCard>
</template>
