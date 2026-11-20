import type { RentalObjectDraft } from '../types/forms/RentalObject';
import type { ApiErrors } from '../types/use/Api';

function required(value: string, label: string) {
  return value.trim() ? '' : label;
}

function square(value: string) {
  return /^(?=.*[1-9])[0-9]+(?:\.[0-9]+)?$/.test(value.trim().replace(',', '.'))
    ? ''
    : 'Введите положительную площадь.';
}

function rentalObject(value: RentalObjectDraft): ApiErrors {
  const errors: ApiErrors = {};
  const fields = {
    name: required(value.name, 'Укажите название.'),
    address: required(value.address, 'Укажите адрес.'),
    square: square(value.square),
    landLordId: value.landLordId ? '' : 'Выберите арендодателя.',
    readingDay:
      /^[0-9]+$/.test(value.readingDay) &&
      Number(value.readingDay) >= 1 &&
      Number(value.readingDay) <= 31
        ? ''
        : 'Укажите день от 1 до 31.',
  };

  for (const [key, message] of Object.entries(fields)) {
    if (message) errors[key] = message;
  }

  return errors;
}

export default function useRentalObjectValidation() {
  return { required, square, rentalObject };
}
