function parse(value: string) {
  const normalized = value.trim().replace(',', '.');

  if (!/^-?\d+(?:\.\d+)?$/.test(normalized)) throw new Error('Введите корректное число.');

  const fraction = normalized.split('.')[1]?.length ?? 0;

  return { units: BigInt(normalized.replace('.', '')), scale: 10n ** BigInt(fraction) };
}

function rounded(numerator: bigint, denominator: bigint, places: number): string {
  const factor = 10n ** BigInt(places);
  const absolute = numerator < 0n ? -numerator : numerator;
  const units = (absolute * factor * 2n + denominator) / (denominator * 2n);
  const digits = units.toString().padStart(places + 1, '0');
  const sign = numerator < 0n && units !== 0n ? '-' : '';

  return sign + digits.slice(0, -places) + '.' + digits.slice(-places);
}

export function indexedAmount(current: string, percent: string): string {
  const base = parse(current);
  const change = parse(percent);

  if (base.units < 0n || change.units < -100n * change.scale)
    throw new Error('Сумма не может быть отрицательной; процент должен быть не меньше −100.');

  return rounded(
    base.units * (100n * change.scale + change.units),
    base.scale * change.scale * 100n,
    2,
  );
}

export function indexationPercent(current: string, amount: string): string {
  const base = parse(current);
  const target = parse(amount);

  if (base.units < 0n || target.units < 0n) throw new Error('Введите неотрицательную сумму.');

  if (base.units === 0n) {
    if (target.units === 0n) return '0';

    throw new Error('Процент повышения от нулевой суммы не определён.');
  }

  return rounded(
    (target.units * base.scale - base.units * target.scale) * 100n,
    target.scale * base.units,
    6,
  ).replace(/\.?0+$/, '');
}
