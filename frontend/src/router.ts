import { Capacitor } from '@capacitor/core';
import { createRouter, createWebHistory, createWebHashHistory } from 'vue-router';
import {
  IconBuildingEstate,
  IconBell,
  IconCalendarMonth,
  IconChartBar,
  IconCreditCard,
  IconFileText,
  IconGauge,
  IconUsers,
} from '@tabler/icons-vue';
import { configureAuthentication } from './router/authentication';
import { pinia } from './store';
import type { Section } from './types/router/Section';

export const sections: Section[] = [
  { path: '/', title: 'Обзор', icon: IconChartBar },
  { path: '/objects', title: 'Объекты', icon: IconBuildingEstate },
  {
    path: '/objects/land-lords',
    title: 'Арендодатели',
    icon: IconBuildingEstate,
    group: 'catalogues',
  },
  { path: '/renters', title: 'Арендаторы', icon: IconUsers, group: 'catalogues' },
  {
    path: '/catalogues/ownerships',
    title: 'Формы собственности',
    icon: IconUsers,
    group: 'catalogues',
  },
  {
    path: '/catalogues/contract-types',
    title: 'Типы договоров',
    icon: IconFileText,
    group: 'catalogues',
  },
  { path: '/contracts', title: 'Договоры', icon: IconFileText },
  { path: '/catalogues/unities', title: 'Коммуникации', icon: IconGauge, group: 'catalogues' },
  {
    path: '/catalogues/unity-providers',
    title: 'Поставщики коммунальных услуг',
    icon: IconBuildingEstate,
    group: 'catalogues',
  },
  { path: '/meters', title: 'Приборы учёта', icon: IconGauge },
  { path: '/payments', title: 'Платежи', icon: IconCreditCard },
  { path: '/calendar', title: 'Календарь', icon: IconCalendarMonth },
  { path: '/notifications', title: 'Уведомления', icon: IconBell },
  { path: '/admin/users', title: 'Пользователи', icon: IconUsers, admin: true },
  {
    path: '/settings/notification-types',
    title: 'Настройки уведомлений',
    icon: IconBell,
    notificationSettings: true,
  },
];

const native = Capacitor.isNativePlatform();

if (!native && typeof window !== 'undefined') {
  if (window.location.hash.startsWith('#/')) {
    window.history.replaceState(null, '', window.location.hash.slice(1));
  } else if (window.location.pathname === '/index.html') {
    window.history.replaceState(null, '', '/' + window.location.search);
  }
}

export const router = createRouter({
  history: native ? createWebHashHistory() : createWebHistory(),
  scrollBehavior: () => ({ top: 0 }),
  routes: [
    { path: '/login', component: () => import('./views/LoginView.vue') },
    { path: '/forgot-password', component: () => import('./views/ForgotPasswordView.vue') },
    { path: '/reset-password', component: () => import('./views/ResetPasswordView.vue') },
    { path: '/activate', component: () => import('./views/ActivationView.vue') },
    {
      path: '/',
      component: () => import('./layouts/WorkspaceLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          component: () => import('./views/OverviewView.vue'),
          meta: { dashboard: true },
        },
        ...sections
          .filter((section) => ['/payments'].includes(section.path))
          .map((section) => ({
            path: section.path.slice(1),
            component: () => import('./views/RegisterView.vue'),
          })),
        {
          path: 'settings/notification-types',
          component: () => import('./views/NotificationTypeListView.vue'),
          meta: { notificationSettings: true },
        },
        {
          path: 'settings/notification-types/:id(\\d+)',
          component: () => import('./views/NotificationTypeEditView.vue'),
          meta: { notificationSettings: true },
        },
        {
          path: 'unity-provider-contracts',
          component: () => import('./views/UnityProviderContractListView.vue'),
          meta: { rentalRegistry: true },
        },
        {
          path: 'unity-provider-contracts/new',
          component: () => import('./views/UnityProviderContractEditView.vue'),
          meta: { rentalRegistry: true, rentalRegistryEdit: true },
        },
        {
          path: 'unity-provider-contracts/:id(\\d+)',
          component: () => import('./views/UnityProviderContractEditView.vue'),
          meta: { rentalRegistry: true },
        },
        {
          path: 'objects',
          component: () => import('./views/RegistryListView.vue'),
          props: { kind: 'objects' },
          meta: { rentalRegistry: true, rentalRegistryEdit: false },
        },
        {
          path: 'objects/new',
          component: () => import('./views/RegistryEditView.vue'),
          props: { kind: 'objects' },
          meta: { rentalRegistry: true, rentalRegistryEdit: true },
        },
        {
          path: 'objects/:id(\\d+)',
          component: () => import('./views/RegistryEditView.vue'),
          props: { kind: 'objects' },
          meta: { rentalRegistry: true, rentalRegistryEdit: false },
        },
        {
          path: 'objects/land-lords',
          component: () => import('./views/RegistryListView.vue'),
          props: { kind: 'land-lords' },
          meta: { rentalRegistry: true, rentalRegistryEdit: false },
        },
        {
          path: 'objects/land-lords/new',
          component: () => import('./views/RegistryEditView.vue'),
          props: { kind: 'land-lords' },
          meta: { rentalRegistry: true, rentalRegistryEdit: true },
        },
        {
          path: 'objects/land-lords/:id(\\d+)',
          component: () => import('./views/RegistryEditView.vue'),
          props: { kind: 'land-lords' },
          meta: { rentalRegistry: true, rentalRegistryEdit: false },
        },
        {
          path: 'contracts',
          component: () => import('./views/ContractListView.vue'),
          meta: { rentalRegistry: true },
        },
        {
          path: 'contracts/new',
          component: () => import('./views/ContractEditView.vue'),
          meta: { rentalRegistry: true, rentalRegistryEdit: true },
        },
        {
          path: 'contracts/:id(\\d+)',
          component: () => import('./views/ContractEditView.vue'),
          meta: { rentalRegistry: true },
        },
        {
          path: 'spaces/:id(\\d+)',
          component: () => import('./views/SpaceEditView.vue'),
          meta: { rentalRegistry: true },
        },
        {
          path: 'renters',
          component: () => import('./views/RenterListView.vue'),
          meta: { rentalRegistry: true },
        },
        {
          path: 'renters/new',
          component: () => import('./views/RenterEditView.vue'),
          meta: { rentalRegistry: true, rentalRegistryEdit: true },
        },
        {
          path: 'renters/:id(\\d+)',
          component: () => import('./views/RenterEditView.vue'),
          meta: { rentalRegistry: true },
        },
        ...(['ownerships', 'contract-types'] as const).flatMap((kind) => [
          {
            path: 'catalogues/' + kind,
            component: () => import('./views/NamedRegistryListView.vue'),
            props: { kind },
            meta: { rentalRegistry: true },
          },
          {
            path: 'catalogues/' + kind + '/new',
            component: () => import('./views/NamedRegistryEditView.vue'),
            props: { kind },
            meta: { rentalRegistry: true, rentalRegistryEdit: true },
          },
          {
            path: 'catalogues/' + kind + '/:id(\\d+)',
            component: () => import('./views/NamedRegistryEditView.vue'),
            props: { kind },
            meta: { rentalRegistry: true },
          },
        ]),
        {
          path: 'renters/:renterId(\\d+)/contacts/new',
          component: () => import('./views/RenterContactEditView.vue'),
          meta: { rentalRegistry: true },
        },
        {
          path: 'renters/:renterId(\\d+)/contacts/:id(\\d+)',
          component: () => import('./views/RenterContactEditView.vue'),
          meta: { rentalRegistry: true },
        },
        {
          path: 'meters',
          component: () => import('./views/MeterListView.vue'),
          meta: { meterRegistry: true },
        },
        {
          path: 'meters/new',
          component: () => import('./views/MeterEditView.vue'),
          meta: { meterRegistry: true, meterInstall: true },
        },
        {
          path: 'meters/:id(\\d+)',
          component: () => import('./views/MeterEditView.vue'),
          meta: { meterRegistry: true },
        },
        {
          path: 'meters/:id(\\d+)/edit',
          redirect: (to) => ({ path: '/meters/' + to.params.id, query: to.query }),
        },
        ...(['readings', 'verifications'] as const).flatMap((kind) => [
          {
            path: 'meters/:meterId(\\d+)/' + kind + '/new',
            component: () => import('./views/MeterRecordEditView.vue'),
            props: { kind },
            meta: {
              meterRegistry: true,
              meterEdit: kind === 'verifications',
              meterReadingEdit: kind === 'readings',
            },
          },
          ...(kind === 'readings'
            ? [
                {
                  path: 'meters/:meterId(\\d+)/readings/:id(\\d+)',
                  component: () => import('./views/MeterRecordEditView.vue'),
                  props: { kind },
                  meta: { meterRegistry: true, meterReadingEdit: true },
                },
              ]
            : []),
        ]),
        ...(['unities', 'unity-providers'] as const).flatMap((kind) => [
          {
            path: 'catalogues/' + kind,
            component: () => import('./views/UtilityListView.vue'),
            props: { kind },
            meta: { meterRegistry: true },
          },
          {
            path: 'catalogues/' + kind + '/new',
            component: () => import('./views/UtilityEditView.vue'),
            props: { kind },
            meta: { meterRegistry: true, meterEdit: true },
          },
          {
            path: 'catalogues/' + kind + '/:id(\\d+)',
            component: () => import('./views/UtilityEditView.vue'),
            props: { kind },
            meta: { meterRegistry: true },
          },
        ]),
        { path: 'calendar', component: () => import('./views/CalendarView.vue') },
        {
          path: 'notifications',
          component: () => import('./views/NotificationsView.vue'),
          meta: { notifications: true },
        },
        {
          path: 'notifications/:id(\\d+)',
          component: () => import('./views/NotificationView.vue'),
          meta: { notifications: true },
        },
        {
          path: 'admin/users',
          component: () => import('./views/AdminUsersView.vue'),
          meta: { admin: true },
        },
        {
          path: 'admin/users/new',
          component: () => import('./views/AdminUserEditView.vue'),
          meta: { admin: true },
        },
        {
          path: 'admin/users/:id(\\d+)',
          component: () => import('./views/AdminUserEditView.vue'),
          meta: { admin: true },
        },
        { path: 'profile', component: () => import('./views/ProfileView.vue') },
        { path: ':pathMatch(.*)*', component: () => import('./views/NotFoundView.vue') },
      ],
    },
  ],
});

function followLegacyHash() {
  if (!native && window.location.hash.startsWith('#/')) {
    void router.replace(window.location.hash.slice(1));
  }
}

if (!native && typeof window !== 'undefined')
  window.addEventListener('hashchange', followLegacyHash);

const disposeAuthentication = configureAuthentication(router, pinia);
const removeCreationGuard = router.beforeEach((to, from) => {
  if (
    to.meta.requiresAuth &&
    to.path.endsWith('/new') &&
    from.meta.requiresAuth &&
    !from.path.endsWith('/new') &&
    !to.query.returnTo
  ) {
    return {
      path: to.path,
      query: { ...to.query, returnTo: from.fullPath },
      hash: to.hash,
      replace: true,
    };
  }
});

import.meta.hot?.dispose(() => {
  disposeAuthentication();
  removeCreationGuard();

  if (!native && typeof window !== 'undefined')
    window.removeEventListener('hashchange', followLegacyHash);
});
