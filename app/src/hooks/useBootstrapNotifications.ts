import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { notificationsApi } from '@/api/notifications';
import { useNotificationStore } from '@/stores/useNotificationStore';

export function useBootstrapNotifications() {
  const setNotifications = useNotificationStore((s) => s.setNotifications);

  const query = useQuery({
    queryKey: ['notifications'],
    queryFn: () => notificationsApi.getAll(),
    refetchInterval: 60_000,
  });

  useEffect(() => {
    if (query.data) {
      setNotifications(query.data);
    }
  }, [query.data, setNotifications]);

  return query;
}
