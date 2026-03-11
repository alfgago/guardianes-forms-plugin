import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { matriculaApi } from '@/api/matricula';
import { useYearStore } from '@/stores/useYearStore';
import { Spinner } from '@/components/ui/Spinner';
import { Alert } from '@/components/ui/Alert';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { StarRating } from '@/components/ui/StarRating';
import { Checkbox } from '@/components/ui/Checkbox';
import { useToast } from '@/components/ui/Toast';
import { Plus, Minus } from 'lucide-react';

export function MatriculaPage() {
  const year = useYearStore((s) => s.selectedYear);
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const { data, isLoading } = useQuery({
    queryKey: ['matricula-prefill', year],
    queryFn: () => matriculaApi.getPrefill(year),
  });

  const addReto = useMutation({
    mutationFn: (retoId: number) => matriculaApi.addReto(retoId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['matricula-prefill', year] });
      toast('success', 'Reto agregado a la matrícula.');
    },
    onError: () => toast('error', 'Error al agregar reto.'),
  });

  const removeReto = useMutation({
    mutationFn: (retoId: number) => matriculaApi.removeReto(retoId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['matricula-prefill', year] });
      toast('success', 'Reto removido de la matrícula.');
    },
    onError: () => toast('error', 'Error al remover reto.'),
  });

  if (isLoading) return <Spinner />;
  if (!data) return <Alert variant="error">Error al cargar datos de matrícula.</Alert>;

  const selected = new Set(data.retosSeleccionados);

  return (
    <div>
      <h2 style={{ marginBottom: 'var(--gnf-space-2)' }}>Matrícula {year}</h2>
      <p style={{ color: 'var(--gnf-muted)', marginBottom: 'var(--gnf-space-6)' }}>
        Selecciona los eco retos en los que participará tu centro educativo.
      </p>

      <Card style={{ marginBottom: 'var(--gnf-space-6)' }}>
        <h3 style={{ marginBottom: 'var(--gnf-space-3)' }}>Meta de estrellas</h3>
        <StarRating rating={data.metaEstrellas} interactive onChange={() => {}} />
      </Card>

      <h3 style={{ marginBottom: 'var(--gnf-space-4)' }}>Eco Retos Disponibles</h3>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--gnf-space-3)' }}>
        {data.retosDisponibles.map((reto) => {
          const isSelected = selected.has(reto.id);
          const isObligatorio = reto.obligatorio;

          return (
            <Card key={reto.id} hoverable style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-4)' }}>
              {reto.iconUrl && (
                <img
                  src={reto.iconUrl}
                  alt=""
                  style={{ width: 48, height: 48, borderRadius: 'var(--gnf-radius-sm)', objectFit: 'cover' }}
                />
              )}
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-2)' }}>
                  <strong>{reto.titulo}</strong>
                  {isObligatorio && (
                    <span style={{ fontSize: '0.6875rem', color: 'var(--gnf-ocean)', fontWeight: 700 }}>OBLIGATORIO</span>
                  )}
                  <span style={{ fontSize: '0.75rem', color: 'var(--gnf-muted)' }}>
                    ({reto.puntajeMaximo} pts max)
                  </span>
                </div>
                <p style={{ fontSize: '0.8125rem', color: 'var(--gnf-muted)', margin: 'var(--gnf-space-1) 0 0' }}>
                  {reto.descripcion}
                </p>
              </div>
              {isObligatorio ? (
                <Checkbox label="" checked disabled />
              ) : isSelected ? (
                <Button
                  variant="danger"
                  size="sm"
                  icon={<Minus size={14} />}
                  loading={removeReto.isPending}
                  onClick={() => removeReto.mutate(reto.id)}
                >
                  Quitar
                </Button>
              ) : (
                <Button
                  variant="outline"
                  size="sm"
                  icon={<Plus size={14} />}
                  loading={addReto.isPending}
                  onClick={() => addReto.mutate(reto.id)}
                >
                  Agregar
                </Button>
              )}
            </Card>
          );
        })}
      </div>
    </div>
  );
}
