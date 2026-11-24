import test from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { createRouter, createMemoryHistory } from 'vue-router';
import useBadge from '../src/use/badge.ts';

test('badge colours default to Azure and preserve custom foreground contrast', () => {
  const { appearance } = useBadge();

  assert.deepEqual(
    [
      appearance().className,
      appearance(null).className,
      appearance('invalid').className,
      appearance('red').className,
      appearance('#ffffff').style.color,
    ],
    [
      'bg-azure text-azure-fg',
      'bg-azure text-azure-fg',
      'bg-azure text-azure-fg',
      'bg-red text-red-fg',
      '#000000',
    ],
  );
});

test('the Basic badge supports links and buttons with the same shared shell', async () => {
  const server = await createServer({ server: { middlewareMode: true }, logLevel: 'error' });

  try {
    const { default: Badge } = await server.ssrLoadModule('/src/components/ui/Badge.vue');
    const app = createSSRApp({
      render: () =>
        h('div', [
          h(Badge, {}, () => 'Info'),
          h(Badge, { to: '/objects/1', color: '#4263eb', wrap: true }, () => 'Object'),
          h(Badge, { action: true, color: 'green' }, () => 'Meter'),
        ]),
    });

    app.use(createRouter({ history: createMemoryHistory(), routes: [] }));
    const html = await renderToString(app);

    assert.deepEqual(
      [
        'bg-azure text-azure-fg',
        'project-badge',
        'href="/objects/1"',
        'background-color:#4263eb',
        'project-badge--wrap',
        '<button',
        'type="button"',
      ].map((text) => html.includes(text)),
      [true, true, true, true, true, true, true],
    );
  } finally {
    await server.close();
  }
});
