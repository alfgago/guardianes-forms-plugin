import { useEffect, useRef, type ReactNode } from 'react';
import { X } from 'lucide-react';

interface ModalProps {
  open: boolean;
  onClose: () => void;
  title?: string;
  children: ReactNode;
  width?: string;
}

export function Modal({ open, onClose, title, children, width = '560px' }: ModalProps) {
  const overlayRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const handleEsc = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', handleEsc);
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', handleEsc);
      document.body.style.overflow = '';
    };
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div
      ref={overlayRef}
      onClick={(e) => { if (e.target === overlayRef.current) onClose(); }}
      style={{
        position: 'fixed',
        inset: 0,
        background: 'rgba(0, 0, 0, 0.5)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        zIndex: 9000,
        padding: 'var(--gnf-space-4)',
      }}
    >
      <div
        role="dialog"
        aria-modal="true"
        aria-label={title}
        style={{
          background: 'var(--gnf-white)',
          borderRadius: 'var(--gnf-radius-lg)',
          boxShadow: 'var(--gnf-shadow-lg)',
          width: '100%',
          maxWidth: width,
          maxHeight: '90vh',
          overflow: 'auto',
          animation: 'gnf-fade-in 200ms ease-out',
        }}
      >
        {title && (
          <div style={{
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            padding: 'var(--gnf-space-5) var(--gnf-space-6)',
            borderBottom: '1px solid var(--gnf-border)',
          }}>
            <h3 style={{ margin: 0 }}>{title}</h3>
            <button
              onClick={onClose}
              aria-label="Cerrar"
              style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                width: 32,
                height: 32,
                border: 'none',
                background: 'none',
                cursor: 'pointer',
                borderRadius: 'var(--gnf-radius-sm)',
                color: 'var(--gnf-gray-500)',
              }}
            >
              <X size={20} />
            </button>
          </div>
        )}
        <div style={{ padding: 'var(--gnf-space-6)' }}>{children}</div>
      </div>
    </div>
  );
}
