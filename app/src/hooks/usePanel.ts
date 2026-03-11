import { useState, useEffect, useCallback } from 'react';
import { getPage, getParam, navigateTo } from '@/utils/url';

interface PanelNav {
  page: string;
  params: Record<string, string | null>;
  navigate: (page: string, extra?: Record<string, string>) => void;
}

export function usePanel(defaultPage: string): PanelNav {
  const [page, setPage] = useState(() => getPage() || defaultPage);
  const [params, setParams] = useState<Record<string, string | null>>({});

  useEffect(() => {
    function handlePopState() {
      const newPage = getPage() || defaultPage;
      setPage(newPage);
      // Read common params
      setParams({
        centro_id: getParam('centro_id'),
        reto_id: getParam('reto_id'),
      });
    }

    window.addEventListener('popstate', handlePopState);
    // Initialize params
    handlePopState();

    return () => window.removeEventListener('popstate', handlePopState);
  }, [defaultPage]);

  const navigate = useCallback(
    (newPage: string, extra?: Record<string, string>) => {
      navigateTo(newPage, extra);
    },
    [],
  );

  return { page, params, navigate };
}
