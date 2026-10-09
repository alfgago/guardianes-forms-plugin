import type { QueryClient } from '@tanstack/react-query';

export interface PanelRevision {
  kind: 'docente' | 'supervisor';
  version: string;
}

export const PANEL_REVISION_EVENT = 'gnf-panel-revision';
const configured = new WeakSet<QueryClient>();

export function configurePanelQueryCache(client: QueryClient) {
  if (configured.has(client)) return;
  configured.add(client);
  const options = {
    staleTime: 10 * 60 * 1000,
    gcTime: 10 * 60 * 1000,
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
  };
  for (const key of ['docente-dashboard', 'docente-retos', 'wizard-steps']) {
    client.setQueryDefaults([key], options);
  }
  for (const key of ['supervisor-dashboard', 'supervisor-centros']) {
    client.setQueryDefaults([key], { ...options, refetchInterval: 2 * 60 * 60 * 1000 });
  }
}

export function applyPanelRevision(client: QueryClient, previous: string | undefined, next: PanelRevision) {
  if (!previous || previous === next.version) return;
  // Never replace a live WPForms DOM or an unsaved enrollment editor.
  const keys = next.kind === 'docente'
    ? ['docente-dashboard', 'docente-retos', 'wizard-steps']
    : ['supervisor-dashboard', 'supervisor-centros'];
  for (const key of keys) void client.invalidateQueries({ queryKey: [key] });
}
