<script setup lang="ts">
import useCreateNavigation from '../use/createNavigation';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { IconPlus } from '@tabler/icons-vue';
import RentalContractsTable from './RentalContractsTable.vue';
import { useAuthStore } from '../store/auth';
import useApi from '../use/api';
import type { Contract } from '../types/models/Contract';
import type { RegistryList } from '../types/api/Registry';
import type { RentalObjectContractsProps } from '../types/components/RentalObjectContracts';

const { fromTable } = useCreateNavigation();
const props = defineProps<RentalObjectContractsProps>();
const emit = defineEmits<{ updated: [] }>();
const auth = useAuthStore();
const { api, errorText } = useApi();
const items = ref<Contract[]>([]);
const page = ref(1);
const total = ref(0);
const loading = ref(false);
const error = ref('');
const createRoute = computed(() =>
  fromTable({
    path: '/contracts/new',
    query: { objectId: String(props.objectId) },
  }),
);
let controller: AbortController | undefined;

async function reload(value = page.value) {
  controller?.abort();
  const current = new AbortController();

  controller = current;
  page.value = value;
  loading.value = true;
  error.value = '';

  try {
    const result = await api<RegistryList<Contract>>(
      '/api/objects/' + props.objectId + '/contracts?page=' + page.value,
      { signal: current.signal },
    );

    if (current.signal.aborted) return;

    items.value = result.items;
    total.value = result.total;
  } catch (e) {
    if (!current.signal.aborted) error.value = errorText(e);
  } finally {
    if (!current.signal.aborted) loading.value = false;
  }
}

function updated() {
  emit('updated');
  void reload();
}

onMounted(() => reload());
onBeforeUnmount(() => controller?.abort());
defineExpose({ reload });
</script>

<template>
  <div
    v-if="error"
    class="alert alert-danger m-3"
    role="alert"
  >
    {{ error }}
    <button
      type="button"
      class="btn ms-2"
      @click="reload()"
    >
      Повторить
    </button>
  </div>
  <RentalContractsTable
    :items="items"
    :total="total"
    :page="page"
    :loading="loading"
    :error="!!error"
    :show-object="false"
    show-actions
    caption="Арендаторы объекта"
    @page-change="reload"
    @updated="updated"
  >
    <template
      v-if="auth.canManageRentalRegistry"
      #actions
    >
      <RouterLink
        :to="createRoute"
        class="btn btn-primary list-add-button"
        aria-label="Добавить договор"
        title="Добавить договор"
      >
        <IconPlus :size="18" />
        <span class="list-add-label">
          Добавить
        </span>
      </RouterLink>
    </template>
  </RentalContractsTable>
</template>
