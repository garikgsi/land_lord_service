import { onMounted, onScopeDispose } from 'vue';
import useEvent from './event.ts';

export default function useBrowserEvents() {
  const events = useEvent();
  const desktop = window.matchMedia('(min-width: 992px)');

  function online() {
    events.emit('browser-online');
  }

  function offline() {
    events.emit('browser-offline');
  }

  function focus() {
    events.emit('browser-focus');
  }

  function desktopChanged(event: MediaQueryListEvent) {
    events.emit('desktop-layout-changed', event.matches);
  }

  function isDesktop() {
    return desktop.matches;
  }

  onMounted(() => {
    window.addEventListener('online', online);
    window.addEventListener('offline', offline);
    window.addEventListener('focus', focus);
    desktop.addEventListener('change', desktopChanged);
  });

  onScopeDispose(() => {
    window.removeEventListener('online', online);
    window.removeEventListener('offline', offline);
    window.removeEventListener('focus', focus);
    desktop.removeEventListener('change', desktopChanged);
  });

  return { isDesktop };
}
