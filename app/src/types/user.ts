export type UserRole = 'docente' | 'supervisor' | 'comite_bae' | 'administrator';

export interface User {
  id: number;
  name: string;
  email: string;
  roles: UserRole[];
  regionId?: number;
  regionIds?: number[];
  regionNames?: string[];
  centroId?: number;
  estado?: 'activo' | 'pendiente' | 'rechazado';
}

export interface PendingUser {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  status?: 'activo' | 'pendiente' | 'rechazado';
  telefono?: string;
  cargo?: string;
  identificacion?: string;
  centroId?: number;
  regionId?: number;
  regionIds?: number[];
  registeredAt: string;
  centroName?: string;
  regionName?: string;
  regionNames?: string[];
  panelUrl?: string;
  canImpersonate?: boolean;
  impersonateUrl?: string;
}
