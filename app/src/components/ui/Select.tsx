import { forwardRef, type SelectHTMLAttributes } from 'react';

interface SelectOption {
  value: string;
  label: string;
}

interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
  label?: string;
  error?: string;
  options: SelectOption[];
  placeholder?: string;
}

export const Select = forwardRef<HTMLSelectElement, SelectProps>(
  ({ label, error, options, placeholder, id, style, ...props }, ref) => {
    const selectId = id ?? label?.toLowerCase().replace(/\s+/g, '-');

    return (
      <div style={{ marginBottom: 'var(--gnf-space-4)', ...style }}>
        {label && (
          <label
            htmlFor={selectId}
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
        <select
          ref={ref}
          id={selectId}
          style={{
            width: '100%',
            padding: '10px 14px',
            border: `1.5px solid ${error ? 'var(--gnf-coral)' : 'var(--gnf-field-border)'}`,
            borderRadius: 'var(--gnf-radius)',
            fontSize: '0.9375rem',
            fontFamily: 'var(--gnf-font-body)',
            color: 'var(--gnf-text)',
            background: 'var(--gnf-white)',
            outline: 'none',
            cursor: 'pointer',
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
        >
          {placeholder && <option value="">{placeholder}</option>}
          {options.map((opt) => (
            <option key={opt.value} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>
        {error && (
          <p style={{ marginTop: 'var(--gnf-space-1)', fontSize: '0.8125rem', color: 'var(--gnf-coral)' }}>
            {error}
          </p>
        )}
      </div>
    );
  },
);

Select.displayName = 'Select';
