import { createRouter, createWebHashHistory } from 'vue-router';
import {
  IconBuildingEstate,
  IconCalendarMonth,
  IconChartBar,
  IconCreditCard,
  IconFileText,
  IconGauge,
} from '@tabler/icons-vue';

export const sections = [
  { path: '/', title: 'Обзор', icon: IconChartBar },
  { path: '/objects', title: 'Объекты', icon: IconBuildingEstate },
  { path: '/contracts', title: 'Договоры', icon: IconFileText },
  { path: '/meters', title: 'Приборы учёта', icon: IconGauge },
  { path: '/payments', title: 'Платежи', icon: IconCreditCard },
  { path: '/calendar', title: 'Календарь', icon: IconCalendarMonth },
];

export const router = createRouter({
  history: createWebHashHistory(),
  scrollBehavior: () => ({ top: 0 }),
  routes: [
    { path: '/', component: () => import('./views/OverviewView.vue') },
    ...sections.slice(1, -1).map((section) => ({
      path: section.path,
      component: () => import('./views/RegisterView.vue'),
    })),
    { path: '/calendar', component: () => import('./views/CalendarView.vue') },
    { path: '/:pathMatch(.*)*', component: () => import('./views/NotFoundView.vue') },
  ],
});
