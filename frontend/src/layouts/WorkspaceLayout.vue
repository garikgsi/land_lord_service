<script setup lang="ts">
import Badge from '../components/ui/Badge.vue';
import useEvent from '../use/event';
import useBrowserEvents from '../use/browserEvents';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import {
  IconBooks,
  IconBell,
  IconChevronDown,
  IconBuildingEstate,
  IconMenu2,
  IconMoon,
  IconSearch,
  IconX,
} from '@tabler/icons-vue';
import TomSelect from 'tom-select';
import { useAuthStore } from '../store/auth';
import { useRolesStore } from '../store/roles';
import { useNotificationsStore } from '../store/notifications';
import UserAvatar from '../components/UserAvatar.vue';
import { sections } from '../router';
import useApi from '../use/api';

const auth = useAuthStore();
const roles = useRolesStore();
const notifications = useNotificationsStore();
const roleLoadError = ref('');

const { api, errorText } = useApi();

const visibleSections = computed(() =>
  sections
    .filter(
      (section) =>
        (!section.admin || auth.isAdmin) &&
        (!section.notificationSettings || auth.canManageNotificationTypes),
    )
    .map((section) => ({
      ...section,
      active: currentSection.value?.path === section.path,
      ariaCurrent: currentSection.value?.path === section.path ? ('page' as const) : undefined,
      hasUnread: section.path === '/notifications' && notifications.unreadCount > 0,
    })),
);
const route = useRoute();
const router = useRouter();
const showPrototypeNotice = computed(
  () =>
    !route.meta.dashboard &&
    !route.meta.notificationSettings &&
    !route.meta.rentalRegistry &&
    !route.meta.notifications &&
    !route.meta.meterRegistry &&
    !route.path.startsWith('/admin') &&
    route.path !== '/profile' &&
    route.path !== '/calendar',
);
const viewKey = computed(() =>
  route.meta.rentalRegistry || route.meta.notifications || route.meta.meterRegistry
    ? route.path
    : undefined,
);
const sidebarOpen = ref(false);
const sidebarElement = ref<HTMLElement | null>(null);
const sidebarToggle = ref<HTMLButtonElement | null>(null);
const searchElement = ref<HTMLSelectElement | null>(null);
const connection = ref<'connecting' | 'ready' | 'error'>('connecting');
const currentSection = computed(
  () =>
    sections
      .filter(
        (section) =>
          section.path === route.path ||
          (section.path !== '/' && route.path.startsWith(section.path + '/')),
      )
      .sort((left, right) => right.path.length - left.path.length)[0],
);
const primarySections = computed(() =>
  visibleSections.value.filter((section) => !section.group && !section.notificationSettings),
);
const bottomSections = computed(() =>
  visibleSections.value.filter((section) => section.notificationSettings),
);
const catalogueSections = computed(() =>
  visibleSections.value.filter((section) => section.group === 'catalogues'),
);
const cataloguesActive = computed(() => catalogueSections.value.some((section) => section.active));
const cataloguesOpen = ref(cataloguesActive.value);

function cataloguesToggled(event: Event) {
  cataloguesOpen.value = (event.target as HTMLDetailsElement).open;
}

const apiLinkClass = computed(() =>
  connection.value === 'ready' ? 'text-success' : 'text-danger',
);
let notificationTimer: ReturnType<typeof setInterval> | undefined;

async function refreshNotifications() {
  if (!auth.user || !navigator.onLine) return;

  try {
    await notifications.refresh();
  } catch {
    // A transient outage keeps the last known count; API handles session expiration.
  }
}

let connectionTimer: ReturnType<typeof setInterval> | undefined;

function connectionLost() {
  connection.value = 'error';
}

const breadcrumbParent = computed(() =>
  currentSection.value?.group === 'catalogues' ? 'Справочники' : 'Рабочее пространство',
);
const breadcrumbTitle = computed(() =>
  route.path.startsWith('/spaces/')
    ? 'Помещения'
    : route.path.startsWith('/unity-provider-contracts')
      ? 'Договоры поставщиков'
      : route.path === '/profile'
        ? 'Мой профиль'
        : (currentSection.value?.title ?? 'Страница не найдена'),
);
let searchControl: TomSelect | undefined;
const events = useEvent();
const { isDesktop } = useBrowserEvents();
const closeDesktopNavigation = (matches: boolean) => {
  if (matches) void setSidebarOpen(false);
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
  if (!navigator.onLine) {
    connectionLost();

    return;
  }

  try {
    const body = await api<unknown>('/api/healthz', { signal: AbortSignal.timeout(5000) }, false);

    connection.value =
      navigator.onLine &&
      typeof body === 'object' &&
      body !== null &&
      'status' in body &&
      body.status === 'ok'
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
  else if (!isDesktop()) sidebarToggle.value?.focus();
}

const removeNavigationListener = router.afterEach((to) => {
  if (
    catalogueSections.value.some(
      (section) => to.path === section.path || to.path.startsWith(section.path + '/'),
    )
  ) {
    cataloguesOpen.value = true;
  }

  void setSidebarOpen(false);
});

onMounted(() => {
  events.on('desktop-layout-changed', closeDesktopNavigation);

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

  events.on('browser-online', checkConnection);
  events.on('browser-offline', connectionLost);
  connectionTimer = setInterval(checkConnection, 30000);
  void checkConnection();
  void loadRoles();
  void refreshNotifications();
  notificationTimer = setInterval(refreshNotifications, 30000);
  events.on('browser-focus', refreshNotifications);
  events.on('browser-online', refreshNotifications);
});

onBeforeUnmount(() => {
  clearInterval(connectionTimer);
  clearInterval(notificationTimer);
  events.off('browser-focus', refreshNotifications);
  events.off('browser-online', refreshNotifications);
  events.off('browser-online', checkConnection);
  events.off('browser-offline', connectionLost);
  removeNavigationListener();
  events.off('desktop-layout-changed', closeDesktopNavigation);
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
          v-for="section in primarySections"
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
          <Badge
            v-if="section.hasUnread"
            class="ms-auto"
            color="red"
            pill
            size="small"
          >
            {{ notifications.countLabel }}
          </Badge>
        </RouterLink>
        <details
          class="workspace-nav-group"
          :open="cataloguesOpen"
          @toggle="cataloguesToggled"
        >
          <summary
            class="workspace-nav-link"
            :class="{ active: cataloguesActive }"
          >
            <IconBooks
              :size="21"
              stroke="1.7"
              aria-hidden="true"
            />
            <span class="flex-fill">
              Справочники
            </span>
            <IconChevronDown
              class="catalogues-chevron"
              :size="16"
              aria-hidden="true"
            />
          </summary>
          <RouterLink
            v-for="section in catalogueSections"
            :key="section.path"
            :to="section.path"
            class="workspace-nav-link workspace-nav-child"
            :class="{ active: section.active }"
            :aria-current="section.ariaCurrent"
          >
            <span>
              {{ section.title }}
            </span>
          </RouterLink>
        </details>
        <RouterLink
          v-for="section in bottomSections"
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
            {{ breadcrumbParent }}
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
            to="/notifications"
            class="btn btn-icon notification-bell"
            :aria-label="notifications.accessibleLabel"
            title="Уведомления"
          >
            <IconBell
              :size="21"
              stroke="1.7"
            />
            <Badge
              v-if="notifications.unreadCount"
              aria-hidden="true"
              color="red"
              pill
              size="small"
            >
              {{ notifications.countLabel }}
            </Badge>
          </RouterLink>
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
          v-if="showPrototypeNotice"
          class="prototype-notice"
        >
          <Badge>
            Начальный интерфейс
          </Badge>
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
        <RouterView
          v-else-if="roles.loaded"
          :key="viewKey"
        />
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
            href="/help/users.html"
            target="_blank"
            rel="noopener"
          >
            Инструкция пользователя
          </a>
          ·
          <a
            :class="apiLinkClass"
            href="/server/api"
            target="_blank"
            rel="noopener"
          >
            API v0.1
          </a>
        </span>
      </footer>
    </div>
  </div>
</template>
