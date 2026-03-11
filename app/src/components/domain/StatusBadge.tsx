import type { Estado } from '@/types';
import { Badge } from '@/components/ui/Badge';
import { ESTADO_LABELS, ESTADO_COLORS, ESTADO_BG_COLORS } from '@/utils/constants';

interface StatusBadgeProps {
  estado: Estado;
  size?: 'sm' | 'md';
}

export function StatusBadge({ estado, size = 'sm' }: StatusBadgeProps) {
  return (
    <Badge color={ESTADO_COLORS[estado]} bg={ESTADO_BG_COLORS[estado]} size={size}>
      {ESTADO_LABELS[estado]}
    </Badge>
  );
}
