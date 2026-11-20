<script setup lang="ts">
import { computed, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AuthCard from '../components/AuthCard.vue';
import useApi from '../use/api';
import { useAuthStore } from '../store/auth';

const auth = useAuthStore();

const { api, errorText } = useApi();
const route = useRoute();
const router = useRouter();
const token = computed(() =>
  typeof route.query.token === 'string' && /^[a-f0-9]{64}$/.test(route.query.token)
    ? route.query.token
    : null,
);
const password = ref('');
const confirm = ref('');
const busy = ref(false);
const error = ref('');

const submitLabel = computed(() => (busy.value ? 'Сохраняем…' : 'Сохранить пароль'));

async function submit() {
  error.value = '';

  if (password.value !== confirm.value) {
    error.value = 'Пароли не совпадают.';

    return;
  }

  busy.value = true;

  try {
    await api(
      '/api/auth/reset-password',
      { method: 'POST', body: JSON.stringify({ token: token.value, password: password.value }) },
      false,
    );
    auth.clear();
    password.value = '';
    confirm.value = '';
    await router.replace('/login?reset=1');
  } catch (e) {
    error.value = errorText(e);
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <AuthCard
    title="Новый пароль"
    description="Задайте пароль длиной от 10 символов."
  >
    <div
      v-if="!token"
      class="alert alert-warning"
      role="alert"
    >
      Ссылка восстановления недействительна. Запросите новую.
    </div>
    <div
      v-if="error"
      class="alert alert-danger"
      role="alert"
    >
      {{ error }}
    </div>
    <form
      v-if="token"
      @submit.prevent="submit"
    >
      <label
        class="form-label"
        for="reset-password"
      >
        Новый пароль
      </label>
      <input
        id="reset-password"
        v-model="password"
        type="password"
        autocomplete="new-password"
        class="form-control mb-3"
        minlength="10"
        maxlength="4096"
        required
        :disabled="busy"
      />
      <label
        class="form-label"
        for="confirm-password"
      >
        Повторите пароль
      </label>
      <input
        id="confirm-password"
        v-model="confirm"
        type="password"
        autocomplete="new-password"
        class="form-control mb-4"
        minlength="10"
        maxlength="4096"
        required
        :disabled="busy"
      />
      <button
        class="btn btn-primary w-100"
        :disabled="busy"
      >
        {{ submitLabel }}
      </button>
    </form>
    <RouterLink
      class="d-block text-center mt-3"
      to="/forgot-password"
    >
      Запросить новую ссылку
    </RouterLink>
  </AuthCard>
</template>
