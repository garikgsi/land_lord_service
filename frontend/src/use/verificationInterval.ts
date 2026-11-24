const daysPerYear = 365;

export const verificationIntervalPresets = [
  { years: 1, label: '1 год' },
  { years: 2, label: '2 года' },
  { years: 3, label: '3 года' },
  { years: 5, label: '5 лет' },
].map((preset) => ({ ...preset, days: String(preset.years * daysPerYear) }));

export function verificationIntervalPreset(days: string) {
  if (!/^[0-9]+$/.test(days)) return undefined;

  return verificationIntervalPresets.find((preset) => Number(preset.days) === Number(days));
}
