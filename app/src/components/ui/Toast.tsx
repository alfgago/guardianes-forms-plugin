import { useEffect, useState, useCallback, createContext, useContext, type ReactNode } from 'react';
import { CheckCircle2, AlertCircle, Info, X } from 'lucide-react';

type ToastType = 'success' | 'error' | 'info';

interface ToastItem {
  id: number;
  type: ToastType;
  message: string;
  exiting?: boolean;
}

interface ToastContextValue {
  toast: (type: ToastType, message: string) => void;
}

const ToastContext = createContext<ToastContextValue>({ toast: () => {} });

export function useToast() {
  return useContext(ToastContext);
}

let nextId = 0;

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<ToastItem[]>([]);

  const toast = useCallback((type: ToastType, message: string) => {
    const id = ++nextId;
    setToasts((prev) => [...prev, { id, type, message }]);
    setTimeout(() => {
      setToasts((prev) => prev.map((t) => (t.id === id ? { ...t, exiting: true } : t)));
      setTimeout(() => {
        setToasts((prev) => prev.filter((t) => t.id !== id));
      }, 300);
    }, 4000);
  }, []);

  const dismiss = useCallback((id: number) => {
    setToasts((prev) => prev.map((t) => (t.id === id ? { ...t, exiting: true } : t)));
    setTimeout(() => {
      setToasts((prev) => prev.filter((t) => t.id !== id));
    }, 300);
  }, []);

  return (
    <ToastContext.Provider value={{ toast }}>
      {children}
      <div className="gnf-toast-container">
        {toasts.map((t) => (
          <ToastMessage key={t.id} item={t} onDismiss={dismiss} />
        ))}
      </div>
    </ToastContext.Provider>
  );
}

const icons: Record<ToastType, typeof Info> = {
  success: CheckCircle2,
  error: AlertCircle,
  info: Info,
};

const colors: Record<ToastType, { border: string; icon: string }> = {
  success: { border: '#22c55e', icon: '#16a34a' },
  error: { border: 'var(--gnf-coral)', icon: '#dc2626' },
  info: { border: '#0ea5e9', icon: '#0369a1' },
};

function ToastMessage({ item, onDismiss }: { item: ToastItem; onDismiss: (id: number) => void }) {
  const Icon = icons[item.type];
  const c = colors[item.type];

  const [mounted, setMounted] = useState(false);
  useEffect(() => { setMounted(true); }, []);

  return (
    <div
      role="alert"
      style={{
        display: 'flex',
        alignItems: 'center',
        gap: 'var(--gnf-space-3)',
        padding: 'var(--gnf-space-3) var(--gnf-space-4)',
        background: 'var(--gnf-white)',
        border: '1px solid var(--gnf-border)',
        borderLeft: `4px solid ${c.border}`,
        borderRadius: 'var(--gnf-radius)',
        boxShadow: 'var(--gnf-shadow-md)',
        minWidth: 300,
        maxWidth: 420,
        animation: !mounted
          ? 'gnf-slide-in-right 300ms ease-out'
          : item.exiting
            ? 'gnf-slide-out-right 300ms ease-in forwards'
            : undefined,
      }}
    >
      <Icon size={20} color={c.icon} style={{ flexShrink: 0 }} />
      <span style={{ flex: 1, fontSize: '0.875rem', color: 'var(--gnf-gray-800)' }}>{item.message}</span>
      <button
        onClick={() => onDismiss(item.id)}
        style={{
          display: 'flex',
          border: 'none',
          background: 'none',
          cursor: 'pointer',
          padding: 2,
          color: 'var(--gnf-gray-500)',
        }}
      >
        <X size={16} />
      </button>
    </div>
  );
}
