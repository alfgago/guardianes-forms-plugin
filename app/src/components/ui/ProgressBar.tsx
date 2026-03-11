interface ProgressBarProps {
  value: number;
  max?: number;
  color?: string;
  height?: number;
  showLabel?: boolean;
  animated?: boolean;
}

export function ProgressBar({
  value,
  max = 100,
  color = 'var(--gnf-forest)',
  height = 8,
  showLabel,
  animated = true,
}: ProgressBarProps) {
  const percentage = max > 0 ? Math.min(Math.round((value / max) * 100), 100) : 0;

  return (
    <div>
      <div
        role="progressbar"
        aria-valuenow={value}
        aria-valuemin={0}
        aria-valuemax={max}
        style={{
          width: '100%',
          height,
          backgroundColor: 'var(--gnf-gray-200)',
          borderRadius: 'var(--gnf-radius-full)',
          overflow: 'hidden',
        }}
      >
        <div
          style={{
            width: `${percentage}%`,
            height: '100%',
            backgroundColor: color,
            borderRadius: 'var(--gnf-radius-full)',
            transition: animated ? 'width 0.6s ease-out' : undefined,
          }}
        />
      </div>
      {showLabel && (
        <span style={{ fontSize: '0.8125rem', color: 'var(--gnf-muted)', marginTop: 4, display: 'block' }}>
          {percentage}%
        </span>
      )}
    </div>
  );
}
