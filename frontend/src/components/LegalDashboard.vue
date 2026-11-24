<script setup lang="ts">
import { onMounted, ref } from 'vue';
import RentalContractsTable from './RentalContractsTable.vue';
import ContractDetailsModal from './ContractDetailsModal.vue';
import UnityProviderContractsTable from './UnityProviderContractsTable.vue';
import useApi from '../use/api';
import type { Contract } from '../types/models/Contract';
import type { RegistryList } from '../types/api/Registry';

const { api, errorText } = useApi();
const contracts = ref<Contract[]>([]);
const selectedContractId = ref<number | null>(null);
const page = ref(1);
const total = ref(0);
const loading = ref(false);
const error = ref('');

async function load(value = page.value) {
  page.value = value;
  loading.value = true;
  error.value = '';

  try {
    const result = await api<RegistryList<Contract>>(
      '/api/dashboard/legal/contracts?page=' + page.value,
    );

    contracts.value = result.items;
    total.value = result.total;
  } catch (e) {
    error.value = errorText(e);
  } finally {
    loading.value = false;
  }
}

onMounted(() => load());
</script>

<template>
  <section class="card mb-4">
    <div
      v-if="error"
      class="alert alert-danger m-3"
      role="alert"
    >
      {{ error }}
      <button
        class="btn ms-2"
        @click="load()"
      >
        Повторить
      </button>
    </div>
    <RentalContractsTable
      :items="contracts"
      :total="total"
      :page="page"
      :loading="loading"
      :error="!!error"
      caption="Действующие договоры аренды"
      show-actions
      preview
      @preview="selectedContractId = $event.id"
      @updated="load()"
      @page-change="load"
    />
  </section>
  <section class="card mb-4">
    <UnityProviderContractsTable dashboard />
  </section>
  <ContractDetailsModal
    v-if="selectedContractId !== null"
    kind="rental"
    :contract-id="selectedContractId"
    @close="selectedContractId = null"
  />
</template>
