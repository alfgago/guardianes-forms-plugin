import { type ButtonHTMLAttributes, forwardRef } from 'react';
import { Loader2 } from 'lucide-react';

type ButtonVariant = 'primary' | 'ghost' | 'danger' | 'outline';
type ButtonSize = 'sm' | 'md' | 'lg';

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  size?: ButtonSize;
  loading?: boolean;
  icon?: React.ReactNode;
}

const variantStyles: Record<ButtonVariant, React.CSSProperties> = {
  primary: {
    background: 'var(--gnf-forest)',
    color: 'var(--gnf-white)',
    border: 'none',
  },
  ghost: {
    background: 'transparent',
    color: 'var(--gnf-gray-700)',
    border: '1px solid var(--gnf-border)',
  },
  danger: {
    background: 'var(--gnf-coral)',
    color: 'var(--gnf-white)',
    border: 'none',
  },
  outline: {
    background: 'transparent',
    color: 'var(--gnf-forest)',
    border: '1px solid var(--gnf-forest)',
  },
};

const sizeStyles: Record<ButtonSize, React.CSSProperties> = {
  sm: { padding: '6px 12px', fontSize: '0.8125rem' },
  md: { padding: '10px 20px', fontSize: '0.9375rem' },
  lg: { padding: '14px 28px', fontSize: '1rem' },
};

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ variant = 'primary', size = 'md', loading, icon, children, disabled, style, ...props }, ref) => {
    return (
      <button
        ref={ref}
        disabled={disabled || loading}
        style={{
          display: 'inline-flex',
          alignItems: 'center',
          justifyContent: 'center',
          gap: '8px',
          borderRadius: 'var(--gnf-radius)',
          fontWeight: 600,
          fontFamily: 'var(--gnf-font-body)',
          cursor: disabled || loading ? 'not-allowed' : 'pointer',
          opacity: disabled ? 0.5 : 1,
          transition: 'all var(--gnf-transition-fast)',
          whiteSpace: 'nowrap',
          ...variantStyles[variant],
          ...sizeStyles[size],
          ...style,
        }}
        {...props}
      >
        {loading ? <Loader2 size={16} style={{ animation: 'spin 1s linear infinite' }} /> : icon}
        {children}
      </button>
    );
  },
);

Button.displayName = 'Button';
