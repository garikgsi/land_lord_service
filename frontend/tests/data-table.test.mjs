import test from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { createRouter, createMemoryHistory } from 'vue-router';

test('DataTable paginates complete lists by default and preserves server pages', async () => {
  const server = await createServer({ server: { middlewareMode: true }, logLevel: 'error' });

  try {
    const { default: DataTable } = await server.ssrLoadModule('/src/components/ui/DataTable.vue');

    async function render(props) {
      const router = createRouter({ history: createMemoryHistory(), routes: [] });
      const app = createSSRApp({
        render: () =>
          h(DataTable, { columns: [{ key: 'name', label: 'Name' }], caption: 'Records', ...props }),
      });

      app.use(router);

      return renderToString(app);
    }

    const rows = Array.from({ length: 45 }, (_, i) => ({ id: i + 1, name: 'record-' + (i + 1) }));
    const client = await render({ rows });
    const remote = await render({ rows: rows.slice(20, 40), total: 100, page: 2 });

    assert.deepEqual(
      [
        client.includes('record-20'),
        client.includes('record-21'),
        client.includes('Страница 3'),
        remote.includes('record-21'),
        remote.includes('record-40'),
        remote.includes('Страница 5'),
      ],
      [true, false, true, true, true, true],
    );
  } finally {
    await server.close();
  }
});
