import useValidation from './validation.ts';
import type { RenterContactDraft } from '../types/forms/RenterContact';
import type { ApiErrors } from '../types/use/Api';

export default function useRenterContactValidation() {
  const validation = useValidation();

  function contact(value: RenterContactDraft): ApiErrors {
    const errors: ApiErrors = {};

    if (!value.name.trim()) errors.name = 'Укажите имя.';

    if (value.email.trim()) {
      const message = validation.email(value.email);

      if (message) errors.email = message;
    }

    const phone = validation.phone(value.phone);

    if (phone) errors.phone = phone;

    return errors;
  }

  return { contact };
}
