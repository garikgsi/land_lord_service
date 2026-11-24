import test from 'node:test';
import assert from 'node:assert/strict';
import {
  meterReadingDate,
  meterReadingMonth,
  meterReadingBadge,
  pendingMeterReadingBadge,
  meterSubmissionBadge,
} from '../src/use/meterReading.ts';

test('reading dates omit time and months have the requested Russian label', () => {
  assert.deepEqual(
    ['2025-01-31T23:59:59', '2026-12-01T00:00:00', '2024-02-29'].map((value) => [
      meterReadingDate(value),
      meterReadingMonth(value),
    ]),
    [
      ['31.01.2025', 'январь 2025'],
      ['01.12.2026', 'декабрь 2026'],
      ['29.02.2024', 'февраль 2024'],
    ],
  );
});

test('actual submissions use green on-time and red late badges', () => {
  assert.deepEqual(
    ['on_time', 'late', null].map((status) => meterReadingBadge(status)),
    [
      { label: 'Передан вовремя', color: 'green' },
      { label: 'Передан с просрочкой', color: 'red' },
      { label: '—', color: null },
    ],
  );
});

test('the header warns only when a current-month reading is required', () => {
  assert.deepEqual(
    [
      pendingMeterReadingBadge(),
      pendingMeterReadingBadge({
        readingRequired: false,
        readingDueDate: '2026-11-25',
        readingOverdue: false,
      }),
      pendingMeterReadingBadge({
        readingRequired: true,
        readingDueDate: '2026-11-25',
        readingOverdue: false,
      }),
      pendingMeterReadingBadge({
        readingRequired: true,
        readingDueDate: '2026-11-25',
        readingOverdue: true,
      }),
    ],
    [
      null,
      null,
      {
        label: 'Передать до 25.11.2026',
        color: 'orange',
        title: 'Срок передачи показаний: 25.11.2026',
      },
      {
        label: 'Не передан',
        color: 'red',
        title: 'Срок передачи показаний: 25.11.2026',
      },
    ],
  );
});

test('registry badges use monthly submission dates and do not require readings from detached meters', () => {
  const meter = {
    installation: { id: 1, type: 'space' },
    readingRequired: true,
    readingOverdue: false,
    readingDueDate: '2026-11-25',
    readingSubmitted: false,
    readingSubmittedLate: false,
  };

  assert.deepEqual(
    [
      meterSubmissionBadge(meter),
      meterSubmissionBadge({ ...meter, readingOverdue: true }),
      meterSubmissionBadge({ ...meter, readingSubmitted: true, readingRequired: false }),
      meterSubmissionBadge({
        ...meter,
        readingSubmitted: true,
        readingSubmittedLate: true,
        readingRequired: false,
      }),
      meterSubmissionBadge({ ...meter, installation: null }),
    ],
    [
      {
        label: 'Передать до 25.11.2026',
        color: 'orange',
        title: 'Срок передачи показаний: 25.11.2026',
      },
      { label: 'Не передан', color: 'red', title: 'Срок передачи показаний: 25.11.2026' },
      { label: 'Передан вовремя', color: 'green' },
      { label: 'Передан с просрочкой', color: 'red' },
      { label: 'Не требуется', color: 'secondary' },
    ],
  );
});
