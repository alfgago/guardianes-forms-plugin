export type Estado = 'no_iniciado' | 'en_progreso' | 'completo' | 'enviado' | 'aprobado' | 'correccion';

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
  createdAt: string;
  updatedAt: string;
}

export interface RetoWithEntry extends Reto {
  entry?: RetoEntry;
}

export interface Evidencia {
  url: string;
  filename: string;
  type: 'imagen' | 'video' | 'pdf' | 'documento';
  size?: number;
  exifYear?: number;
}
