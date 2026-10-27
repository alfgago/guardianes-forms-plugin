import { get, put } from './client';
import type { Notification } from '@/types';

export interface NotificationSummary {
  unreadCount: number;
  total: number;
  version: string;
}

export const notificationsApi = {
  getSummary(signal?: AbortSignal) {
    return get<NotificationSummary>('/notifications/summary', undefined, signal);
  },

  getAll(signal?: AbortSignal) {
    return get<Notification[]>('/notifications', undefined, signal);
  },

  markAsRead(id: number) {
    return put<{ success: boolean }>(`/notifications/${id}/read`);
  },

  markAllAsRead() {
    return put<{ success: boolean }>('/notifications/read-all');
  },
};
