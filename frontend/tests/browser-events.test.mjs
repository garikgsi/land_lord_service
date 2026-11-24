import test from 'node:test';
import assert from 'node:assert/strict';
import { createRenderer } from 'vue';
import useBrowserEvents from '../src/use/browserEvents.ts';
import useEvent from '../src/use/event.ts';

test('browser events reach the shared bus once and stop after layout disposal', () => {
  const originalWindow = Object.getOwnPropertyDescriptor(globalThis, 'window');
  const browser = new EventTarget();
  const desktop = new EventTarget();
  const received = [];
  const events = useEvent();
  const handlers = {
    'browser-online': () => received.push('online'),
    'browser-offline': () => received.push('offline'),
    'browser-focus': () => received.push('focus'),
    'desktop-layout-changed': (matches) => received.push(matches),
  };
  const renderer = createRenderer({
    createComment: () => ({}),
    insert() {},
    remove() {},
    parentNode: () => null,
    nextSibling: () => null,
  });
  const apps = [];

  desktop.matches = true;
  browser.matchMedia = () => desktop;
  Object.defineProperty(globalThis, 'window', { configurable: true, value: browser });

  for (const [event, handler] of Object.entries(handlers)) events.on(event, handler);

  try {
    for (let mount = 0; mount < 2; mount++) {
      let state;
      const app = renderer.createApp({
        setup() {
          state = useBrowserEvents();

          return () => null;
        },
      });

      apps.push(app);
      app.mount({});
      received.push(state.isDesktop());
      browser.dispatchEvent(new Event('online'));
      browser.dispatchEvent(new Event('offline'));
      browser.dispatchEvent(new Event('focus'));
      desktop.dispatchEvent(Object.assign(new Event('change'), { matches: false }));
      app.unmount();
      apps.pop();
      browser.dispatchEvent(new Event('online'));
      desktop.dispatchEvent(Object.assign(new Event('change'), { matches: true }));
    }

    assert.deepEqual(received, [
      true,
      'online',
      'offline',
      'focus',
      false,
      true,
      'online',
      'offline',
      'focus',
      false,
    ]);
  } finally {
    for (const app of apps) app.unmount();

    for (const [event, handler] of Object.entries(handlers)) events.off(event, handler);

    if (originalWindow) Object.defineProperty(globalThis, 'window', originalWindow);
    else delete globalThis.window;
  }
});
