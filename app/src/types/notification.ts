export type NotificationType = 'correccion' | 'aprobado' | 'invalid_photo_date' | 'matricula' | 'general';

export interface Notification {
  id: number;
  userId: number;
  tipo: NotificationType;
  mensaje: string;
  relacionTipo?: string;
  relacionId?: number;
  leido: boolean;
  createdAt: string;
}
