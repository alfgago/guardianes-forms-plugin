import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, CheckCircle2, MessageSquarePlus, XCircle } from 'lucide-react';
import { comiteApi } from '@/api/comite';
import { useYearStore } from '@/stores/useYearStore';
import { Spinner } from '@/components/ui/Spinner';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Textarea } from '@/components/ui/Textarea';
import { Badge } from '@/components/ui/Badge';
import { CentroCard } from '@/components/domain/CentroCard';
import { StatusBadge } from '@/components/domain/StatusBadge';
import { StarRating } from '@/components/ui/StarRating';
import { ProgressBar } from '@/components/ui/ProgressBar';
import { EvidenceViewer } from '@/components/domain/EvidenceViewer';
import type { Evidencia } from '@/types';

interface CentroDetailPageProps {
  centroId: number;
  onBack: () => void;
}

export function CentroDetailPage({ centroId, onBack }: CentroDetailPageProps) {
  const year = useYearStore((state) => state.selectedYear);
  const queryClient = useQueryClient();
  const [reviewNotes, setReviewNotes] = useState('');
  const [observation, setObservation] = useState('');

  const { data, isLoading, error } = useQuery({
    queryKey: ['comite-centro', centroId, year],
    queryFn: () => comiteApi.getCentroDetail(centroId, year),
  });

  useEffect(() => {
    if (data?.review.notes) {
      setReviewNotes(data.review.notes);
    }
  }, [data?.review.notes]);

  const validateMutation = useMutation({
    mutationFn: (action: 'validar' | 'rechazar') =>
      comiteApi.validateCentro(centroId, year, {
        action,
        notes: reviewNotes.trim() || undefined,
      }),
    onSuccess: () => {
      setReviewNotes('');
      queryClient.invalidateQueries({ queryKey: ['comite-centro', centroId, year] });
      queryClient.invalidateQueries({ queryKey: ['comite-centros', year] });
      queryClient.invalidateQueries({ queryKey: ['comite-dashboard', year] });
      queryClient.invalidateQueries({ queryKey: ['comite-historial', year] });
    },
  });

  const observationMutation = useMutation({
    mutationFn: () => comiteApi.addObservation(centroId, year, { observation: observation.trim() }),
    onSuccess: () => {
      setObservation('');
      queryClient.invalidateQueries({ queryKey: ['comite-centro', centroId, year] });
      queryClient.invalidateQueries({ queryKey: ['comite-historial', year] });
    },
  });

  const reviewBadge = useMemo(() => {
    const status = data?.review.status ?? '';
    if (status === 'validado') {
      return <Badge color="#fff" bg="#16a34a">Validado</Badge>;
    }
    if (status === 'rechazado') {
      return <Badge color="#fff" bg="#dc2626">Rechazado</Badge>;
    }
    return <Badge color="#b45309" bg="rgba(245, 158, 11, 0.12)">Pendiente</Badge>;
  }, [data?.review.status]);

  if (isLoading) return <Spinner />;
  if (error || !data) return <Alert variant="error">Error al cargar centro.</Alert>;

  const { centro, entries, review, observations } = data;

  return (
    <div>
      <Button variant="ghost" size="sm" icon={<ArrowLeft size={16} />} onClick={onBack} style={{ marginBottom: 'var(--gnf-space-4)' }}>
        Volver
      </Button>

      <CentroCard centro={centro} />

      <div style={{ display: 'flex', gap: 'var(--gnf-space-6)', margin: 'var(--gnf-space-6) 0', flexWrap: 'wrap' }}>
        <div>
          <span style={{ fontSize: '0.875rem', color: 'var(--gnf-muted)' }}>Galardón actual:</span>
          <StarRating rating={centro.annual.estrellaFinal} size={16} />
        </div>
        <span style={{ fontSize: '0.875rem', color: 'var(--gnf-muted)' }}>{centro.annual.puntajeTotal} pts</span>
        {reviewBadge}
      </div>

      {review.notes && (
        <Alert variant={review.status === 'rechazado' ? 'error' : 'info'} title="Ultima revision del comite">
          {review.notes}
        </Alert>
      )}

      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))',
          gap: 'var(--gnf-space-4)',
          margin: 'var(--gnf-space-6) 0',
        }}
      >
        <Card>
          <h3 style={{ marginTop: 0, marginBottom: 'var(--gnf-space-4)' }}>Revision del centro</h3>
          <Textarea
            label="Notas de validacion"
            value={reviewNotes}
            onChange={(event) => setReviewNotes(event.target.value)}
            placeholder="Agrega una nota para respaldar la decision del comite."
          />
          <div style={{ display: 'flex', gap: 'var(--gnf-space-3)', flexWrap: 'wrap' }}>
            <Button
              icon={<CheckCircle2 size={16} />}
              loading={validateMutation.isPending}
              onClick={() => validateMutation.mutate('validar')}
            >
              Validar centro
            </Button>
            <Button
              variant="danger"
              icon={<XCircle size={16} />}
              loading={validateMutation.isPending}
              onClick={() => validateMutation.mutate('rechazar')}
            >
              Rechazar centro
            </Button>
          </div>
          {validateMutation.isError && (
            <Alert variant="error" title="No se pudo guardar la revision">
              {(validateMutation.error as Error).message}
            </Alert>
          )}
          {review.updatedAt && (
            <p style={{ marginBottom: 0, marginTop: 'var(--gnf-space-4)', fontSize: '0.8125rem', color: 'var(--gnf-muted)' }}>
              Ultima revision: {review.updatedAt}{review.userName ? ` por ${review.userName}` : ''}
            </p>
          )}
        </Card>

        <Card>
          <h3 style={{ marginTop: 0, marginBottom: 'var(--gnf-space-4)' }}>Observaciones</h3>
          <Textarea
            label="Nueva observacion"
            value={observation}
            onChange={(event) => setObservation(event.target.value)}
            placeholder="Registra una observacion visible en el historial del comite."
          />
          <Button
            variant="outline"
            icon={<MessageSquarePlus size={16} />}
            loading={observationMutation.isPending}
            onClick={() => observationMutation.mutate()}
            disabled={!observation.trim()}
          >
            Guardar observacion
          </Button>
          {observationMutation.isError && (
            <Alert variant="error" title="No se pudo guardar la observacion">
              {(observationMutation.error as Error).message}
            </Alert>
          )}
          <div style={{ display: 'grid', gap: 'var(--gnf-space-3)', marginTop: 'var(--gnf-space-4)' }}>
            {observations.length === 0 && (
              <p style={{ margin: 0, color: 'var(--gnf-muted)', fontSize: '0.875rem' }}>
                No hay observaciones registradas para este ano.
              </p>
            )}
            {observations.map((item, index) => (
              <div
                key={`${item.createdAt}-${index}`}
                style={{
                  padding: 'var(--gnf-space-3)',
                  borderRadius: 'var(--gnf-radius)',
                  background: 'var(--gnf-gray-50)',
                  border: '1px solid var(--gnf-border)',
                }}
              >
                <p style={{ margin: 0, fontSize: '0.875rem' }}>{item.observation}</p>
                <p style={{ margin: 'var(--gnf-space-2) 0 0', fontSize: '0.75rem', color: 'var(--gnf-muted)' }}>
                  {item.userName} - {item.createdAt}
                </p>
              </div>
            ))}
          </div>
        </Card>
      </div>

      <h3 style={{ marginBottom: 'var(--gnf-space-4)' }}>Retos ({entries.length})</h3>
      {entries.map((entry) => (
        <Card key={entry.id} style={{ marginBottom: 'var(--gnf-space-4)' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-3)', marginBottom: 'var(--gnf-space-3)' }}>
            {entry.retoIconUrl && (
              <img
                src={entry.retoIconUrl}
                alt=""
                style={{ width: 40, height: 40, objectFit: 'contain', borderRadius: 'var(--gnf-radius-sm)' }}
              />
            )}
            <h4 style={{ margin: 0, color: entry.retoColor }}>{entry.retoTitulo}</h4>
            <StatusBadge estado={entry.estado} />
          </div>
          <ProgressBar value={entry.puntaje} max={entry.puntajeMaximo} height={6} />
          <span style={{ fontSize: '0.75rem', color: 'var(--gnf-muted)' }}>
            {entry.puntaje} / {entry.puntajeMaximo} pts
          </span>
          {entry.evidencias && (entry.evidencias as Evidencia[]).length > 0 && (
            <div style={{ marginTop: 'var(--gnf-space-3)' }}>
              <EvidenceViewer evidencias={entry.evidencias as Evidencia[]} />
            </div>
          )}
        </Card>
      ))}
    </div>
  );
}
