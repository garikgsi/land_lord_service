import IMask from 'imask';

const phoneOptions = {
  mask: '+{7} (000) 000-00-00',
  prepare(value: string) {
    const digits = value.replace(/\D/g, '');

    return digits.length === 11 && digits.startsWith('8') ? '7' + digits.slice(1) : value;
  },
};

const client = {
  phone(input: HTMLInputElement) {
    return IMask(input, phoneOptions);
  },
  phoneModel(value: string) {
    let digits = value.replace(/\D/g, '');

    if (value.trimStart().startsWith('+7') || (digits.length === 11 && /^[78]/.test(digits)))
      digits = digits.slice(1);

    const mask = IMask.createMask(phoneOptions);

    mask.resolve(digits ? '7' + digits.slice(0, 10) : '');

    return mask;
  },
};

/** Shared adapter for input masks. Components own and destroy their input instance. */
export default function useMask() {
  return client;
}
