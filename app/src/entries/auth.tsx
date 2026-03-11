import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ToastProvider } from '@/components/ui/Toast';
import { AuthPanel } from '@/panels/auth/AuthPanel';
import '@/styles/base.css';
import '@/styles/components.css';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { retry: 1, staleTime: 30_000 },
  },
});

const root = document.getElementById('gnf-auth-root');
if (root) {
  createRoot(root).render(
    <StrictMode>
      <QueryClientProvider client={queryClient}>
        <ToastProvider>
          <AuthPanel />
        </ToastProvider>
      </QueryClientProvider>
    </StrictMode>,
  );
}
