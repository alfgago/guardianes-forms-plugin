import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Textarea } from '@/components/ui/Textarea';
import { StatusBadge } from '@/components/domain/StatusBadge';
import { EvidenceViewer } from '@/components/domain/EvidenceViewer';
import { ProgressBar } from '@/components/ui/ProgressBar';
import { Alert } from '@/components/ui/Alert';
import { useToast } from '@/components/ui/Toast';
import { supervisorApi } from '@/api/supervisor';
import { CheckCircle2, MessageSquare, RotateCcw, Upload } from 'lucide-react';
import type { RetoEntry, Evidencia, RetoEntryResponse } from '@/types';

interface EntryReviewCardProps {
  entry: RetoEntry & { retoTitulo: string; retoColor: string; retoIconUrl?: string };
  year: number;
}

export function EntryReviewCard({ entry, year }: EntryReviewCardProps) {
  const [showNotes, setShowNotes] = useState(false);
  const [notes, setNotes] = useState('');
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const mutation = useMutation({
    mutationFn: (action: 'aprobar' | 'correccion') =>
      supervisorApi.updateEntry(entry.id, { action, notes: action === 'correccion' ? notes : undefined }),
    onSuccess: (_, action) => {
      toast('success', action === 'aprobar' ? 'Reto aprobado.' : 'Correccion solicitada.');
      queryClient.invalidateQueries({ queryKey: ['supervisor-centro'] });
      queryClient.invalidateQueries({ queryKey: ['supervisor-centros', year] });
      queryClient.invalidateQueries({ queryKey: ['admin-centros', year] });
      queryClient.invalidateQueries({ queryKey: ['admin-stats', year] });
      queryClient.invalidateQueries({ queryKey: ['admin-reports', year] });
      setShowNotes(false);
      setNotes('');
    },
    onError: () => toast('error', 'Error al actualizar.'),
  });

  const isReviewable = entry.estado === 'enviado';
  const responses = entry.responses ?? [];
  const orphanEvidence = (entry.evidencias ?? []).filter(
    (evidence) => !responses.some((response) => response.fieldId === (evidence.field_id ?? 0)),
  );
  const hasYearValidationWarning = (entry.evidencias ?? []).some((evidence) => evidence.requires_year_validation);

  return (
    <Card style={{ marginBottom: 'var(--gnf-space-4)' }}>
      <div style={{ display: 'flex', gap: 'var(--gnf-space-4)', marginBottom: 'var(--gnf-space-4)' }}>
        {entry.retoIconUrl && (
          <img src={entry.retoIconUrl} alt="" style={{ width: 48, height: 48, borderRadius: 'var(--gnf-radius-sm)', objectFit: 'cover' }} />
        )}
        <div style={{ flex: 1 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-3)' }}>
            <h4 style={{ margin: 0, color: entry.retoColor }}>{entry.retoTitulo}</h4>
            <StatusBadge estado={entry.estado} />
          </div>
          <div style={{ marginTop: 'var(--gnf-space-2)' }}>
            <ProgressBar value={entry.puntaje} max={entry.puntajeMaximo} height={6} />
            <span style={{ fontSize: '0.75rem', color: 'var(--gnf-muted)' }}>
              {entry.puntaje} / {entry.puntajeMaximo} eco puntos
            </span>
          </div>
        </div>
      </div>

      {hasYearValidationWarning && (
        <div style={{ marginBottom: 'var(--gnf-space-4)' }}>
          <Alert variant="warning" title="Validación de año pendiente">
            Hay fotos que no coinciden con el año activo. El reto no se puede aprobar hasta revisarlas o reemplazarlas.
          </Alert>
        </div>
      )}

      <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--gnf-space-3)', marginBottom: 'var(--gnf-space-4)' }}>
        {responses.length > 0 ? (
          responses.map((response) => <EntryResponseBlock key={response.fieldId} response={response} />)
        ) : (
          <div
            style={{
              padding: 'var(--gnf-space-4)',
              border: '1px solid var(--gnf-border)',
              borderRadius: 'var(--gnf-radius)',
              background: 'var(--gnf-white)',
              color: 'var(--gnf-muted)',
              fontSize: '0.875rem',
            }}
          >
            No hay respuestas visibles todavia para este reto.
          </div>
        )}

        {orphanEvidence.length > 0 && (
          <div
            style={{
              padding: 'var(--gnf-space-4)',
              border: '1px solid var(--gnf-border)',
              borderRadius: 'var(--gnf-radius)',
              background: 'var(--gnf-white)',
            }}
          >
            <h5 style={{ margin: '0 0 var(--gnf-space-3)', fontSize: '0.875rem' }}>Archivos adicionales</h5>
            <EvidenceViewer evidencias={orphanEvidence as Evidencia[]} />
          </div>
        )}
      </div>

      {entry.supervisorNotes && (
        <div
          style={{
            padding: 'var(--gnf-space-3)',
            background: 'var(--gnf-white)',
            borderRadius: 'var(--gnf-radius-sm)',
            fontSize: '0.8125rem',
            marginBottom: 'var(--gnf-space-4)',
            border: '1px solid var(--gnf-border)',
            borderLeft: `4px solid ${entry.estado === 'correccion' ? 'var(--gnf-coral)' : 'var(--gnf-ocean)'}`,
          }}
        >
          <strong>Feedback previo:</strong> {entry.supervisorNotes}
        </div>
      )}

      {isReviewable && (
        <div>
          {showNotes ? (
            <div>
              <Textarea
                label="Notas de correccion"
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                placeholder="Explica que debe ajustar el centro educativo..."
              />
              <div style={{ display: 'flex', gap: 'var(--gnf-space-2)' }}>
                <Button
                  variant="danger"
                  size="sm"
                  icon={<RotateCcw size={14} />}
                  loading={mutation.isPending}
                  onClick={() => mutation.mutate('correccion')}
                  disabled={!notes.trim()}
                >
                  Enviar correccion
                </Button>
                <Button variant="ghost" size="sm" onClick={() => setShowNotes(false)}>
                  Cancelar
                </Button>
              </div>
            </div>
          ) : (
            <div style={{ display: 'flex', gap: 'var(--gnf-space-2)' }}>
              <Button
                size="sm"
                icon={<CheckCircle2 size={14} />}
                loading={mutation.isPending}
                onClick={() => mutation.mutate('aprobar')}
              >
                Aprobar
              </Button>
              <Button variant="outline" size="sm" icon={<RotateCcw size={14} />} onClick={() => setShowNotes(true)}>
                Solicitar correccion
              </Button>
            </div>
          )}
        </div>
      )}
    </Card>
  );
}

function EntryResponseBlock({ response }: { response: RetoEntryResponse }) {
  const hasEvidence = response.evidencias && response.evidencias.length > 0;
  const hasTextValue = response.displayValue.trim().length > 0;
  const isEvidenceField = response.type === 'file-upload' || response.type === 'file';
  const hasEvidenceWarning = response.evidencias.some((item) => item.requires_year_validation);

  return (
    <div
      style={{
        padding: 'var(--gnf-space-4)',
        border: '1px solid var(--gnf-border)',
        borderRadius: 'var(--gnf-radius)',
        background: 'var(--gnf-white)',
      }}
    >
      <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 'var(--gnf-space-3)', marginBottom: 'var(--gnf-space-3)' }}>
        <div style={{ minWidth: 0 }}>
          <h5 style={{ margin: 0, fontSize: '0.9375rem', color: 'var(--gnf-ocean-dark)' }}>{response.label}</h5>
          <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-2)', marginTop: 'var(--gnf-space-2)', flexWrap: 'wrap' }}>
            {response.puntos > 0 && (
              <span
                style={{
                  display: 'inline-flex',
                  alignItems: 'center',
                  padding: '4px 10px',
                  borderRadius: '999px',
                  fontSize: '0.75rem',
                  fontWeight: 700,
                  background: 'rgba(22, 163, 74, 0.12)',
                  color: '#15803d',
                }}
              >
                {response.puntos} eco puntos
              </span>
            )}
            <span style={{ fontSize: '0.75rem', color: 'var(--gnf-muted)' }}>{response.type}</span>
          </div>
        </div>
        {hasEvidence && (
          <span
            style={{
              display: 'inline-flex',
              alignItems: 'center',
              gap: '6px',
              fontSize: '0.75rem',
              fontWeight: 600,
              color: 'var(--gnf-ocean-dark)',
            }}
          >
            <Upload size={14} />
            {response.evidencias.length} archivo{response.evidencias.length === 1 ? '' : 's'}
          </span>
        )}
      </div>

      {hasTextValue && (
        <div
          style={{
            padding: 'var(--gnf-space-3)',
            background: 'rgba(30, 95, 138, 0.05)',
            borderRadius: 'var(--gnf-radius-sm)',
            color: 'var(--gnf-gray-900)',
            fontSize: '0.875rem',
            marginBottom: hasEvidence ? 'var(--gnf-space-3)' : 0,
            whiteSpace: 'pre-wrap',
          }}
        >
          {response.displayValue}
        </div>
      )}

      {hasEvidence && <EvidenceViewer evidencias={response.evidencias as Evidencia[]} />}

      {hasEvidenceWarning && (
        <div style={{ marginTop: 'var(--gnf-space-3)', color: '#b45309', fontSize: '0.8125rem', fontWeight: 600 }}>
          Esta evidencia requiere validar el año de la fotografía antes de aprobarla.
        </div>
      )}

      {!hasTextValue && !hasEvidence && (
        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', color: 'var(--gnf-muted)', fontSize: '0.8125rem' }}>
          <MessageSquare size={14} />
          {isEvidenceField ? 'Sin evidencias cargadas.' : 'Sin respuesta visible.'}
        </div>
      )}
    </div>
  );
}
