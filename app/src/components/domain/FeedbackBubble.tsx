import { useCallback, useEffect, useRef, useState } from 'react';
import { CircleCheckBig, MessageCircle } from 'lucide-react';
import { Modal } from '@/components/ui/Modal';
import { Spinner } from '@/components/ui/Spinner';

interface FeedbackBubbleProps {
  enabled: boolean;
  url: string;
}

type FeedbackState = 'closed' | 'form' | 'success';

const INITIAL_FRAME_HEIGHT = 480;

export function FeedbackBubble({ enabled, url }: FeedbackBubbleProps) {
  const [state, setState] = useState<FeedbackState>('closed');
  const [frameLoaded, setFrameLoaded] = useState(false);
  const [frameHeight, setFrameHeight] = useState(INITIAL_FRAME_HEIGHT);
  const iframeRef = useRef<HTMLIFrameElement>(null);
  const observersCleanupRef = useRef<(() => void) | null>(null);
  const closeTimerRef = useRef<number | null>(null);

  // Se dispara en cada navegacion del iframe (carga inicial y envios sin AJAX).
  const handleFrameLoad = useCallback(() => {
    setFrameLoaded(true);

    observersCleanupRef.current?.();
    observersCleanupRef.current = null;

    let doc: Document | null = null;
    try {
      doc = iframeRef.current?.contentDocument ?? null;
    } catch {
      doc = null;
    }
    if (!doc || !doc.body) {
      // Override cross-origin en settings: sin acceso al documento, altura fija por viewport.
      setFrameHeight(Math.round(window.innerHeight * 0.7));
      return;
    }
    const frameDoc = doc;

    const measure = () => {
      const body = frameDoc.body;
      if (!body) return;
      const next = Math.max(body.scrollHeight, body.offsetHeight);
      if (next > 0) {
        setFrameHeight((prev) => (Math.abs(prev - next) > 2 ? next : prev));
      }
    };
    const detectConfirmation = () => {
      if (frameDoc.querySelector('.wpforms-confirmation-container-full, .wpforms-confirmation-container')) {
        setState('success');
      }
    };

    measure();
    detectConfirmation();

    const resizeObserver = new ResizeObserver(measure);
    resizeObserver.observe(frameDoc.documentElement);
    resizeObserver.observe(frameDoc.body);

    const mutationObserver = new MutationObserver(() => {
      measure();
      detectConfirmation();
    });
    mutationObserver.observe(frameDoc.body, { childList: true, subtree: true });

    observersCleanupRef.current = () => {
      resizeObserver.disconnect();
      mutationObserver.disconnect();
    };
  }, []);

  useEffect(() => {
    if (state === 'form') return;
    observersCleanupRef.current?.();
    observersCleanupRef.current = null;
    setFrameLoaded(false);
    setFrameHeight(INITIAL_FRAME_HEIGHT);
  }, [state]);

  useEffect(() => () => observersCleanupRef.current?.(), []);

  useEffect(() => {
    if (state !== 'success') return;

    closeTimerRef.current = window.setTimeout(() => setState('closed'), 2500);
    return () => {
      if (closeTimerRef.current !== null) {
        window.clearTimeout(closeTimerRef.current);
        closeTimerRef.current = null;
      }
    };
  }, [state]);

  if (!enabled || !url) return null;

  const close = () => {
    if (closeTimerRef.current !== null) {
      window.clearTimeout(closeTimerRef.current);
      closeTimerRef.current = null;
    }
    setState('closed');
  };

  return (
    <>
      <button
        type="button"
        className={`gnf-feedback-bubble${document.getElementById('gnf-impersonate-bar') ? ' gnf-feedback-bubble--impersonating' : ''}`}
        onClick={() => setState('form')}
        aria-label="Déjanos tu retroalimentación"
      >
        <MessageCircle size={20} aria-hidden="true" />
        <span>Déjanos tu retroalimentación</span>
      </button>

      <Modal
        open={state !== 'closed'}
        onClose={close}
        title={state === 'success' ? undefined : 'Déjanos tu retroalimentación'}
        width="680px"
        bodyPadding={state === 'success' ? undefined : '0'}
      >
        {state === 'success' ? (
          <div className="gnf-feedback-success" role="status">
            <CircleCheckBig size={54} aria-hidden="true" />
            <strong>Tu retroalimentación ha sido recibida</strong>
          </div>
        ) : (
          <div className="gnf-feedback-form">
            {!frameLoaded && (
              <div className="gnf-feedback-frame-loading">
                <Spinner />
              </div>
            )}
            <iframe
              ref={iframeRef}
              src={url}
              title="Déjanos tu retroalimentación"
              className="gnf-feedback-frame"
              style={{ height: `${frameHeight}px`, opacity: frameLoaded ? 1 : 0 }}
              onLoad={handleFrameLoad}
            />
          </div>
        )}
      </Modal>
    </>
  );
}
