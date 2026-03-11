import { get, post } from './client';
import type { User } from '@/types';

interface LoginPayload {
  username: string;
  password: string;
}

interface RegisterDocentePayload {
  nombre: string;
  email: string;
  password: string;
  centroId?: number;
  centroNombre?: string;
  centroCodigoMep?: string;
  centroDireccion?: string;
  centroProvincia?: string;
  centroCanton?: string;
  centroNivelEducativo?: string;
  centroDependencia?: string;
}

interface RegisterSupervisorPayload {
  nombre: string;
  email: string;
  password: string;
  regionId: number;
  cargo?: string;
  telefono?: string;
}

interface AuthResponse {
  user: User;
  redirectUrl?: string;
}

export const authApi = {
  login(data: LoginPayload) {
    return post<AuthResponse>('/auth/login', data);
  },

  registerDocente(data: RegisterDocentePayload) {
    return post<AuthResponse>('/auth/register/docente', data);
  },

  registerSupervisor(data: RegisterSupervisorPayload) {
    return post<AuthResponse>('/auth/register/supervisor', data);
  },

  me() {
    return get<User>('/auth/me');
  },

  logout() {
    return post<void>('/auth/logout');
  },
};
