import { useEffect, useRef } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { notificationsApi } from '@/api/notifications';
import { applyPanelRevision, configurePanelQueryCache, PANEL_REVISION_EVENT, type PanelRevision } from '@/api/panel-cache';
import { useNotificationStore } from '@/stores/useNotificationStore';

export function useBootstrapNotifications() {
  const client = useQueryClient();
  configurePanelQueryCache(client);
  const versions = useRef<Partial<Record<PanelRevision['kind'], string>>>({});
  const setSummary = useNotificationStore((s) => s.setSummary);
  const notificationVersion = useRef<string>();

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
    queryKey: ['notifications', 'summary'],
    queryFn: ({ signal }) => notificationsApi.getSummary(signal),
    refetchInterval: 60_000,
    refetchOnWindowFocus: false,
  });

  useEffect(() => {
    if (query.data) {
      setSummary(query.data);
      const previous = notificationVersion.current;
      notificationVersion.current = query.data.version;
      if (previous !== undefined && previous !== query.data.version) {
        client.invalidateQueries({ queryKey: ['notifications', 'list'] });
      }
    }
  }, [query.data, setSummary, client]);

  return query;
}
