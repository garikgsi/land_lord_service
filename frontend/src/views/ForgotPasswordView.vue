<script setup lang="ts">
import EmailInput from '../components/ui/EmailInput.vue';
import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import AuthCard from '../components/AuthCard.vue';
import useApi from '../use/api';
import type { MessageResponse } from '../types/use/Api';

const { api, errorText } = useApi();
const email = ref('');
const busy = ref(false);
const error = ref('');
const message = ref('');

const submitLabel = computed(() => (busy.value ? 'Отправляем…' : 'Отправить ссылку'));

async function submit() {
  busy.value = true;
  error.value = '';
  message.value = '';

  try {
    const result = await api<MessageResponse>(
      '/api/auth/forgot-password',
      { method: 'POST', body: JSON.stringify({ email: email.value.trim().toLowerCase() }) },
      false,
    );

    message.value = result.message;
  } catch (e) {
    error.value = errorText(e);
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <AuthCard
    title="Восстановление пароля"
    description="Отправим ссылку на почту вашей учётной записи."
  >
    <div
      v-if="error"
      class="alert alert-danger"
      role="alert"
    >
      {{ error }}
    </div>
    <div
      v-if="message"
      class="alert alert-success"
      role="status"
    >
      {{ message }}
    </div>
    <form @submit.prevent="submit">
      <label
        class="form-label"
        for="recovery-email"
      >
        Электронная почта
      </label>
      <EmailInput
        id="recovery-email"
        v-model="email"
        autocomplete="email"
        class="mb-4"
        required
        maxlength="254"
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
      to="/login"
    >
      Вернуться ко входу
    </RouterLink>
  </AuthCard>
</template>
