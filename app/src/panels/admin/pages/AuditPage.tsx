import { useQuery } from '@tanstack/react-query';
import { ShieldCheck } from 'lucide-react';
import { adminApi } from '@/api/admin';
import { useYearStore } from '@/stores/useYearStore';
import { Card } from '@/components/ui/Card';
import { Spinner } from '@/components/ui/Spinner';
import { EmptyState } from '@/components/ui/EmptyState';

const EVENT_LABELS: Record<string, string> = {
  auth_login: 'Inicio de sesion',
  auth_register_docente: 'Registro de centro educativo',
  auth_register_supervisor: 'Registro de supervisor/comite',
  matricula_add_reto: 'Agrego reto a matricula',
  matricula_remove_reto: 'Removio reto de matricula',
  docente_autosave_reto: 'Guardado automatico de formulario',
  docente_finalize_reto: 'Marco reto como completo',
  docente_submit_participacion: 'Envio participacion anual',
  supervisor_approve_entry: 'Supervisor aprobo reto',
  supervisor_request_correction: 'Supervisor solicito correccion',
  comite_validate_centro: 'Comite valido centro',
  comite_reject_centro: 'Comite rechazo centro',
  comite_add_observation: 'Comite agrego observacion',
  admin_approve_user: 'Admin aprobo usuario',
  admin_reject_user: 'Admin rechazo usuario',
  admin_toggle_region: 'Admin cambio region',
  admin_start_impersonation: 'Admin inicio impersonacion',
  admin_stop_impersonation: 'Admin termino impersonacion',
  panel_visit: 'Visita de panel',
  form_autosave: 'Evento de llenado',
  reto_finalize_click: 'Finalizacion de reto',
  annual_submit_click: 'Envio anual',
};

function formatDateTime(value: string) {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return date.toLocaleString('es-CR', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export function AuditPage() {
  const year = useYearStore((state) => state.selectedYear);

  const { data, isLoading } = useQuery({
    queryKey: ['admin-audit-logs', year],
    queryFn: () => adminApi.getAuditLogs(year),
  });

  if (isLoading) {
    return <Spinner />;
  }

  if (!data || data.length === 0) {
    return <EmptyState icon={<ShieldCheck size={48} />} title="Sin actividad auditada para este año" />;
  }

  return (
    <div>
      <h2 style={{ marginBottom: 'var(--gnf-space-4)' }}>Auditoria</h2>
      <p style={{ color: 'var(--gnf-muted)', fontSize: '0.875rem', marginBottom: 'var(--gnf-space-4)' }}>
        Registro cronologico de acciones hechas por centros educativos, supervisores, comite y administracion.
      </p>

      <div style={{ display: 'grid', gap: 'var(--gnf-space-3)' }}>
        {data.map((item) => (
          <Card key={item.id}>
            <div style={{ display: 'flex', justifyContent: 'space-between', gap: 'var(--gnf-space-4)', flexWrap: 'wrap' }}>
              <div style={{ display: 'grid', gap: 'var(--gnf-space-2)' }}>
                <strong>{EVENT_LABELS[item.event_key] ?? item.event_key}</strong>
                <div style={{ fontSize: '0.875rem', color: 'var(--gnf-muted)' }}>
                  Actor: {item.actorName ?? 'Sistema'}
                  {item.actor_role ? ` | Rol: ${item.actor_role}` : ''}
                  {item.panel ? ` | Panel: ${item.panel}` : ''}
                </div>
                {(item.centroName || item.retoTitle || item.targetName) && (
                  <div style={{ fontSize: '0.8125rem', color: 'var(--gnf-muted)' }}>
                    {item.centroName ? `Centro: ${item.centroName}` : ''}
                    {item.retoTitle ? `${item.centroName ? ' | ' : ''}Reto: ${item.retoTitle}` : ''}
                    {item.targetName ? `${item.centroName || item.retoTitle ? ' | ' : ''}Objetivo: ${item.targetName}` : ''}
                  </div>
                )}
                {item.message && <p style={{ margin: 0, fontSize: '0.875rem' }}>{item.message}</p>}
              </div>
              <div style={{ fontSize: '0.8125rem', color: 'var(--gnf-muted)', whiteSpace: 'nowrap' }}>
                {formatDateTime(item.created_at)}
              </div>
            </div>
          </Card>
        ))}
      </div>
    </div>
  );
}
