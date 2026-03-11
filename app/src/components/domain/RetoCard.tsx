import type { RetoWithEntry } from '@/types';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { StatusBadge } from './StatusBadge';
import { ProgressBar } from '@/components/ui/ProgressBar';
import { FileText, ExternalLink, RotateCcw } from 'lucide-react';
import { isEditable, canReopen } from '@/utils/estado';

interface RetoCardProps {
  reto: RetoWithEntry;
  onFillForm?: (retoId: number) => void;
  onReopen?: (retoId: number) => void;
  onViewFeedback?: (notes: string) => void;
}

export function RetoCard({ reto, onFillForm, onReopen, onViewFeedback }: RetoCardProps) {
  const entry = reto.entry;
  const estado = entry?.estado ?? 'no_iniciado';
  const puntaje = entry?.puntaje ?? 0;

  return (
    <Card hoverable>
      <div style={{ display: 'flex', gap: 'var(--gnf-space-4)', marginBottom: 'var(--gnf-space-4)' }}>
        {reto.iconUrl && (
          <img
            src={reto.iconUrl}
            alt=""
            style={{
              width: 56,
              height: 56,
              borderRadius: 'var(--gnf-radius)',
              objectFit: 'cover',
              flexShrink: 0,
            }}
          />
        )}
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-3)', flexWrap: 'wrap' }}>
            <h4 style={{ margin: 0, color: reto.color || 'var(--gnf-gray-900)' }}>{reto.titulo}</h4>
            <StatusBadge estado={estado} />
            {reto.obligatorio && (
              <span style={{ fontSize: '0.6875rem', color: 'var(--gnf-ocean)', fontWeight: 600 }}>OBLIGATORIO</span>
            )}
          </div>
          <p style={{ color: 'var(--gnf-muted)', fontSize: '0.8125rem', margin: 'var(--gnf-space-2) 0 0' }}>
            {reto.descripcion}
          </p>
        </div>
      </div>

      <div style={{ marginBottom: 'var(--gnf-space-4)' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 'var(--gnf-space-1)' }}>
          <span style={{ fontSize: '0.8125rem', color: 'var(--gnf-muted)' }}>Puntaje</span>
          <span style={{ fontSize: '0.8125rem', fontWeight: 600 }}>
            {puntaje} / {reto.puntajeMaximo}
          </span>
        </div>
        <ProgressBar value={puntaje} max={reto.puntajeMaximo} />
      </div>

      {entry?.supervisorNotes && estado === 'correccion' && (
        <div
          style={{
            padding: 'var(--gnf-space-3)',
            background: 'var(--gnf-coral-light)',
            borderRadius: 'var(--gnf-radius-sm)',
            fontSize: '0.8125rem',
            color: '#991b1b',
            marginBottom: 'var(--gnf-space-4)',
          }}
        >
          {entry.supervisorNotes}
        </div>
      )}

      <div style={{ display: 'flex', gap: 'var(--gnf-space-2)', flexWrap: 'wrap' }}>
        {isEditable(estado) && onFillForm && (
          <Button size="sm" icon={<FileText size={14} />} onClick={() => onFillForm(reto.id)}>
            {estado === 'en_progreso' ? 'Continuar' : 'Llenar formulario'}
          </Button>
        )}
        {canReopen(estado) && onReopen && (
          <Button variant="outline" size="sm" icon={<RotateCcw size={14} />} onClick={() => onReopen(reto.id)}>
            Reabrir y corregir
          </Button>
        )}
        {entry?.supervisorNotes && onViewFeedback && (
          <Button variant="ghost" size="sm" onClick={() => onViewFeedback(entry.supervisorNotes!)}>
            Ver feedback
          </Button>
        )}
        {reto.pdfUrl && (
          <a href={reto.pdfUrl} target="_blank" rel="noopener noreferrer" style={{ textDecoration: 'none' }}>
            <Button variant="ghost" size="sm" icon={<ExternalLink size={14} />}>
              Ver PDF
            </Button>
          </a>
        )}
      </div>
    </Card>
  );
}
