<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import { IconBuildingEstate, IconMenu2, IconMoon, IconSearch, IconX } from '@tabler/icons-vue';
import TomSelect from 'tom-select';
import { sections } from './router';

const route = useRoute();
const router = useRouter();
const sidebarOpen = ref(false);
const sidebarElement = ref<HTMLElement | null>(null);
const sidebarToggle = ref<HTMLButtonElement | null>(null);
const searchElement = ref<HTMLSelectElement | null>(null);
const connection = ref<'connecting' | 'ready' | 'error'>('connecting');
const currentSection = computed(() => sections.find((section) => section.path === route.path));
let searchControl: TomSelect | undefined;
const desktopLayout = window.matchMedia('(min-width: 992px)');
const closeDesktopNavigation = (event: MediaQueryListEvent) => {
  if (event.matches) sidebarOpen.value = false;
};

async function checkConnection() {
  connection.value = 'connecting';
  try {
    const response = await fetch(
      `${(import.meta.env.VITE_API_BASE_URL ?? '').replace(/\/$/, '')}/api/healthz`,
      {
        signal: AbortSignal.timeout(5000),
        cache: 'no-store',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      },
    );
    const body: unknown = await response.json();
    connection.value = response.ok && typeof body === 'object' && body !== null
      && 'status' in body && body.status === 'ok' ? 'ready' : 'error';
  } catch {
    connection.value = 'error';
  }
}

watch(() => route.path, () => { sidebarOpen.value = false; });
watch(sidebarOpen, async (open) => {
  document.body.classList.toggle('navigation-open', open);
  await nextTick();
  if (open) sidebarElement.value?.querySelector<HTMLAnchorElement>('nav a')?.focus();
  else if (!desktopLayout.matches) sidebarToggle.value?.focus();
});

onMounted(() => {
  desktopLayout.addEventListener('change', closeDesktopNavigation);
  if (searchElement.value) {
    searchControl = new TomSelect(searchElement.value, {
      options: sections.map((section) => ({ value: section.path, text: section.title })),
      placeholder: 'Найти раздел',
      maxItems: 1,
      create: false,
      onChange: (value: string | string[]) => {
        if (typeof value === 'string' && value) {
          void router.push(value);
          searchControl?.clear(true);
          searchControl?.blur();
        }
      },
    });
    searchElement.value.setAttribute('aria-hidden', 'true');
    searchControl.control_input.setAttribute('aria-label', 'Найти раздел');
  }
  void checkConnection();
});

onBeforeUnmount(() => {
  desktopLayout.removeEventListener('change', closeDesktopNavigation);
  searchControl?.destroy();
  document.body.classList.remove('navigation-open');
});
</script>

<template>
  <a class="skip-link" href="#main-content">Перейти к содержимому</a>
  <div class="workspace" @keydown.esc="sidebarOpen = false">
    <button v-if="sidebarOpen" class="sidebar-backdrop" aria-label="Закрыть меню" @click="sidebarOpen = false"></button>
    <aside id="workspace-navigation" ref="sidebarElement" class="workspace-sidebar" :class="{ 'is-open': sidebarOpen }" aria-label="Навигация">
      <RouterLink class="workspace-brand" to="/" @click="sidebarOpen = false">
        <span class="brand-symbol"><IconBuildingEstate :size="25" stroke="1.7" /></span>
        <span>LandLord<span class="brand-service">Service</span></span>
      </RouterLink>
      <div class="sidebar-label">Рабочее пространство</div>
      <nav class="workspace-nav">
        <RouterLink v-for="section in sections" :key="section.path" :to="section.path"
          class="workspace-nav-link" :class="{ active: route.path === section.path }"
          :aria-current="route.path === section.path ? 'page' : undefined">
          <component :is="section.icon" :size="21" stroke="1.7" aria-hidden="true" />
          <span>{{ section.title }}</span>
        </RouterLink>
      </nav>
      <div class="sidebar-footer">
        <div class="sidebar-footer-mark"><IconBuildingEstate :size="19" stroke="1.5" /></div>
        <div>Управление недвижимостью<small>Аренда и эксплуатация</small></div>
      </div>
    </aside>

    <div class="workspace-body" :inert="sidebarOpen">
      <header class="workspace-header">
        <div class="d-flex align-items-center gap-3">
          <button ref="sidebarToggle" class="btn btn-icon navigation-toggle" aria-label="Открыть меню"
            aria-controls="workspace-navigation" :aria-expanded="sidebarOpen" @click="sidebarOpen = !sidebarOpen">
            <IconX v-if="sidebarOpen" :size="22" /><IconMenu2 v-else :size="22" />
          </button>
          <div class="header-breadcrumb">Рабочее пространство<span>/</span><strong>{{ currentSection?.title ?? 'Страница не найдена' }}</strong></div>
        </div>
        <div class="d-flex align-items-center gap-3">
          <div class="navigation-search"><IconSearch :size="18" stroke="1.7" /><select ref="searchElement" aria-label="Найти раздел"><option value=""></option></select></div>
          <button class="btn btn-icon theme-toggle" data-theme-toggle aria-label="Тёмная тема" aria-pressed="false" title="Переключить тему">
            <IconMoon :size="21" stroke="1.7" />
          </button>
        </div>
      </header>

      <main id="main-content" class="workspace-content" tabindex="-1">
        <div v-if="connection === 'error'" class="alert alert-warning connection-alert" role="alert">
          <span>Не удалось связаться с сервером.</span>
          <button class="btn btn-sm" @click="checkConnection">Повторить</button>
        </div>
        <div class="prototype-notice"><span class="badge bg-blue-lt">Начальный интерфейс</span><span>Рабочие данные пока не подключены</span></div>
        <RouterView />
      </main>

      <footer class="workspace-footer">
        <span>LandLordService</span>
        <span class="connection-label" role="status">
          <span class="connection-dot" :class="connection" aria-hidden="true"></span>
          {{ connection === 'ready' ? 'Подключение установлено' : connection === 'error' ? 'Нет подключения' : 'Подключение…' }}
        </span>
      </footer>
    </div>
  </div>
</template>
