import { useEffect, useId, useRef, type ReactNode } from 'react';
import { X } from 'lucide-react';

interface ModalProps {
  open: boolean;
  onClose: () => void;
  title?: string;
  children: ReactNode;
  width?: string;
  bodyPadding?: string;
  ariaLabel?: string;
}

const focusableSelector = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(',');

export function Modal({ open, onClose, title, children, width = '560px', bodyPadding = 'var(--gnf-space-6)', ariaLabel }: ModalProps) {
  const overlayRef = useRef<HTMLDivElement>(null);
  const dialogRef = useRef<HTMLDivElement>(null);
  const previousActiveElement = useRef<HTMLElement | null>(null);
  const titleId = useId();

  useEffect(() => {
    if (!open) return;

    previousActiveElement.current = document.activeElement instanceof HTMLElement
      ? document.activeElement
      : null;
    const previousOverflow = document.body.style.overflow;
    const focusFrame = window.requestAnimationFrame(() => {
      const firstFocusable = dialogRef.current?.querySelector<HTMLElement>(focusableSelector);
      (firstFocusable ?? dialogRef.current)?.focus();
    });

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        onClose();
        return;
      }

      if (event.key === 'Tab') {
        const focusableElements = Array.from(
          dialogRef.current?.querySelectorAll<HTMLElement>(focusableSelector) ?? [],
        ).filter((element) => element.offsetParent !== null);

        if (focusableElements.length === 0) {
          event.preventDefault();
          dialogRef.current?.focus();
          return;
        }

        const firstElement = focusableElements[0]!;
        const lastElement = focusableElements[focusableElements.length - 1]!;
        if (event.shiftKey && document.activeElement === firstElement) {
          event.preventDefault();
          lastElement.focus();
        } else if (!event.shiftKey && document.activeElement === lastElement) {
          event.preventDefault();
          firstElement.focus();
        }
      }
    };
    document.addEventListener('keydown', handleKeyDown);
    document.body.style.overflow = 'hidden';

    return () => {
      window.cancelAnimationFrame(focusFrame);
      document.removeEventListener('keydown', handleKeyDown);
      document.body.style.overflow = previousOverflow;
      previousActiveElement.current?.focus();
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
        ref={dialogRef}
        className="gnf-modal-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby={title ? titleId : undefined}
        aria-label={title ? undefined : (ariaLabel ?? 'Ventana de diálogo')}
        tabIndex={-1}
        style={{
          background: 'var(--gnf-white)',
          borderRadius: 'var(--gnf-radius-lg)',
          boxShadow: 'var(--gnf-shadow-lg)',
          width: '100%',
          maxWidth: width,
          maxHeight: '90vh',
          overflow: 'auto',
          overscrollBehavior: 'contain',
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
            <h3 id={titleId} style={{ margin: 0 }}>{title}</h3>
            <button
              type="button"
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
              <X size={20} aria-hidden="true" />
            </button>
          </div>
        )}
        <div style={{ padding: bodyPadding }}>{children}</div>
      </div>
    </div>
  );
}
