import { forwardRef, type InputHTMLAttributes, type ReactNode } from 'react';

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string;
  hint?: string;
  rightElement?: ReactNode;
}

export const Input = forwardRef<HTMLInputElement, InputProps>(
  ({ label, error, hint, id, style, rightElement, ...props }, ref) => {
    const inputId = id ?? label?.toLowerCase().replace(/\s+/g, '-');

    return (
      <div style={{ marginBottom: 'var(--gnf-space-4)', ...style }}>
        {label && (
          <label
            htmlFor={inputId}
            style={{
              display: 'block',
              marginBottom: 'var(--gnf-space-2)',
              fontWeight: 500,
              fontSize: '0.875rem',
              color: 'var(--gnf-gray-700)',
            }}
          >
            {label}
          </label>
        )}
        <div style={{ position: 'relative' }}>
          <input
            ref={ref}
            id={inputId}
            style={{
              width: '100%',
              padding: rightElement ? '10px 44px 10px 14px' : '10px 14px',
              border: `1.5px solid ${error ? 'var(--gnf-coral)' : 'var(--gnf-field-border)'}`,
              borderRadius: 'var(--gnf-radius)',
              fontSize: '0.9375rem',
              fontFamily: 'var(--gnf-font-body)',
              color: 'var(--gnf-text)',
              background: 'var(--gnf-white)',
              outline: 'none',
              transition: 'border-color var(--gnf-transition-fast)',
            }}
            onFocus={(e) => {
              e.currentTarget.style.borderColor = 'var(--gnf-ocean)';
              e.currentTarget.style.boxShadow = '0 0 0 3px rgba(30, 95, 138, 0.12)';
            }}
            onBlur={(e) => {
              e.currentTarget.style.borderColor = error ? 'var(--gnf-coral)' : 'var(--gnf-field-border)';
              e.currentTarget.style.boxShadow = 'none';
            }}
            {...props}
          />
          {rightElement && (
            <div
              style={{
                position: 'absolute',
                top: 0,
                right: 0,
                height: '100%',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                paddingRight: 8,
              }}
            >
              {rightElement}
            </div>
          )}
        </div>
        {error && (
          <p style={{ marginTop: 'var(--gnf-space-1)', fontSize: '0.8125rem', color: 'var(--gnf-coral)' }}>
            {error}
          </p>
        )}
        {hint && !error && (
          <p style={{ marginTop: 'var(--gnf-space-1)', fontSize: '0.8125rem', color: 'var(--gnf-muted)' }}>
            {hint}
          </p>
        )}
      </div>
    );
  },
);

Input.displayName = 'Input';
