import type { UserProfileErrors, UserProfileValidationInput } from '../types/use/Validation';

// Keep the email rule aligned with Symfony EmailValidator's HTML5 mode.
const emailPattern =
  /^[a-zA-Z0-9.!#$%&'*+\/=?^_\x60{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/;
const phonePattern = /^\+7(?:[0-9]{10}| (?:\([0-9]{3}\)|[0-9]{3}) [0-9]{3}-[0-9]{2}-[0-9]{2})$/;

function firstName(value: string | null) {
  if (!value?.trim()) return 'Укажите имя.';

  return Array.from(value.trim()).length > 100 ? 'Имя не должно быть длиннее 100 символов.' : '';
}

function email(value: string) {
  const trimmed = value.trim();

  if (!trimmed) return 'Укажите электронную почту.';

  return trimmed.length > 254 || !emailPattern.test(trimmed)
    ? 'Введите корректный адрес электронной почты.'
    : '';
}

function phone(value: string | null) {
  return !value?.trim() || phonePattern.test(value.trim())
    ? ''
    : 'Введите телефон полностью: +7 (ХХХ) ХХХ-ХХ-ХХ.';
}

function userProfile(value: UserProfileValidationInput): UserProfileErrors {
  const errors: UserProfileErrors = {};
  const nameError = firstName(value.firstName);
  const emailError = email(value.email);
  const phoneError = phone(value.phone);

  if (nameError) errors.firstName = nameError;

  if (emailError) errors.email = emailError;

  if (phoneError) errors.phone = phoneError;

  return errors;
}

const client = { firstName, email, phone, userProfile };

export default function useValidation() {
  return client;
}
