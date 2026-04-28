export type Estado = 'no_iniciado' | 'en_progreso' | 'completo' | 'enviado' | 'aprobado' | 'correccion' | 'sin_evidencias';

export interface Reto {
  id: number;
  titulo: string;
  descripcion: string;
  color?: string;
  iconUrl?: string;
  pdfUrl?: string;
  puntajeMaximo: number;
  obligatorio: boolean;
  parentId?: number;
  formId?: number;
}

export interface RetoEntry {
  id: number;
  retoId: number;
  centroId: number;
  userId: number;
  anio: number;
  estado: Estado;
  puntaje: number;
  puntajeMaximo: number;
  supervisorNotes?: string;
  data?: Record<string, unknown>;
  evidencias?: Evidencia[];
  responses?: RetoEntryResponse[];
  createdAt: string;
  updatedAt: string;
}

export interface RetoWithEntry extends Reto {
  entry?: RetoEntry;
}

export interface Evidencia {
  url?: string;
  ruta?: string;
  filename?: string;
  nombre?: string;
  type?: 'imagen' | 'video' | 'pdf' | 'documento';
  tipo?: 'imagen' | 'video' | 'pdf' | 'documento' | 'archivo';
  size?: number;
  field_id?: number;
  puntos?: number | null;
  estado?: 'pendiente' | 'aprobada' | 'rechazada' | null;
  supervisor_comment?: string | null;
  reviewed_by?: number | null;
  reviewed_at?: string | null;
  replaced?: boolean;
  photo_date?: string | null;
  // Legacy fields (may exist in old data)
  requires_year_validation?: boolean;
  warning?: string;
  exifYear?: number;
}

export interface RetoEntryResponse {
  fieldId: number;
  label: string;
  type: string;
  displayValue: string;
  hasValue: boolean;
  puntos: number;
  evidencias: Evidencia[];
}
