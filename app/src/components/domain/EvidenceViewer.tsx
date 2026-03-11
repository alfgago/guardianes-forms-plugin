import { useState } from 'react';
import { Modal } from '@/components/ui/Modal';
import { ChevronLeft, ChevronRight, Download, ZoomIn } from 'lucide-react';
import type { Evidencia } from '@/types';

interface EvidenceViewerProps {
  evidencias: Evidencia[];
}

export function EvidenceViewer({ evidencias }: EvidenceViewerProps) {
  const [selectedIndex, setSelectedIndex] = useState<number | null>(null);
  const selected = selectedIndex !== null ? evidencias[selectedIndex] : null;

  if (evidencias.length === 0) return null;

  const images = evidencias.filter((e) => e.type === 'imagen');
  const others = evidencias.filter((e) => e.type !== 'imagen');

  return (
    <div>
      {images.length > 0 && (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(100px, 1fr))', gap: 'var(--gnf-space-2)', marginBottom: 'var(--gnf-space-4)' }}>
          {images.map((ev, i) => (
            <div
              key={i}
              onClick={() => setSelectedIndex(evidencias.indexOf(ev))}
              style={{
                position: 'relative',
                aspectRatio: '1',
                borderRadius: 'var(--gnf-radius-sm)',
                overflow: 'hidden',
                cursor: 'pointer',
                border: '1px solid var(--gnf-border)',
              }}
            >
              <img
                src={ev.url}
                alt={ev.filename}
                style={{ width: '100%', height: '100%', objectFit: 'cover' }}
              />
              <div
                style={{
                  position: 'absolute',
                  inset: 0,
                  background: 'rgba(0,0,0,0.3)',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  opacity: 0,
                  transition: 'opacity var(--gnf-transition-fast)',
                }}
                onMouseEnter={(e) => { e.currentTarget.style.opacity = '1'; }}
                onMouseLeave={(e) => { e.currentTarget.style.opacity = '0'; }}
              >
                <ZoomIn size={24} color="white" />
              </div>
            </div>
          ))}
        </div>
      )}

      {others.length > 0 && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--gnf-space-2)' }}>
          {others.map((ev, i) => (
            <a
              key={i}
              href={ev.url}
              target="_blank"
              rel="noopener noreferrer"
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: 'var(--gnf-space-2)',
                padding: 'var(--gnf-space-2) var(--gnf-space-3)',
                background: 'var(--gnf-gray-50)',
                borderRadius: 'var(--gnf-radius-sm)',
                fontSize: '0.8125rem',
                color: 'var(--gnf-ocean)',
              }}
            >
              <Download size={14} />
              {ev.filename}
            </a>
          ))}
        </div>
      )}

      <Modal
        open={selected !== null}
        onClose={() => setSelectedIndex(null)}
        title={selected?.filename}
        width="800px"
      >
        {selected?.type === 'imagen' && (
          <div style={{ textAlign: 'center' }}>
            <img
              src={selected.url}
              alt={selected.filename}
              style={{ maxWidth: '100%', maxHeight: '70vh', borderRadius: 'var(--gnf-radius)' }}
            />
            <div style={{ display: 'flex', justifyContent: 'center', gap: 'var(--gnf-space-4)', marginTop: 'var(--gnf-space-4)' }}>
              <button
                disabled={selectedIndex === 0}
                onClick={() => setSelectedIndex((i) => (i !== null && i > 0 ? i - 1 : i))}
                style={{ border: 'none', background: 'none', cursor: 'pointer', opacity: selectedIndex === 0 ? 0.3 : 1 }}
              >
                <ChevronLeft size={24} />
              </button>
              <span style={{ fontSize: '0.875rem', color: 'var(--gnf-muted)' }}>
                {(selectedIndex ?? 0) + 1} / {evidencias.length}
              </span>
              <button
                disabled={selectedIndex === evidencias.length - 1}
                onClick={() => setSelectedIndex((i) => (i !== null && i < evidencias.length - 1 ? i + 1 : i))}
                style={{ border: 'none', background: 'none', cursor: 'pointer', opacity: selectedIndex === evidencias.length - 1 ? 0.3 : 1 }}
              >
                <ChevronRight size={24} />
              </button>
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
}
