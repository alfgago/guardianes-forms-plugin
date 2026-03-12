import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, CheckCircle2, MessageSquarePlus, XCircle } from 'lucide-react';
import { supervisorApi } from '@/api/supervisor';
import { comiteApi } from '@/api/comite';
import { useYearStore } from '@/stores/useYearStore';
import { Spinner } from '@/components/ui/Spinner';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Textarea } from '@/components/ui/Textarea';
import { CentroCard } from '@/components/domain/CentroCard';
import { StarRating } from '@/components/ui/StarRating';
import { EntryReviewCard } from '@/panels/supervisor/components/EntryReviewCard';

interface CentroDetailPageProps {
  centroId: number;
  onBack: () => void;
}

export function CentroDetailPage({ centroId, onBack }: CentroDetailPageProps) {
  const year = useYearStore((state) => state.selectedYear);
  const queryClient = useQueryClient();
  const [reviewNotes, setReviewNotes] = useState('');
  const [observation, setObservation] = useState('');

  const supervisorQuery = useQuery({
    queryKey: ['admin-centro-supervisor', centroId, year],
    queryFn: () => supervisorApi.getCentroDetail(centroId, year),
  });

  const comiteQuery = useQuery({
    queryKey: ['admin-centro-comite', centroId, year],
    queryFn: () => comiteApi.getCentroDetail(centroId, year),
  });

  useEffect(() => {
    if (comiteQuery.data?.review.notes) {
      setReviewNotes(comiteQuery.data.review.notes);
    }
  }, [comiteQuery.data?.review.notes]);

  const validateMutation = useMutation({
    mutationFn: (action: 'validar' | 'rechazar') =>
      comiteApi.validateCentro(centroId, year, {
        action,
        notes: reviewNotes.trim() || undefined,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-centro-comite', centroId, year] });
      queryClient.invalidateQueries({ queryKey: ['admin-centros', year] });
      queryClient.invalidateQueries({ queryKey: ['admin-stats', year] });
      queryClient.invalidateQueries({ queryKey: ['admin-reports', year] });
    },
  });

  const observationMutation = useMutation({
    mutationFn: () => comiteApi.addObservation(centroId, year, { observation: observation.trim() }),
    onSuccess: () => {
      setObservation('');
      queryClient.invalidateQueries({ queryKey: ['admin-centro-comite', centroId, year] });
    },
  });

  const reviewBadge = useMemo(() => {
    const status = comiteQuery.data?.review.status ?? '';
    if (status === 'validado') {
      return <Badge color="#fff" bg="#16a34a">Validado por comité</Badge>;
    }
    if (status === 'rechazado') {
      return <Badge color="#fff" bg="#dc2626">Rechazado por comité</Badge>;
    }
    return <Badge color="#b45309" bg="rgba(245, 158, 11, 0.12)">Pendiente de comite</Badge>;
  }, [comiteQuery.data?.review.status]);

  if (supervisorQuery.isLoading || comiteQuery.isLoading) {
    return <Spinner />;
  }

  if (supervisorQuery.error || comiteQuery.error || !supervisorQuery.data || !comiteQuery.data) {
    return <Alert variant="error">Error al cargar el detalle del centro.</Alert>;
  }

  const { centro, entries } = supervisorQuery.data;
  const { review, observations } = comiteQuery.data;

  return (
    <div>
      <Button variant="ghost" size="sm" icon={<ArrowLeft size={16} />} onClick={onBack} style={{ marginBottom: 'var(--gnf-space-4)' }}>
        Volver al listado
      </Button>

      <CentroCard centro={centro} />

      <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-4)', margin: 'var(--gnf-space-6) 0 var(--gnf-space-4)', flexWrap: 'wrap' }}>
        <div>
          <span style={{ fontSize: '0.875rem', color: 'var(--gnf-muted)' }}>Galardón actual:</span>
          <StarRating rating={centro.annual.estrellaFinal} size={16} />
        </div>
        <div>
          <span style={{ fontSize: '0.875rem', color: 'var(--gnf-muted)' }}>{centro.annual.puntajeTotal} pts</span>
        </div>
        {reviewBadge}
      </div>

      {review.notes && (
        <Alert variant={review.status === 'rechazado' ? 'error' : 'info'} title="Ultima revision del comité">
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
          <h3 style={{ marginTop: 0, marginBottom: 'var(--gnf-space-4)' }}>Vista comité</h3>
          <Textarea
            label="Notas de validacion"
            value={reviewNotes}
            onChange={(event) => setReviewNotes(event.target.value)}
            placeholder="Agrega una nota para respaldar la decision del comité."
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
          {review.updatedAt && (
            <p style={{ marginBottom: 0, marginTop: 'var(--gnf-space-4)', fontSize: '0.8125rem', color: 'var(--gnf-muted)' }}>
              Ultima revision: {review.updatedAt}{review.userName ? ` por ${review.userName}` : ''}
            </p>
          )}
        </Card>

        <Card>
          <h3 style={{ marginTop: 0, marginBottom: 'var(--gnf-space-4)' }}>Observaciones del comité</h3>
          <Textarea
            label="Nueva observacion"
            value={observation}
            onChange={(event) => setObservation(event.target.value)}
            placeholder="Registra una observacion visible en el historial."
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

          <div style={{ display: 'grid', gap: 'var(--gnf-space-3)', marginTop: 'var(--gnf-space-4)' }}>
            {observations.length === 0 && (
              <p style={{ margin: 0, color: 'var(--gnf-muted)', fontSize: '0.875rem' }}>
                No hay observaciones registradas para este año.
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

      <h3 style={{ marginBottom: 'var(--gnf-space-4)' }}>Vista supervisor: retos ({entries.length})</h3>
      {entries.map((entry) => (
        <EntryReviewCard key={entry.id} entry={entry} year={year} />
      ))}
    </div>
  );
}
