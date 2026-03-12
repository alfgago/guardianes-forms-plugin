import { get, post, put } from './client';
import type { CentroWithStats, DashboardStats, PendingUser } from '@/types';

interface AdminStats extends DashboardStats {
  totalUsers: number;
  pendingUsers: number;
}

interface AdminRetoStats {
  id: number;
  titulo: string;
  total: number;
  aprobados: number;
  enviados: number;
  correccion: number;
  enProgreso: number;
}

interface ReportData {
  centros: CentroWithStats[];
  summary: {
    totalCentros: number;
    totalAprobados: number;
    promedioEstrellas: number;
    promedioPuntaje: number;
  };
}

interface Dre {
  id: number;
  name: string;
  slug: string;
  enabled: boolean;
}

interface AuditLog {
  id: number;
  event_key: string;
  actor_user_id?: number;
  actor_role?: string;
  target_user_id?: number;
  centro_id?: number;
  reto_id?: number;
  anio: number;
  panel?: string;
  message?: string;
  meta?: Record<string, unknown>;
  created_at: string;
  actorName?: string;
  targetName?: string;
  centroName?: string;
  retoTitle?: string;
}

interface UpdateUserPayload {
  name: string;
  email: string;
  status?: 'activo' | 'pendiente';
  telefono?: string;
  cargo?: string;
  identificacion?: string;
  centroId?: number;
  regionId?: number;
}

export const adminApi = {
  getStats(year: number) {
    return get<AdminStats>('/admin/stats', { year });
  },

  getUsers() {
    return get<PendingUser[]>('/admin/users');
  },

  getPendingUsers() {
    return get<PendingUser[]>('/admin/users/pending');
  },

  approveUser(userId: number) {
    return post<{ success: boolean }>(`/admin/users/${userId}/approve`);
  },

  rejectUser(userId: number) {
    return post<{ success: boolean }>(`/admin/users/${userId}/reject`);
  },

  updateUser(userId: number, data: UpdateUserPayload) {
    return put<PendingUser>(`/admin/users/${userId}`, data);
  },

  getCentros(year: number, region?: string, search?: string) {
    return get<CentroWithStats[]>('/admin/centros', { year, region, s: search });
  },

  approveCentro(centroId: number) {
    return post<{ success: boolean }>(`/admin/centros/${centroId}/approve`);
  },

  rejectCentro(centroId: number) {
    return post<{ success: boolean }>(`/admin/centros/${centroId}/reject`);
  },

  getRetos(year: number) {
    return get<AdminRetoStats[]>('/admin/retos', { year });
  },

  getReports(year: number) {
    return get<ReportData>('/admin/reports', { year });
  },

  getDres() {
    return get<Dre[]>('/admin/dre');
  },

  toggleDre(dreId: number) {
    return post<{ success: boolean; enabled: boolean }>(`/admin/dre/${dreId}/toggle`);
  },

  getAuditLogs(year?: number) {
    return get<AuditLog[]>('/admin/audit-logs', { year, limit: 150 });
  },
};
