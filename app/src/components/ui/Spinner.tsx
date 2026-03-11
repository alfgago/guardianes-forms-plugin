import { Loader2 } from 'lucide-react';

interface SpinnerProps {
  size?: number;
  color?: string;
  label?: string;
}

export function Spinner({ size = 32, color = 'var(--gnf-forest)', label = 'Cargando...' }: SpinnerProps) {
  return (
    <div
      role="status"
      style={{
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        gap: 'var(--gnf-space-3)',
        padding: 'var(--gnf-space-10)',
      }}
    >
      <Loader2 size={size} color={color} style={{ animation: 'spin 1s linear infinite' }} />
      {label && <span style={{ fontSize: '0.875rem', color: 'var(--gnf-muted)' }}>{label}</span>}
      <style>{`@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }`}</style>
    </div>
  );
}
