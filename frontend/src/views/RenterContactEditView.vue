<script setup lang="ts">
import FormBackButton from '../components/ui/FormBackButton.vue';
import useCreateNavigation from '../use/createNavigation';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { RouterLink, onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import TextInput from '../components/ui/TextInput.vue';
import EmailInput from '../components/ui/EmailInput.vue';
import PhoneInput from '../components/ui/PhoneInput.vue';
import useApi, { ApiError } from '../use/api';
import useRenterContactValidation from '../use/renterContactValidation';
import { useAuthStore } from '../store/auth';
import type { Renter } from '../types/models/Renter';
import type { RenterContact } from '../types/models/RenterContact';
import type { RenterContactDraft } from '../types/forms/RenterContact';
import type { ApiErrors } from '../types/use/Api';

const { returnAfterCreate, returnAfterSave } = useCreateNavigation();
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const { api, errorText } = useApi();
const validation = useRenterContactValidation();
const creating = computed(() => !route.params.id);
const renter = ref<Renter>();
const record = ref<RenterContact>();
const loading = ref(true);
const saving = ref(false);
const loadError = ref('');
const error = ref('');
const notice = ref('');
const errors = ref<ApiErrors>({});
const form = reactive<RenterContactDraft>({
  name: '',
  lastName: '',
  patronymic: '',
  phone: '',
  email: '',
});
const backRoute = computed(() => ({
  path: '/renters/' + route.params.renterId,
  query: { tab: 'contacts' },
}));
const title = computed(() =>
  creating.value
    ? 'Добавить контакт'
    : [record.value?.name, record.value?.lastName, record.value?.patronymic]
        .filter(Boolean)
        .join(' ') || 'Загрузка…',
);
const payload = computed(() => ({
  name: form.name.trim(),
  lastName: form.lastName.trim() || null,
  patronymic: form.patronymic.trim() || null,
  phone: form.phone || null,
  email: form.email.trim().toLowerCase() || null,
}));
const original = ref('');
const validationErrors = computed(() => validation.contact(form));
const disabled = computed(() => !auth.user || saving.value);
const emailError = computed(() => errors.value.email || validationErrors.value.email);
const phoneError = computed(() => errors.value.phone || validationErrors.value.phone);
const canSave = computed(
  () =>
    !disabled.value &&
    !loading.value &&
    JSON.stringify(payload.value) !== original.value &&
    !Object.keys(validationErrors.value).length &&
    !Object.keys(errors.value).length,
);
const saveLabel = computed(() =>
  saving.value ? 'Сохраняем…' : creating.value ? 'Создать' : 'Сохранить',
);
let controller: AbortController | undefined;

function clearErrors() {
  errors.value = {};
  notice.value = '';
}

async function load() {
  controller?.abort();
  const current = new AbortController();

  controller = current;
  loading.value = true;
  loadError.value = '';

  try {
    const owner = await api<Renter>('/api/renters/' + route.params.renterId, {
      signal: current.signal,
    });

    if (current.signal.aborted) return;

    renter.value = owner;

    if (!creating.value) {
      const result = await api<RenterContact>(
        '/api/renters/' + route.params.renterId + '/contacts/' + route.params.id,
        { signal: current.signal },
      );

      if (current.signal.aborted) return;

      record.value = result;
      Object.assign(form, {
        name: result.name,
        lastName: result.lastName ?? '',
        patronymic: result.patronymic ?? '',
        phone: result.phone ?? '',
        email: result.email ?? '',
      });
    }

    original.value = JSON.stringify(payload.value);
  } catch (e) {
    if (!current.signal.aborted) loadError.value = errorText(e);
  } finally {
    if (!current.signal.aborted) loading.value = false;
  }
}

async function save() {
  if (!canSave.value) return;

  saving.value = true;
  error.value = '';

  try {
    const result = await api<RenterContact>(
      '/api/renters/' +
        route.params.renterId +
        '/contacts' +
        (creating.value ? '' : '/' + route.params.id),
      {
        method: creating.value ? 'POST' : 'PUT',
        body: JSON.stringify(payload.value),
      },
    );

    record.value = result;
    original.value = JSON.stringify(payload.value);
    notice.value = 'Изменения сохранены.';
    saving.value = false;

    if (creating.value)
      await returnAfterCreate('/renters/' + route.params.renterId + '?tab=contacts');
    else await returnAfterSave();
  } catch (e) {
    error.value = errorText(e);

    if (e instanceof ApiError) errors.value = e.errors;
  } finally {
    saving.value = false;
  }
}

onMounted(load);
onBeforeUnmount(() => controller?.abort());
onBeforeRouteLeave(() => !auth.user || !saving.value);
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>
        {{ title }}
      </h1>
    </div>
    <RouterLink
      :to="backRoute"
      class="btn"
    >
      К контактам
    </RouterLink>
  </div>
  <p
    v-if="loading"
    role="status"
  >
    Загрузка…
  </p>
  <div
    v-else-if="loadError"
    class="alert alert-danger"
    role="alert"
  >
    {{ loadError }}
    <button
      class="btn ms-2"
      @click="load"
    >
      Повторить
    </button>
  </div>
  <section
    v-else
    class="card"
  >
    <form
      novalidate
      @submit.prevent="save"
      @input="clearErrors"
      @change="clearErrors"
    >
      <div class="card-body">
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
        <div class="row g-3">
          <div class="col-12 col-md-4">
            <TextInput
              id="contact-name"
              v-model="form.name"
              label="Имя"
              required
              :readonly="disabled"
              :error="errors.name"
              :validation-error="validationErrors.name"
            />
          </div>
          <div class="col-12 col-md-4">
            <TextInput
              id="contact-last-name"
              v-model="form.lastName"
              label="Фамилия"
              :readonly="disabled"
              :error="errors.lastName"
            />
          </div>
          <div class="col-12 col-md-4">
            <TextInput
              id="contact-patronymic"
              v-model="form.patronymic"
              label="Отчество"
              :readonly="disabled"
              :error="errors.patronymic"
            />
          </div>
          <div class="col-12 col-md-6 col-lg-4">
            <label
              for="contact-phone"
              class="form-label"
            >
              Телефон
            </label>
            <PhoneInput
              id="contact-phone"
              v-model="form.phone"
              :readonly="disabled"
              :error="phoneError"
            />
          </div>
          <div class="col-12 col-md-6 col-lg-4">
            <label
              for="contact-email"
              class="form-label"
            >
              Email
            </label>
            <EmailInput
              optional
              id="contact-email"
              v-model="form.email"
              :readonly="disabled"
              :error="emailError"
            />
          </div>
        </div>
        <div
          v-if="auth.user"
          class="d-flex flex-wrap gap-2 mt-3"
        >
          <button
            type="submit"
            class="btn btn-primary"
            :disabled="!canSave"
          >
            {{ saveLabel }}
          </button>
          <FormBackButton :to="backRoute" />
        </div>
      </div>
    </form>
  </section>
</template>
