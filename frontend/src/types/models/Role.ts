export type Role =
  | 'ROLE_ADMIN'
  | 'ROLE_SUPER_ADMIN'
  | 'ROLE_ACCOUNTANT'
  | 'ROLE_TECHNICIAN'
  | 'ROLE_LAWYER'
  | 'ROLE_DIRECTOR';
export interface RoleDefinition {
  code: Role;
  label: string;
  description: string;
  assignable: boolean;
}
