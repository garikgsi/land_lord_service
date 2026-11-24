<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import useApi from '../use/api';
import { useNotificationsStore } from '../store/notifications';
import type { Notification } from '../types/models/Notification';

const route = useRoute();
const { api, errorText } = useApi();
const notifications = useNotificationsStore();
const record = ref<Notification>();
const loading = ref(true);
const error = ref('');
const readError = ref('');
const title = computed(() => record.value?.title ?? 'Уведомление');
const createdLabel = computed(() =>
  record.value ? new Date(record.value.createdAt).toLocaleString('ru-RU') : '',
);
const dueLabel = computed(() =>
  record.value?.dueDate
    ? new Date(record.value.dueDate + 'T00:00:00').toLocaleDateString('ru-RU')
    : '',
);
let controller: AbortController | undefined;

async function markRead() {
  if (!record.value) return;

  readError.value = '';

  try {
    record.value = await notifications.markRead(record.value.id);
  } catch (e) {
    readError.value = errorText(e);
  }
}

async function load() {
  controller?.abort();
  const current = new AbortController();

  controller = current;
  loading.value = true;
  error.value = '';

  try {
    const result = await api<Notification>('/api/notifications/' + route.params.id, {
      signal: current.signal,
    });

    if (current.signal.aborted) return;

    record.value = result;

    if (!result.readAt) await markRead();
  } catch (e) {
    if (!current.signal.aborted) error.value = errorText(e);
  } finally {
    if (!current.signal.aborted) loading.value = false;
  }
}

onMounted(load);
onBeforeUnmount(() => controller?.abort());
</script>

<template>
  <div class="page-heading d-block d-md-flex">
    <div>
      <h1>
        {{ title }}
      </h1>
    </div>
    <RouterLink
      to="/notifications"
      class="btn"
    >
      К списку
    </RouterLink>
  </div>
  <p
    v-if="loading"
    role="status"
  >
    Загрузка…
  </p>
  <div
    v-else-if="error"
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
  <section
    v-else-if="record"
    class="card"
  >
    <div class="card-body">
      <div
        v-if="readError"
        class="alert alert-danger"
        role="alert"
      >
        {{ readError }}
        <button
          class="btn ms-2"
          @click="markRead"
        >
          Отметить прочитанным
        </button>
      </div>
      <p class="text-secondary">
        Получено: {{ createdLabel }}
      </p>
      <p v-if="dueLabel">
        Дата исполнения: {{ dueLabel }}
      </p>
      <p class="notification-message">
        {{ record.message }}
      </p>
      <RouterLink
        v-if="record.sourcePath"
        :to="record.sourcePath"
        class="btn btn-primary"
      >
        Перейти к записи
      </RouterLink>
    </div>
  </section>
</template>
