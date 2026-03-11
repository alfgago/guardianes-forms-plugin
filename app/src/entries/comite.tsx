import { StrictMode, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ToastProvider } from '@/components/ui/Toast';
import { ComitePanel } from '@/panels/comite/ComitePanel';
import { useInitData } from '@/hooks/useInitData';
import { useAuthStore } from '@/stores/useAuthStore';
import { useYearStore } from '@/stores/useYearStore';
import '@/styles/base.css';
import '@/styles/components.css';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { retry: 1, staleTime: 30_000 },
  },
});

function App() {
  const initData = useInitData('comite');
  const setUser = useAuthStore((s) => s.setUser);
  const initYear = useYearStore((s) => s.init);

  useEffect(() => {
    if (initData.user) setUser(initData.user);
    if (initData.anio) initYear(initData.anio, initData.availableYears as number[] | undefined);
  }, [initData, setUser, initYear]);

  return <ComitePanel />;
}

const root = document.getElementById('gnf-comite-root');
if (root) {
  createRoot(root).render(
    <StrictMode>
      <QueryClientProvider client={queryClient}>
        <ToastProvider>
          <App />
        </ToastProvider>
      </QueryClientProvider>
    </StrictMode>,
  );
}
