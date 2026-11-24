<script setup lang="ts">
import { RouterLink, useRoute } from 'vue-router';
import { computed } from 'vue';
import UnityProviderContractsTable from '../components/UnityProviderContractsTable.vue';

const route = useRoute();
const unityProviderId = computed(() => Number(route.query.unityProviderId) || undefined);
const objectRoute = computed(() =>
  objectId.value ? '/objects/' + objectId.value + '?tab=utilities' : '/objects',
);
const objectId = computed(() => Number(route.query.objectId) || undefined);
</script>

<template>
  <div class="page-heading">
    <h1>
      Договоры коммунальных поставщиков
    </h1>
    <RouterLink
      :to="objectRoute"
      class="btn"
    >
      К объекту
    </RouterLink>
  </div>
  <section class="card">
    <UnityProviderContractsTable
      :object-id="objectId"
      :unity-provider-id="unityProviderId"
    />
  </section>
</template>
