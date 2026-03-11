export interface ApiError {
  code: string;
  message: string;
  data?: {
    status: number;
    [key: string]: unknown;
  };
}

export interface PaginatedResponse<T> {
  items: T[];
  total: number;
  page: number;
  perPage: number;
  totalPages: number;
}

export interface DashboardStats {
  centros: number;
  pendientes: number;
  aprobados: number;
  correccion: number;
  enviados: number;
  enProgreso: number;
}

export interface Region {
  id: number;
  name: string;
  slug: string;
  centroCount?: number;
}
