import { useEffect, useRef } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { notificationsApi } from '@/api/notifications';
import { applyPanelRevision, configurePanelQueryCache, PANEL_REVISION_EVENT, type PanelRevision } from '@/api/panel-cache';
import { useNotificationStore } from '@/stores/useNotificationStore';

export function useBootstrapNotifications() {
  const client = useQueryClient();
  configurePanelQueryCache(client);
  const versions = useRef<Partial<Record<PanelRevision['kind'], string>>>({});
  const setNotifications = useNotificationStore((s) => s.setNotifications);

  useEffect(() => {
    function onRevision(event: Event) {
      const next = (event as CustomEvent<PanelRevision>).detail;
      const previous = versions.current[next.kind];
      versions.current[next.kind] = next.version;
      applyPanelRevision(client, previous, next);
    }
    window.addEventListener(PANEL_REVISION_EVENT, onRevision);
    return () => window.removeEventListener(PANEL_REVISION_EVENT, onRevision);
  }, [client]);

  const query = useQuery({
    queryKey: ['notifications'],
    queryFn: () => notificationsApi.getAll(),
    refetchInterval: 60_000,
    refetchOnWindowFocus: false,
  });

  useEffect(() => {
    if (query.data) {
      setNotifications(query.data);
    }
  }, [query.data, setNotifications]);

  return query;
}
