import type { Meter, MeterReadingSubmissionStatus } from '../types/models/Meter';
import type { MeterReadingBadge, MeterSubmissionState } from '../types/use/MeterReading';

export function meterReadingDate(value: string): string {
  return value.slice(0, 10).split('-').reverse().join('.');
}

export function meterReadingMonth(value: string): string {
  const month = new Date(value.slice(0, 7) + '-01T00:00:00Z');

  return (
    new Intl.DateTimeFormat('ru-RU', { month: 'long', timeZone: 'UTC' }).format(month) +
    ' ' +
    value.slice(0, 4)
  );
}

export function meterReadingBadge(
  status: MeterReadingSubmissionStatus | null | undefined,
): MeterReadingBadge {
  if (status === 'on_time') return { label: 'Передан вовремя', color: 'green' };

  if (status === 'late') return { label: 'Передан с просрочкой', color: 'red' };

  return { label: '—', color: null };
}

export function pendingMeterReadingBadge(
  meter?: Pick<Meter, 'readingRequired' | 'readingOverdue' | 'readingDueDate'>,
): MeterReadingBadge | null {
  if (!meter?.readingRequired || !meter.readingDueDate) return null;

  const deadline = meterReadingDate(meter.readingDueDate);

  return {
    label: meter.readingOverdue ? 'Не передан' : 'Передать до ' + deadline,
    color: meter.readingOverdue ? 'red' : 'orange',
    title: 'Срок передачи показаний: ' + deadline,
  };
}

export function meterSubmissionBadge(meter: MeterSubmissionState): MeterReadingBadge {
  if (!meter.installation) return { label: 'Не требуется', color: 'secondary' };

  if (meter.readingSubmittedLate) return meterReadingBadge('late');

  if (meter.readingSubmitted) return meterReadingBadge('on_time');

  return pendingMeterReadingBadge(meter) ?? { label: '—', color: null };
}
