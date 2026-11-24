import test from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { createPinia } from 'pinia';

test('meter fields keep the optional model after communication and the optional comment last', async () => {
  const server = await createServer({ server: { middlewareMode: true }, logLevel: 'error' });

  try {
    const { default: MeterFields } = await server.ssrLoadModule('/src/components/MeterFields.vue');
    const app = createSSRApp({
      render: () =>
        h(MeterFields, {
          modelValue: {
            utilityId: 1,
            model: '',
            comment: '',
            serialNumber: '',
            createDate: '',
            startRead: '',
            verificationIntervalDays: '',
            verificationDate: '',
            readingDay: '',
          },
          disabled: true,
          showReadingDay: true,
          errors: {},
          validation: {},
        }),
    });

    app.use(createPinia());

    const html = await renderToString(app);
    const fieldIds = [...html.matchAll(/<(?:input|select|textarea)\b[^>]*\bid="([^"]+)"/g)].map(
      (match) => match[1],
    );

    assert.deepEqual(
      [
        fieldIds.indexOf('meter-model') === fieldIds.indexOf('meter-utility') + 1,
        fieldIds.at(-1),
        /<input[^>]*id="meter-model"[^>]*required/.test(html),
        /<textarea[^>]*id="meter-comment"[^>]*required/.test(html),
        /<textarea[^>]*id="meter-comment"[^>]*readonly/.test(html),
      ],
      [true, 'meter-comment', false, false, true],
    );
  } finally {
    await server.close();
  }
});
