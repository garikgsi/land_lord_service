<script setup lang="ts">
import DataTable from '../components/ui/DataTable.vue';
import type { TableColumn } from '../types/components/ui/DataTable';
import type { ContractPreviewRow } from '../types/components/ContractPreviewRow';
import {
  IconBuildingEstate,
  IconCalendarMonth,
  IconCreditCard,
  IconFileText,
  IconGauge,
} from '@tabler/icons-vue';

const contractRows: ContractPreviewRow[] = [];
const contractColumns: TableColumn<ContractPreviewRow>[] = [
  { key: 'contractNum', label: 'Договор' },
  { key: 'objectName', label: 'Объект' },
  { key: 'renterName', label: 'Арендатор' },
  { key: 'expiryDate', label: 'Окончание' },
];

const summaries = [
  {
    title: 'Договоры аренды',
    description: 'Реестр по объектам',
    icon: IconFileText,
    path: '/contracts',
  },
  {
    title: 'Сроки договоров',
    description: 'Продление и повышение платы',
    icon: IconCalendarMonth,
    path: '/calendar',
  },
  {
    title: 'Показания',
    description: 'Учёт потребления ресурсов',
    icon: IconGauge,
    path: '/meters',
  },
  {
    title: 'Арендная плата',
    description: 'Начисления и поступления',
    icon: IconCreditCard,
    path: '/payments',
  },
];
</script>

<template>
  <div class="page-heading">
    <div>
      <div class="page-pretitle">
        Недвижимость
      </div>
      <h1>
        Обзор
      </h1>
      <p>
        Договоры, показания и платежи по вашим объектам.
      </p>
    </div>
    <RouterLink
      to="/calendar"
      class="btn"
    >
      <IconCalendarMonth :size="19" />
      Календарь
    </RouterLink>
  </div>

  <div class="summary-grid">
    <RouterLink
      v-for="summary in summaries"
      :key="summary.path"
      :to="summary.path"
      class="card summary-card"
    >
      <div class="summary-card-heading">
        <span>
          {{ summary.title }}
        </span>
        <span class="summary-icon">
          <component
            :is="summary.icon"
            :size="22"
            stroke="1.6"
          />
        </span>
      </div>
      <div class="summary-value">
        Нет данных
      </div>
      <div class="text-secondary">
        {{ summary.description }}
      </div>
    </RouterLink>
  </div>

  <div class="overview-grid">
    <section class="card overview-properties">
      <div class="card-header">
        <h2 class="card-title">
          Ваши объекты
        </h2>
        <RouterLink
          to="/objects"
          class="subtle-link"
        >
          Все объекты
        </RouterLink>
      </div>
      <div
        class="property-placeholder"
        aria-hidden="true"
      >
        <div class="building building-small">
          <i>
          </i>
          <i>
          </i>
          <i>
          </i>
          <i>
          </i>
          <i>
          </i>
          <i>
          </i>
        </div>
        <div class="building building-tall">
          <i>
          </i>
          <i>
          </i>
          <i>
          </i>
          <i>
          </i>
          <i>
          </i>
          <i>
          </i>
          <i>
          </i>
          <i>
          </i>
          <i>
          </i>
        </div>
        <div class="building building-wide">
          <i>
          </i>
          <i>
          </i>
          <i>
          </i>
          <i>
          </i>
          <i>
          </i>
          <i>
          </i>
        </div>
      </div>
      <div class="empty-description">
        <h3>
          Здесь будут ваши объекты
        </h3>
        <p>
          Здания, помещения и коммуникации
          <br class="d-none d-sm-inline" />
          в единой структуре недвижимости.
        </p>
        <RouterLink
          to="/objects"
          class="btn btn-primary"
        >
          <IconBuildingEstate :size="19" />
          Перейти к объектам
        </RouterLink>
      </div>
    </section>
    <section class="card upcoming-card">
      <div class="card-header">
        <h2 class="card-title">
          Ближайшие события
        </h2>
        <span class="badge bg-secondary-lt">
          Календарь
        </span>
      </div>
      <div class="upcoming-body">
        <div class="empty-icon-circle">
          <IconCalendarMonth
            :size="30"
            stroke="1.4"
          />
        </div>
        <h3>
          Событий пока нет
        </h3>
        <p>
          Здесь появятся сроки договоров
          <br />
          и передачи показаний.
        </p>
        <RouterLink
          to="/calendar"
          class="subtle-link"
        >
          Открыть календарь
        </RouterLink>
      </div>
      <div class="upcoming-footer">
        <IconCalendarMonth
          :size="18"
          stroke="1.5"
        />
        <span>
          Важные даты в одном месте
        </span>
      </div>
    </section>
  </div>

  <section class="card contracts-preview">
    <DataTable
      :rows="contractRows"
      :columns="contractColumns"
      caption="Предварительное представление реестра договоров"
    >
      <template #header>
        <div>
          <h2 class="card-title">
            Договоры аренды
          </h2>
          <div class="text-secondary mt-1">
            По объектам и датам окончания
          </div>
        </div>
        <RouterLink
          to="/contracts"
          class="subtle-link"
        >
          Открыть реестр
        </RouterLink>
      </template>
      <template #empty>
        <div class="register-placeholder">
          <IconFileText
            :size="23"
            stroke="1.5"
          />
          <span>
            Здесь появятся ваши договоры аренды
          </span>
        </div>
      </template>
    </DataTable>
  </section>
</template>
