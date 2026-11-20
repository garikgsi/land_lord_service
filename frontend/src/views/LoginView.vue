<script setup lang="ts">
import EmailInput from '../components/ui/EmailInput.vue';
import { computed, ref } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import AuthCard from '../components/AuthCard.vue';
import { useAuthStore } from '../store/auth';
import useApi from '../use/api';

const auth = useAuthStore();

const { errorText } = useApi();
const email = ref('');
const password = ref('');
const visible = ref(false);
const busy = ref(false);
const error = ref('');
const route = useRoute();
const router = useRouter();

const submitLabel = computed(() => (busy.value ? 'Входим…' : 'Войти'));

const passwordType = computed(() => (visible.value ? 'text' : 'password'));
const visibilityLabel = computed(() => (visible.value ? 'Скрыть' : 'Показать'));

async function submit() {
  busy.value = true;
  error.value = '';

  try {
    await auth.login(email.value, password.value);
    password.value = '';
    const next = route.query.redirect;

    await router.replace(
      typeof next === 'string' &&
        next.startsWith('/') &&
        !next.startsWith('//') &&
        !/^\/(login|forgot-password|reset-password|activate)(\/|$)/.test(next)
        ? next
        : '/',
    );
  } catch (e) {
    error.value = errorText(e);
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <AuthCard
    title="Вход в систему"
    description="Введите рабочую почту и пароль."
  >
    <div
      v-if="route.query.activated === '1'"
      class="alert alert-success"
      role="status"
    >
      Учётная запись активирована. Войдите с новым паролем.
    </div>
    <div
      v-if="route.query.reset === '1'"
      class="alert alert-success"
      role="status"
    >
      Пароль изменён. Войдите с новым паролем.
    </div>
    <div
      v-if="route.query.expired === '1'"
      class="alert alert-info"
      role="status"
    >
      Сеанс завершён. Войдите снова.
    </div>
    <div
      v-if="error"
      class="alert alert-danger"
      role="alert"
    >
      {{ error }}
    </div>
    <form @submit.prevent="submit">
      <label
        class="form-label"
        for="login-email"
      >
        Электронная почта
      </label>
      <EmailInput
        id="login-email"
        v-model="email"
        class="mb-3"
        autocomplete="username"
        required
        maxlength="254"
        :disabled="busy"
      />
      <label
        class="form-label"
        for="login-password"
      >
        Пароль
      </label>
      <div class="input-group mb-2">
        <input
          id="login-password"
          v-model="password"
          :type="passwordType"
          class="form-control"
          autocomplete="current-password"
          required
          maxlength="4096"
          :disabled="busy"
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
      <RouterLink
        class="d-inline-block mb-4"
        to="/forgot-password"
      >
        Забыли пароль?
      </RouterLink>
      <button
        class="btn btn-primary w-100"
        :disabled="busy"
      >
        {{ submitLabel }}
      </button>
    </form>
    <p class="text-secondary small mt-3 mb-0">
      Учётную запись создаёт администратор системы.
    </p>
  </AuthCard>
</template>
