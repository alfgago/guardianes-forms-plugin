import { forwardRef, type TextareaHTMLAttributes } from 'react';

interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  label?: string;
  error?: string;
}

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(
  ({ label, error, id, style, ...props }, ref) => {
    const textareaId = id ?? label?.toLowerCase().replace(/\s+/g, '-');

    return (
      <div style={{ marginBottom: 'var(--gnf-space-4)', ...style }}>
        {label && (
          <label
            htmlFor={textareaId}
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
        <textarea
          ref={ref}
          id={textareaId}
          rows={4}
          style={{
            width: '100%',
            padding: '10px 14px',
            border: `1px solid ${error ? 'var(--gnf-coral)' : 'var(--gnf-border)'}`,
            borderRadius: 'var(--gnf-radius)',
            fontSize: '0.9375rem',
            fontFamily: 'var(--gnf-font-body)',
            color: 'var(--gnf-text)',
            background: 'var(--gnf-white)',
            outline: 'none',
            resize: 'vertical',
          }}
          {...props}
        />
        {error && (
          <p style={{ marginTop: 'var(--gnf-space-1)', fontSize: '0.8125rem', color: 'var(--gnf-coral)' }}>
            {error}
          </p>
        )}
      </div>
    );
  },
);

Textarea.displayName = 'Textarea';
