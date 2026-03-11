import type { Centro } from '@/types';
import { Card } from '@/components/ui/Card';
import { MapPin, Hash } from 'lucide-react';

interface CentroCardProps {
  centro: Centro;
}

export function CentroCard({ centro }: CentroCardProps) {
  return (
    <Card>
      <h3 style={{ marginBottom: 'var(--gnf-space-2)' }}>{centro.nombre}</h3>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--gnf-space-2)', fontSize: '0.875rem', color: 'var(--gnf-muted)' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-2)' }}>
          <Hash size={14} />
          <span>Código MEP: {centro.codigoMep}</span>
        </div>
        {centro.regionName && (
          <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-2)' }}>
            <MapPin size={14} />
            <span>{centro.regionName}</span>
          </div>
        )}
        {centro.direccion && (
          <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-2)' }}>
            <MapPin size={14} />
            <span>{centro.direccion}</span>
          </div>
        )}
      </div>
    </Card>
  );
}
