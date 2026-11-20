import { computed, onScopeDispose, ref } from 'vue';
import { defineStore } from 'pinia';
import useApi from '../use/api.ts';
import useEvent from '../use/event.ts';
import { useRolesStore } from './roles.ts';
import type { User } from '../types/models/User';
import type { AuthSession, StoredSession } from '../types/api/Auth';

export const useAuthStore = defineStore('auth', () => {
  const { api, setToken } = useApi();
  const events = useEvent();
  const storageKey = 'landlordservice.session';
  const user = ref<User | null>(null);
  const isAdmin = computed(
    () =>
      user.value?.roles.some((role) => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'].includes(role)) ?? false,
  );
  const isSuperAdmin = computed(() => user.value?.roles.includes('ROLE_SUPER_ADMIN') ?? false);
  // Keep credentials out of Pinia state and the development tools.
  let token: string | null = null;
  let initialization: Promise<void> | undefined;
  let sessionVersion = 0;

  function clear() {
    sessionVersion++;
    token = null;
    user.value = null;
    sessionStorage.removeItem(storageKey);
    setToken(null);
    useRolesStore().reset();
  }

  events.on('session-expired', clear);
  onScopeDispose(() => events.off('session-expired', clear));

  async function initialize() {
    initialization ??= (async () => {
      let saved: StoredSession | null = null;

      try {
        saved = JSON.parse(sessionStorage.getItem(storageKey) ?? 'null');
      } catch {
        clear();
      }

      if (
        !saved ||
        typeof saved.accessToken !== 'string' ||
        !Number.isFinite(Date.parse(saved.expiresAt)) ||
        Date.parse(saved.expiresAt) <= Date.now()
      ) {
        clear();

        return;
      }

      const version = sessionVersion;

      token = saved.accessToken;
      setToken(token);

      try {
        const current = await api<User>('/api/auth/me');

        if (version === sessionVersion) user.value = current;
      } catch {
        if (version === sessionVersion) clear();
      }
    })();
    await initialization;
  }

  async function login(email: string, password: string) {
    const session = await api<AuthSession>(
      '/api/auth/login',
      { method: 'POST', body: JSON.stringify({ email: email.trim().toLowerCase(), password }) },
      false,
    );

    sessionVersion++;
    token = session.accessToken;
    user.value = session.user;
    setToken(token);
    sessionStorage.setItem(
      storageKey,
      JSON.stringify({ accessToken: token, expiresAt: session.expiresAt }),
    );
  }

  async function logout() {
    try {
      if (token) await api('/api/auth/logout', { method: 'POST' });
    } finally {
      clear();
    }
  }

  return { user, isAdmin, isSuperAdmin, initialize, login, logout, clear };
});
