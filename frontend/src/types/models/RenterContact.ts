export interface RenterContact {
  id: number;
  renterId: number;
  name: string;
  lastName: string | null;
  patronymic: string | null;
  phone: string | null;
  email: string | null;
}
