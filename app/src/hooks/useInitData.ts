import { useMemo } from 'react';
import type { User } from '@/types';

interface PanelInitData {
  restUrl: string;
  nonce: string;
  anio: number;
  pluginUrl: string;
  user: User | null;
  [key: string]: unknown;
}

/**
 * Read the window.__GNF_{PANEL}__ data injected by PHP.
 * This is called once at mount time.
 */
export function useInitData(panel: string): PanelInitData {
  return useMemo(() => {
    const key = `__GNF_${panel.toUpperCase()}__`;
    const data = (window as unknown as Record<string, unknown>)[key] as PanelInitData | undefined;
    return (
      data ?? {
        restUrl: '/wp-json/gnf/v1',
        nonce: '',
        anio: new Date().getFullYear(),
        pluginUrl: '',
        user: null,
      }
    );
  }, [panel]);
}
