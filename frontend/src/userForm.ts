import type { UserForm } from './types/forms/User';

/** Canonical values shared by change detection and the profile request. */
export function userFormPayload(form: UserForm) {
  const roles = new Set(form.roles);

  if (roles.has('ROLE_SUPER_ADMIN')) roles.add('ROLE_ADMIN');

  return {
    email: form.email.trim().toLowerCase(),
    firstName: form.firstName?.trim() ?? '',
    lastName: form.lastName?.trim() || null,
    phone: form.phone?.trim() || null,
    position: form.position?.trim() || null,
    roles: [...roles].sort(),
    blocked: form.blocked,
  };
}
