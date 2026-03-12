import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Textarea } from '@/components/ui/Textarea';
import { StatusBadge } from '@/components/domain/StatusBadge';
import { EvidenceViewer } from '@/components/domain/EvidenceViewer';
import { ProgressBar } from '@/components/ui/ProgressBar';
import { useToast } from '@/components/ui/Toast';
import { supervisorApi } from '@/api/supervisor';
import { CheckCircle2, RotateCcw } from 'lucide-react';
import type { RetoEntry, Evidencia } from '@/types';

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

      {entry.evidencias && entry.evidencias.length > 0 && (
        <div style={{ marginBottom: 'var(--gnf-space-4)' }}>
          <h5 style={{ fontSize: '0.875rem', marginBottom: 'var(--gnf-space-2)' }}>Evidencias</h5>
          <EvidenceViewer evidencias={entry.evidencias as Evidencia[]} />
        </div>
      )}

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
          <strong>Notas previas:</strong> {entry.supervisorNotes}
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
