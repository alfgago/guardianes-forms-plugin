export type UserRole = 'docente' | 'supervisor' | 'comite_bae' | 'administrator';

export interface User {
  id: number;
  name: string;
  email: string;
  roles: UserRole[];
  regionId?: number;
  centroId?: number;
  estado?: 'activo' | 'pendiente';
}

export interface PendingUser {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  registeredAt: string;
  centroName?: string;
  regionName?: string;
}
