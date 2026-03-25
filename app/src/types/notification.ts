export type NotificationType =
  | 'correccion'
  | 'aprobado'
  | 'invalid_photo_date'
  | 'matricula'
  | 'general'
  | 'participacion_enviada'
  | 'reto_enviado'
  | 'feedback_actualizado'
  | 'cuenta_aprobada'
  | 'docente_aprobado'
  | 'docente_solicita_acceso'
  | 'nueva_matricula'
  | 'validado'
  | 'rechazado';

export interface NotificationActionTarget {
  page: string;
  params?: Record<string, string>;
}

export interface Notification {
  id: number;
  userId: number;
  tipo: NotificationType;
  mensaje: string;
  relacionTipo?: string;
  relacionId?: number;
  leido: boolean;
  createdAt: string;
  actionTarget?: NotificationActionTarget | null;
  actionLabel?: string;
  canReview?: boolean;
  entryId?: number;
  retoId?: number;
  retoTitulo?: string;
  centroId?: number;
  centroNombre?: string;
  regionName?: string;
  circuito?: string;
  year?: number;
  entryStatus?: string;
  requiresYearValidation?: boolean;
}
