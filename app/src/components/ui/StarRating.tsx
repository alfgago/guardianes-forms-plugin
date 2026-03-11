import { Star } from 'lucide-react';

interface StarRatingProps {
  rating: number;
  max?: number;
  size?: number;
  interactive?: boolean;
  onChange?: (rating: number) => void;
}

export function StarRating({ rating, max = 5, size = 20, interactive, onChange }: StarRatingProps) {
  return (
    <div style={{ display: 'inline-flex', gap: 2 }}>
      {Array.from({ length: max }, (_, i) => {
        const filled = i < rating;
        return (
          <button
            key={i}
            type="button"
            disabled={!interactive}
            onClick={() => onChange?.(i + 1)}
            aria-label={`${i + 1} estrella${i > 0 ? 's' : ''}`}
            style={{
              display: 'inline-flex',
              border: 'none',
              background: 'none',
              padding: 0,
              cursor: interactive ? 'pointer' : 'default',
              color: filled ? 'var(--gnf-sun)' : 'var(--gnf-gray-300)',
              transition: 'color var(--gnf-transition-fast), transform var(--gnf-transition-fast)',
            }}
            onMouseEnter={interactive ? (e) => { e.currentTarget.style.transform = 'scale(1.2)'; } : undefined}
            onMouseLeave={interactive ? (e) => { e.currentTarget.style.transform = 'scale(1)'; } : undefined}
          >
            <Star size={size} fill={filled ? 'currentColor' : 'none'} />
          </button>
        );
      })}
    </div>
  );
}
