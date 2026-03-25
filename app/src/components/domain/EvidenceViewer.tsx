import { useCallback, useEffect, useState } from 'react';
import { FileText, X } from 'lucide-react';
import type { Evidencia } from '@/types';

interface NormalizedEvidence {
  url: string;
  filename: string;
  isImage: boolean;
}

interface EvidenceViewerProps {
  evidencias: Evidencia[];
  onRemove?: (index: number) => void;
}

function normalize(ev: Evidencia): NormalizedEvidence {
  const url = ev.url ?? ev.ruta ?? '';
  const filename = ev.filename ?? ev.nombre ?? 'Evidencia';
  const tipo = ev.type ?? ev.tipo ?? 'documento';
  return { url, filename, isImage: tipo === 'imagen' };
}

export function EvidenceViewer({ evidencias, onRemove }: EvidenceViewerProps) {
  const [lightbox, setLightbox] = useState<NormalizedEvidence | null>(null);
  const [visible, setVisible] = useState(false);

  const items = evidencias.map(normalize);

  const openLightbox = useCallback((item: NormalizedEvidence) => {
    if (!item.isImage) {
      window.open(item.url, '_blank', 'noopener');
      return;
    }
    setLightbox(item);
    requestAnimationFrame(() => setVisible(true));
  }, []);

  const closeLightbox = useCallback(() => {
    setVisible(false);
    setTimeout(() => setLightbox(null), 200);
  }, []);

  useEffect(() => {
    if (!lightbox) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') closeLightbox();
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [lightbox, closeLightbox]);

  if (items.length === 0) return null;

  return (
    <div className="gnf-ev">
      <div className="gnf-ev-grid">
        {items.map((item, i) => (
          <div key={i} className={`gnf-ev-item ${item.isImage ? '' : 'gnf-ev-item--file'}`}>
            {onRemove && (
              <button
                className="gnf-ev-remove"
                onClick={(e) => { e.stopPropagation(); onRemove(i); }}
                aria-label="Eliminar"
              >
                <X size={12} />
              </button>
            )}

            {item.isImage ? (
              <div className="gnf-ev-thumb" onClick={() => openLightbox(item)}>
                <img src={item.url} alt={item.filename} />
              </div>
            ) : (
              <div className="gnf-ev-archivo" onClick={() => openLightbox(item)}>
                <FileText size={28} />
                <span className="gnf-ev-archivo__label">ARCHIVO</span>
              </div>
            )}

            <span className={`gnf-ev-name ${item.isImage ? 'gnf-ev-name--truncate' : ''}`}>
              {item.filename}
            </span>
          </div>
        ))}
      </div>

      {lightbox && (
        <div
          className={`gnf-ev-lightbox ${visible ? 'gnf-ev-lightbox--visible' : ''}`}
          onClick={closeLightbox}
        >
          <div className="gnf-ev-lightbox__card" onClick={(e) => e.stopPropagation()}>
            <button className="gnf-ev-lightbox__close" onClick={closeLightbox}>
              <X size={18} />
            </button>
            <img
              className="gnf-ev-lightbox__img"
              src={lightbox.url}
              alt={lightbox.filename}
            />
            <span className="gnf-ev-lightbox__name">{lightbox.filename}</span>
          </div>
        </div>
      )}
    </div>
  );
}
