import type { MeterReading } from '../types/models/Meter';
import type { MeterConsumptionSummary } from '../types/use/MeterConsumption';
import { meterReadingMonth } from './meterReading.ts';

export function meterConsumptionSummary(
  readings: readonly MeterReading[],
  unit: string | null,
): MeterConsumptionSummary {
  const periods = [...readings]
    .filter(
      (reading): reading is MeterReading & { consumption: number } =>
        reading.consumption !== undefined,
    )
    .sort(
      (left, right) =>
        right.meterReadingDate.localeCompare(left.meterReadingDate) || right.id - left.id,
    )
    .slice(0, 2)
    .map((reading) => ({
      id: reading.id,
      label: meterReadingMonth(reading.meterReadingDate),
      detail:
        meterReadingMonth(reading.meterReadingDate) +
        ' — ' +
        reading.consumption.toLocaleString('ru-RU') +
        (unit ? ' ' + unit : ''),
      value: reading.consumption,
      reading: reading.meterRead,
      date: reading.meterReadingDate,
    }));
  const current = periods[0];
  const previous = periods[1];
  const change =
    !previous || !current || (previous.value === 0 && current.value > 0)
      ? null
      : previous.value === 0
        ? 0
        : ((current.value - previous.value) / previous.value) * 100;
  const difference = current && previous ? current.value - previous.value : 0;
  const color = difference > 0 ? 'red' : difference < 0 ? 'green' : 'secondary';
  const changeLabel =
    change === null
      ? '—'
      : (change > 0 ? '+' : '') +
        change.toLocaleString('ru-RU', { maximumFractionDigits: 1 }) +
        '%';
  const changeDescription = !previous
    ? 'Для сравнения нужно два показания.'
    : change === null
      ? 'Предыдущее потребление равно нулю; процент изменения не определён.'
      : 'Изменение потребления относительно предыдущего измерения.';

  return { periods, change, changeLabel, changeDescription, color };
}
