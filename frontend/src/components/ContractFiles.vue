<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import DataTable from './ui/DataTable.vue';
import DeleteConfirmModal from './ui/DeleteConfirmModal.vue';
import { useAuthStore } from '../store/auth';
import useApi from '../use/api';
import type { ContractFile, ContractFileList } from '../types/models/ContractFile';
import type { ContractFilesProps, ContractFileRow } from '../types/components/ContractFiles';
import type { TableColumn } from '../types/components/ui/DataTable';

const props = defineProps<ContractFilesProps>();
const auth = useAuthStore();
const canManage = computed(() => auth.canManageRentalRegistry && !props.readonly);
const { api, request, errorText } = useApi();
const files = ref<ContractFile[]>([]);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const pendingDelete = ref<ContractFile | null>(null);
const deleteError = ref('');
const input = ref<HTMLInputElement | null>(null);
const rows = computed<ContractFileRow[]>(() =>
  files.value.map((file) => ({ ...file, sizeLabel: (file.sizeBytes / 1024).toFixed(1) + ' КБ' })),
);
const columns: TableColumn<ContractFileRow>[] = [
  { key: 'name', label: 'Файл' },
  { key: 'sizeLabel', label: 'Размер' },
  { key: 'actions', label: 'Действия' },
];
let controller: AbortController | undefined;

async function load() {
  controller?.abort();
  const current = new AbortController();

  controller = current;
  loading.value = true;

  try {
    const result = await api<ContractFileList>('/api/contracts/' + props.contractId + '/files', {
      signal: current.signal,
    });

    if (!current.signal.aborted) files.value = result.items;
  } catch (e) {
    if (!current.signal.aborted) error.value = errorText(e);
  } finally {
    if (!current.signal.aborted) loading.value = false;
  }
}

function chooseFiles() {
  if (!canManage.value) return;

  input.value?.click();
}

async function upload(event: Event) {
  if (!canManage.value) return;

  const element = event.target as HTMLInputElement;
  const selected = Array.from(element.files ?? []);

  element.value = '';

  if (!selected.length) return;

  if (selected.some((file) => file.size > 20 * 1024 * 1024)) {
    error.value = 'Размер каждого файла не должен превышать 20 МБ.';

    return;
  }

  saving.value = true;
  error.value = '';

  try {
    for (const file of selected) {
      const body = new FormData();

      body.append('file', file);
      await api('/api/contracts/' + props.contractId + '/files', { method: 'POST', body });
    }
  } catch (e) {
    error.value = errorText(e);
  } finally {
    await load();
    saving.value = false;
  }
}

async function download(file: ContractFile) {
  error.value = '';

  try {
    const response = await request('/api/contract-files/' + file.id);
    const url = URL.createObjectURL(await response.blob());
    const link = document.createElement('a');

    link.href = url;
    link.download = file.name;
    link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  } catch (e) {
    error.value = errorText(e);
  }
}

function requestDelete(file: ContractFile) {
  pendingDelete.value = file;
  deleteError.value = '';
}

async function remove() {
  const file = pendingDelete.value;

  if (!file || saving.value || !canManage.value) return;

  saving.value = true;
  deleteError.value = '';

  try {
    await api('/api/contract-files/' + file.id, { method: 'DELETE' });
    files.value = files.value.filter((entry) => entry.id !== file.id);
    pendingDelete.value = null;
    await load();
  } catch (e) {
    deleteError.value = errorText(e);
  } finally {
    saving.value = false;
  }
}

onMounted(load);
onBeforeUnmount(() => controller?.abort());
defineExpose({ saving });
</script>

<template>
  <div
    v-if="error"
    class="alert alert-danger m-3"
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
  <DataTable
    :rows="rows"
    :columns="columns"
    caption="Файлы договора"
    :loading="loading"
    :error="!!error"
    empty-text="Файлы пока не прикреплены."
  >
    <template
      v-if="canManage"
      #actions
    >
      <input
        ref="input"
        type="file"
        multiple
        class="d-none"
        aria-label="Выберите файлы договора"
        :disabled="saving"
        @change="upload"
      />
      <button
        type="button"
        class="btn"
        :disabled="saving"
        @click="chooseFiles"
      >
        Добавить файлы
      </button>
    </template>
    <template #cell="{ row, column }">
      <template v-if="column.key === 'name'">
        {{ row.name }}
      </template>
      <template v-else-if="column.key === 'sizeLabel'">
        {{ row.sizeLabel }}
      </template>
      <div
        v-else-if="column.key === 'actions'"
        class="d-flex flex-wrap gap-2"
      >
        <button
          type="button"
          class="btn btn-sm"
          :disabled="saving"
          @click.stop="download(row)"
        >
          Скачать
        </button>
        <button
          v-if="canManage"
          type="button"
          class="btn btn-sm text-danger"
          :disabled="saving"
          @click.stop="requestDelete(row)"
        >
          Удалить
        </button>
      </div>
    </template>
  </DataTable>
  <DeleteConfirmModal
    v-if="pendingDelete"
    title="Удалить файл?"
    :item-name="pendingDelete.name"
    :busy="saving"
    :error="deleteError"
    @confirm="remove"
    @cancel="pendingDelete = null"
  />
</template>
