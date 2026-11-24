import type { Router } from 'vue-router';
import type { Pinia } from 'pinia';
import { useAuthStore } from '../store/auth.ts';
import { watch } from 'vue';

export function configureAuthentication(router: Router, pinia: Pinia) {
  const auth = useAuthStore(pinia);
  const removeGuard = router.beforeEach(async (to) => {
    await auth.initialize();

    if (to.meta.requiresAuth && !auth.user)
      return { path: '/login', query: { redirect: to.fullPath } };

    if (to.meta.rentalRegistryEdit && !auth.canManageRentalRegistry) return '/objects';

    if (to.meta.meterInstall && !auth.canInstallMeters) return '/meters';

    if (to.meta.meterReadingEdit && !auth.canEditMeterReadings) return '/meters';

    if (to.meta.meterEdit && !auth.canManageMeters) return '/meters';

    if (to.meta.notificationSettings && !auth.canManageNotificationTypes) return '/';

    if (to.meta.admin && !auth.isAdmin) return '/';

    if (to.path === '/login' && auth.user) return '/';
  });
  // Navigation follows session state even when it is cleared without an API event.
  // Exception: session loss may happen outside navigation and must redirect the open protected page.
  const stopWatching = watch(
    () => auth.user,
    (user) => {
      const current = router.currentRoute.value;

      if (!user && current.matched.some((record) => record.meta.requiresAuth)) {
        void router.replace({
          path: '/login',
          query: { expired: '1', redirect: current.fullPath },
        });
      }
    },
  );

  return () => {
    removeGuard();
    stopWatching();
  };
}
