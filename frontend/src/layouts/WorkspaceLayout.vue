<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import { IconBuildingEstate, IconMenu2, IconMoon, IconSearch, IconX } from '@tabler/icons-vue';
import TomSelect from 'tom-select';
import { useAuthStore } from '../store/auth';
import { useRolesStore } from '../store/roles';
import UserAvatar from '../components/UserAvatar.vue';
import { sections } from '../router';
import useApi from '../use/api';

const auth = useAuthStore();
const roles = useRolesStore();
const roleLoadError = ref('');

const { api, errorText } = useApi();

const visibleSections = computed(() =>
  sections
    .filter((section) => !section.admin || auth.isAdmin)
    .map((section) => ({
      ...section,
      active: currentSection.value?.path === section.path,
      ariaCurrent: currentSection.value?.path === section.path ? ('page' as const) : undefined,
    })),
);
const route = useRoute();
const router = useRouter();
const sidebarOpen = ref(false);
const sidebarElement = ref<HTMLElement | null>(null);
const sidebarToggle = ref<HTMLButtonElement | null>(null);
const searchElement = ref<HTMLSelectElement | null>(null);
const connection = ref<'connecting' | 'ready' | 'error'>('connecting');
const currentSection = computed(() =>
  sections.find(
    (section) =>
      section.path === route.path ||
      (section.path !== '/' && route.path.startsWith(section.path + '/')),
  ),
);
const connectionLabel = computed(
  () =>
    ({ ready: 'Подключение установлено', error: 'Нет подключения', connecting: 'Подключение…' })[
      connection.value
    ],
);
const breadcrumbTitle = computed(() =>
  route.path === '/profile'
    ? 'Мой профиль'
    : (currentSection.value?.title ?? 'Страница не найдена'),
);
let searchControl: TomSelect | undefined;
const desktopLayout = window.matchMedia('(min-width: 992px)');
const closeDesktopNavigation = (event: MediaQueryListEvent) => {
  if (event.matches) void setSidebarOpen(false);
};

async function loadRoles() {
  roleLoadError.value = '';

  try {
    await roles.load();
  } catch (error) {
    roleLoadError.value = errorText(error);
  }
}

async function signOut() {
  try {
    await auth.logout();
  } catch {
    /* Local session has already been cleared. */
  }

  await router.replace('/login');
}

async function checkConnection() {
  connection.value = 'connecting';

  try {
    const body = await api<unknown>('/api/healthz', { signal: AbortSignal.timeout(5000) }, false);

    connection.value =
      typeof body === 'object' && body !== null && 'status' in body && body.status === 'ok'
        ? 'ready'
        : 'error';
  } catch {
    connection.value = 'error';
  }
}

async function setSidebarOpen(open: boolean) {
  sidebarOpen.value = open;
  document.body.classList.toggle('navigation-open', open);
  await nextTick();

  if (open) sidebarElement.value?.querySelector<HTMLAnchorElement>('nav a')?.focus();
  else if (!desktopLayout.matches) sidebarToggle.value?.focus();
}

const removeNavigationListener = router.afterEach(() => {
  void setSidebarOpen(false);
});

onMounted(() => {
  desktopLayout.addEventListener('change', closeDesktopNavigation);

  if (searchElement.value) {
    searchControl = new TomSelect(searchElement.value, {
      options: visibleSections.value.map((section) => ({
        value: section.path,
        text: section.title,
      })),
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
  void loadRoles();
});

onBeforeUnmount(() => {
  removeNavigationListener();
  desktopLayout.removeEventListener('change', closeDesktopNavigation);
  searchControl?.destroy();
  document.body.classList.remove('navigation-open');
});
</script>

<template>
  <a
    class="skip-link"
    href="#main-content"
  >
    Перейти к содержимому
  </a>
  <div
    class="workspace"
    @keydown.esc="setSidebarOpen(false)"
  >
    <button
      v-if="sidebarOpen"
      class="sidebar-backdrop"
      aria-label="Закрыть меню"
      @click="setSidebarOpen(false)"
    >
    </button>
    <aside
      id="workspace-navigation"
      ref="sidebarElement"
      class="workspace-sidebar"
      :class="{ 'is-open': sidebarOpen }"
      aria-label="Навигация"
    >
      <RouterLink
        class="workspace-brand"
        to="/"
        @click="setSidebarOpen(false)"
      >
        <span class="brand-symbol">
          <IconBuildingEstate
            :size="25"
            stroke="1.7"
          />
        </span>
        <span>
          Land Lord
          <span class="brand-service">
            Service
          </span>
        </span>
      </RouterLink>
      <div class="sidebar-label">
        Рабочее пространство
      </div>
      <nav class="workspace-nav">
        <RouterLink
          v-for="section in visibleSections"
          :key="section.path"
          :to="section.path"
          class="workspace-nav-link"
          :class="{ active: section.active }"
          :aria-current="section.ariaCurrent"
        >
          <component
            :is="section.icon"
            :size="21"
            stroke="1.7"
            aria-hidden="true"
          />
          <span>
            {{ section.title }}
          </span>
        </RouterLink>
      </nav>
      <div
        class="sidebar-account"
        v-if="auth.user"
      >
        <RouterLink
          to="/profile"
          class="text-break"
        >
          {{ auth.user.email }}
        </RouterLink>
        <button
          class="btn w-100 mt-2"
          @click="signOut"
        >
          Выйти
        </button>
      </div>
      <div class="sidebar-footer">
        <div class="sidebar-footer-mark">
          <IconBuildingEstate
            :size="19"
            stroke="1.5"
          />
        </div>
        <div>
          Управление недвижимостью
          <small>
            Аренда и эксплуатация
          </small>
        </div>
      </div>
    </aside>

    <div
      class="workspace-body"
      :inert="sidebarOpen"
    >
      <header class="workspace-header">
        <div class="d-flex align-items-center gap-3">
          <button
            ref="sidebarToggle"
            class="btn btn-icon navigation-toggle"
            aria-label="Открыть меню"
            aria-controls="workspace-navigation"
            :aria-expanded="sidebarOpen"
            @click="setSidebarOpen(!sidebarOpen)"
          >
            <IconX
              v-if="sidebarOpen"
              :size="22"
            />
            <IconMenu2
              v-else
              :size="22"
            />
          </button>
          <div class="header-breadcrumb">
            Рабочее пространство
            <span>
              /
            </span>
            <strong>
              {{ breadcrumbTitle }}
            </strong>
          </div>
        </div>
        <div class="d-flex align-items-center gap-3">
          <div class="navigation-search">
            <IconSearch
              :size="18"
              stroke="1.7"
            />
            <select
              ref="searchElement"
              aria-label="Найти раздел"
            >
              <option value="">
              </option>
            </select>
          </div>
          <RouterLink
            v-if="auth.user"
            to="/profile"
            class="profile-link"
            aria-label="Мой профиль"
            :title="auth.user.email"
          >
            <UserAvatar
              :url="auth.user.avatarUrl"
              :first-name="auth.user.firstName"
              :last-name="auth.user.lastName"
            />
          </RouterLink>
          <button
            class="btn btn-icon theme-toggle"
            data-theme-toggle
            aria-label="Тёмная тема"
            aria-pressed="false"
            title="Переключить тему"
          >
            <IconMoon
              :size="21"
              stroke="1.7"
            />
          </button>
        </div>
      </header>

      <main
        id="main-content"
        class="workspace-content"
        tabindex="-1"
      >
        <div
          v-if="connection === 'error'"
          class="alert alert-warning connection-alert"
          role="alert"
        >
          <span>
            Не удалось связаться с сервером.
          </span>
          <button
            class="btn btn-sm"
            @click="checkConnection"
          >
            Повторить
          </button>
        </div>
        <div
          v-if="!route.path.startsWith('/admin') && route.path !== '/profile'"
          class="prototype-notice"
        >
          <span class="badge bg-blue-lt">
            Начальный интерфейс
          </span>
          <span>
            Рабочие данные пока не подключены
          </span>
        </div>
        <div
          v-if="roleLoadError"
          class="alert alert-danger"
          role="alert"
        >
          {{ roleLoadError }}
          <button
            class="btn btn-sm ms-2"
            @click="loadRoles"
          >
            Повторить загрузку ролей
          </button>
        </div>
        <RouterView v-else-if="roles.loaded" />
        <p
          v-else
          class="text-secondary"
          role="status"
        >
          Загрузка справочников…
        </p>
      </main>

      <footer class="workspace-footer">
        <span>
          Land Lord Service ·
          <a
            href="./help/users.html"
            target="_blank"
            rel="noopener"
          >
            Инструкция пользователя
          </a>
          <a
            class="text-success ms-2"
            href="./server/api"
            target="_blank"
            rel="noopener"
          >
            API v0.1
          </a>
        </span>
        <span
          class="connection-label"
          role="status"
        >
          <span
            class="connection-dot"
            :class="connection"
            aria-hidden="true"
          >
          </span>
          {{ connectionLabel }}
        </span>
      </footer>
    </div>
  </div>
</template>
