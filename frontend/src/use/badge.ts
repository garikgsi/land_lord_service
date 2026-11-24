import { colorPalette } from '../store/colors.ts';
import useColor from './color.ts';
import type { BadgeAppearance } from '../types/use/Badge';

const namedColors = new Set(['secondary', ...colorPalette.map((option) => option.name)]);

export default function useBadge() {
  const { colorStyle, isColor } = useColor();

  function appearance(color?: string | null): BadgeAppearance {
    if (color?.startsWith('#') && isColor(color))
      return { className: '', style: colorStyle(color) };

    const name = color && namedColors.has(color) ? color : 'azure';

    return { className: 'bg-' + name + ' text-' + name + '-fg', style: {} };
  }

  return { appearance };
}
