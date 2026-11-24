/** Compare exact decimal strings without rounding or binary floating point. */
export function compareDecimal(left: string, right: string): number {
  const [leftWhole = '0', leftFraction = ''] = left.trim().replace(',', '.').split('.');
  const [rightWhole = '0', rightFraction = ''] = right.trim().replace(',', '.').split('.');
  const scale = Math.max(leftFraction.length, rightFraction.length);
  const leftUnits = BigInt(leftWhole + leftFraction.padEnd(scale, '0'));
  const rightUnits = BigInt(rightWhole + rightFraction.padEnd(scale, '0'));

  if (leftUnits === rightUnits) return 0;

  return leftUnits < rightUnits ? -1 : 1;
}
