import { get, put } from './client';
import type { Notification } from '@/types';

export const notificationsApi = {
  getAll() {
    return get<Notification[]>('/notifications');
  },

  markAsRead(id: number) {
    return put<{ success: boolean }>(`/notifications/${id}/read`);
  },
};
