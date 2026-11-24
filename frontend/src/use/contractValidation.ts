import { compareDecimal } from './decimal.ts';
import type { ContractDraft } from '../types/forms/Contract';
import type { ApiErrors } from '../types/use/Api';

function decimal(value: string): boolean {
  return /^[0-9]+(?:\.[0-9]+)?$/.test(value);
}

function date(value: string): boolean {
  return (
    /^\d{4}-\d{2}-\d{2}$/.test(value) &&
    !Number.isNaN(Date.parse(value)) &&
    new Date(value).toISOString().slice(0, 10) === value
  );
}

function contract(
  value: ContractDraft,
  editableSquare: boolean,
  availableSquare?: string,
): ApiErrors {
  const errors: ApiErrors = {};

  if (!value.objectId) errors.objectId = 'Выберите объект.';

  if (!value.renterId) errors.renterId = 'Выберите арендатора.';

  if (!value.contractTypeId) errors.contractTypeId = 'Выберите тип договора.';

  if (!value.contractNum.trim()) errors.contractNum = 'Укажите номер договора.';

  if (!date(value.contractDate)) errors.contractDate = 'Укажите корректную дату начала аренды.';

  if (!date(value.expiryDate)) errors.expiryDate = 'Укажите корректную дату окончания аренды.';
  else if (value.expiryDate < value.contractDate)
    errors.expiryDate = 'Окончание не может быть раньше начала.';

  if (!decimal(value.monthlyPayment.replace(',', '.')))
    errors.monthlyPayment = 'Введите неотрицательную сумму.';

  if (!decimal(value.securityPayment.replace(',', '.')))
    errors.securityPayment = 'Введите неотрицательную сумму.';

  if (editableSquare && (!decimal(value.square.replace(',', '.')) || !/[1-9]/.test(value.square))) {
    errors.square = 'Введите положительную площадь.';
  }

  if (
    editableSquare &&
    !errors.square &&
    availableSquare !== undefined &&
    compareDecimal(value.square, availableSquare) > 0
  ) {
    errors.square = 'Недостаточно свободной площади на объекте.';
  }

  if (value.increaseDate || value.increasePercent) {
    if (!date(value.increaseDate)) errors.increaseDate = 'Укажите дату индексации.';

    if (!decimal(value.increasePercent.replace(',', '.')))
      errors.increasePercent = 'Укажите неотрицательный процент.';
  }

  return errors;
}

function nextDay(value: string): string {
  const result = new Date(value + 'T00:00:00Z');

  result.setUTCDate(result.getUTCDate() + 1);

  return result.toISOString().slice(0, 10);
}

export default function useContractValidation() {
  return { contract, date, decimal, nextDay };
}
