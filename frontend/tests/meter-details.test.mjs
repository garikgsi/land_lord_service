import test from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { createRouter, createMemoryHistory } from 'vue-router';

test('meter details show complete information and warnings without editable controls', async () => {
  const server = await createServer({ server: { middlewareMode: true }, logLevel: 'error' });

  try {
    const { default: MeterDetails } = await server.ssrLoadModule(
      '/src/components/MeterDetails.vue',
    );
    const meter = {
      id: 9,
      name: 'Water · A-123',
      utility: { id: 1, name: 'Water', unit: 'm3', useMeter: true },
      serialNumber: 'A-123',
      model: 'Water model',
      comment: 'Device note',
      createDate: '2020-01-02',
      startRead: 5,
      verificationIntervalDays: 365,
      verificationDate: null,
      nextVerificationDate: '2021-01-01',
      verificationExpired: true,
      readingDay: null,
      effectiveReadingDay: 25,
      readingDueDate: '2026-11-25',
      nextReadingDueDate: '2026-12-25',
      readingRequired: false,
      readingSubmitted: true,
      readingSubmittedLate: true,
      readingOverdue: false,
      readingAttention: false,
      lastReading: { id: 1, meterId: 9, meterReadingDate: '2026-11-26T12:30:00', meterRead: 78 },
      installation: {
        id: 4,
        type: 'space',
        name: 'Room',
        object: { id: 2, name: 'Object', readingDay: 25 },
        comment: 'Hallway',
        provider: { unityProvider: { name: 'Provider' } },
      },
    };

    async function render(value) {
      const app = createSSRApp({ render: () => h(MeterDetails, { meter: value }) });

      app.use(createRouter({ history: createMemoryHistory(), routes: [] }));

      return renderToString(app);
    }

    const html = await render(meter);
    const removed = await render({
      ...meter,
      installation: null,
      lastReading: null,
      effectiveReadingDay: null,
      readingDueDate: null,
      nextReadingDueDate: null,
      readingSubmitted: false,
      readingSubmittedLate: false,
    });

    assert.deepEqual(
      [
        [
          'A-123',
          'Water model',
          'Device note',
          'Water',
          'm3',
          '02.01.2020',
          '78',
          '26.11.2026',
          '25.11.2026',
          '25.12.2026',
          '365 дней',
          'Не проводилась',
          'Provider',
          'Hallway',
          'href="/objects/2"',
          'href="/spaces/4"',
          'Требуется поверка',
          'Показания переданы с просрочкой',
        ].every((text) => html.includes(text)),
        /<(?:input|select|textarea|button|form)\b/.test(html),
        ['Не установлен', 'Передача показаний не требуется', 'Ещё не передавались'].every((text) =>
          removed.includes(text),
        ),
        removed.includes('href="/spaces/4"'),
        removed.includes('Device note'),
      ],
      [true, false, true, false, true],
    );
  } finally {
    await server.close();
  }
});
