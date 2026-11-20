import { createRouter, createWebHashHistory } from 'vue-router';
import {
  IconBuildingEstate,
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
  { path: '/contracts', title: 'Договоры', icon: IconFileText },
  { path: '/meters', title: 'Приборы учёта', icon: IconGauge },
  { path: '/payments', title: 'Платежи', icon: IconCreditCard },
  { path: '/calendar', title: 'Календарь', icon: IconCalendarMonth },
  { path: '/admin/users', title: 'Пользователи', icon: IconUsers, admin: true },
];

export const router = createRouter({
  history: createWebHashHistory(),
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
        { path: '', component: () => import('./views/OverviewView.vue') },
        ...sections
          .filter((section) =>
            ['/objects', '/contracts', '/meters', '/payments'].includes(section.path),
          )
          .map((section) => ({
            path: section.path.slice(1),
            component: () => import('./views/RegisterView.vue'),
          })),
        { path: 'calendar', component: () => import('./views/CalendarView.vue') },
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
const disposeAuthentication = configureAuthentication(router, pinia);

import.meta.hot?.dispose(disposeAuthentication);
