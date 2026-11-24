<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { sections } from '../router';

const route = useRoute();
const section = computed(() => sections.find((item) => item.path === route.path));
const descriptions: Record<string, string> = {
  '/objects': 'Объекты недвижимости, здания, помещения и коммуникации.',
  '/contracts': 'Реестр договоров по объектам и датам окончания.',
  '/meters': 'Приборы учёта, передача показаний и потребление ресурсов.',
  '/payments': 'Начисления аренды, поступления и сальдо по договорам.',
};
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>
        {{ section?.title }}
      </h1>
      <p>
        {{ descriptions[route.path] }}
      </p>
    </div>
  </div>
  <section class="card register-empty">
    <div class="empty-icon-circle">
      <component
        :is="section?.icon"
        :size="34"
        stroke="1.4"
      />
    </div>
    <h2>
      Здесь будут ваши данные
    </h2>
    <p>
      Раздел подготовлен для дальнейшей работы.
    </p>
    <RouterLink
      to="/"
      class="btn"
    >
      Вернуться к обзору
    </RouterLink>
  </section>
</template>
