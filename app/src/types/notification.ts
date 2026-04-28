export type NotificationType =
  | 'correccion'
  | 'aprobado'
  | 'invalid_photo_date'
  | 'evidencia_subida'
  | 'evidencia_resubida'
  | 'evidencia_aprobada'
  | 'evidencia_rechazada'
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

export interface NotificationEvidenceItem {
  evidenceIndex: number;
  fieldId?: number;
  questionLabel: string;
  fileName?: string;
  previewUrl?: string;
  tipo?: string;
  isImage?: boolean;
  estado?: 'pendiente' | 'aprobada' | 'rechazada' | string;
  puntos?: number | null;
  supervisorComment?: string | null;
  reviewedBy?: number | null;
  reviewedAt?: string | null;
  photoDate?: string | null;
  requiresYearValidation?: boolean;
  canReview?: boolean;
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
  hasRejectedEvidence?: boolean;
  evidenceItems?: NotificationEvidenceItem[];
  requiresYearValidation?: boolean;
}
