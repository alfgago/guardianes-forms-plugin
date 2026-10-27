import { create } from 'zustand';
import type { Notification } from '@/types';
import type { NotificationSummary } from '@/api/notifications';

interface NotificationState {
  notifications: Notification[];
  unreadCount: number;
  setSummary: (summary: NotificationSummary) => void;
  setNotifications: (notifications: Notification[]) => void;
  markRead: (id: number) => void;
  markAllRead: () => void;
}

export const useNotificationStore = create<NotificationState>((set) => ({
  notifications: [],
  unreadCount: 0,
  setSummary: (summary) => set({ unreadCount: summary.unreadCount }),
  setNotifications: (notifications) =>
    set({
      notifications,
      unreadCount: notifications.filter((n) => !n.leido).length,
    }),
  markRead: (id) =>
    set((state) => {
      const wasUnread = state.notifications.some((n) => n.id === id && !n.leido);
      const updated = state.notifications.map((n) =>
        n.id === id ? { ...n, leido: true } : n,
      );
      return {
        notifications: updated,
        unreadCount: Math.max(0, state.unreadCount - (wasUnread ? 1 : 0)),
      };
    }),
  markAllRead: () =>
    set((state) => ({
      notifications: state.notifications.map((n) => ({ ...n, leido: true })),
      unreadCount: 0,
    })),
}));
