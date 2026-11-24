import type { MeterDraft, MeterLocationDraft } from '../types/forms/Meter.ts';

export function integerError(value: string, min = 0, max = 2147483647): string {
  return /^[0-9]+$/.test(value) && Number(value) >= min && Number(value) <= max
    ? ''
    : 'Укажите целое число';
}

export function dateError(value: string): string {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return 'Укажите корректную дату.';

  const date = new Date(value + 'T00:00:00Z');

  return Number.isFinite(date.getTime()) && date.toISOString().slice(0, 10) === value
    ? ''
    : 'Укажите корректную дату.';
}

export function meterErrors(draft: MeterDraft): Record<string, string> {
  return {
    utilityId: draft.utilityId ? '' : 'Выберите коммуникацию.',
    createDate: dateError(draft.createDate),
    startRead: integerError(draft.startRead),
    verificationIntervalDays: integerError(draft.verificationIntervalDays, 1),
    readingDay: draft.readingDay ? integerError(draft.readingDay, 1, 31) : '',
    verificationDate: draft.verificationDate
      ? dateError(draft.verificationDate) ||
        (draft.verificationDate < draft.createDate
          ? 'Поверка не может быть раньше производства.'
          : '')
      : '',
  };
}

export function locationErrors(draft: MeterLocationDraft): Record<string, string> {
  return {
    objectId: '',
    spaceId: draft.type === 'space' && !draft.spaceId ? 'Выберите помещение.' : '',
  };
}
