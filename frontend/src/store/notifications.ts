import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import useApi from '../use/api.ts';
import type { Notification, NotificationCount } from '../types/models/Notification';

export const useNotificationsStore = defineStore('notifications', () => {
  const { api } = useApi();
  const unreadCount = ref(0);
  const countLabel = computed(() => (unreadCount.value > 99 ? '99+' : String(unreadCount.value)));
  const accessibleLabel = computed(() => 'Уведомления: ' + unreadCount.value + ' непрочитанных');
  let version = 0;
  let controller: AbortController | undefined;

  function reset() {
    version++;
    controller?.abort();
    unreadCount.value = 0;
  }

  async function refresh() {
    controller?.abort();
    const current = new AbortController();
    const session = version;

    controller = current;

    try {
      const result = await api<NotificationCount>('/api/notifications/unread-count', {
        signal: current.signal,
      });

      if (!current.signal.aborted && session === version) unreadCount.value = result.unreadCount;
    } catch (error) {
      if (!current.signal.aborted) throw error;
    }
  }

  async function markRead(id: number): Promise<Notification> {
    const result = await api<Notification>('/api/notifications/' + id + '/read', { method: 'PUT' });

    await refresh();

    return result;
  }

  async function markAllRead() {
    const session = version;
    const result = await api<NotificationCount>('/api/notifications/read-all', { method: 'PUT' });

    controller?.abort();

    if (session === version) unreadCount.value = result.unreadCount;
  }

  return { unreadCount, countLabel, accessibleLabel, refresh, markRead, markAllRead, reset };
});
