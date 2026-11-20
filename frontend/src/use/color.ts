import type { CSSProperties } from 'vue';

export default function useColor() {
  function isColor(value: string | null): boolean {
    return value === null || /^#[0-9a-f]{6}$/i.test(value);
  }

  function colorStyle(value: string | null): CSSProperties {
    if (!value || !isColor(value)) return {};

    const channels = [1, 3, 5].map((offset) => {
      const channel = parseInt(value.slice(offset, offset + 2), 16) / 255;

      return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
    });
    const luminance = 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
    const whiteContrast = 1.05 / (luminance + 0.05);
    const blackContrast = (luminance + 0.05) / 0.05;

    return {
      backgroundColor: value,
      color: whiteContrast >= blackContrast ? '#ffffff' : '#000000',
    };
  }

  return { isColor, colorStyle };
}
