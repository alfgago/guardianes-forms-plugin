import { forwardRef, type InputHTMLAttributes } from 'react';

interface CheckboxProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> {
  label: string;
}

export const Checkbox = forwardRef<HTMLInputElement, CheckboxProps>(
  ({ label, id, style, ...props }, ref) => {
    const checkboxId = id ?? label.toLowerCase().replace(/\s+/g, '-');

    return (
      <label
        htmlFor={checkboxId}
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: 'var(--gnf-space-3)',
          cursor: props.disabled ? 'not-allowed' : 'pointer',
          fontSize: '0.9375rem',
          color: 'var(--gnf-gray-700)',
          ...style,
        }}
      >
        <input
          ref={ref}
          type="checkbox"
          id={checkboxId}
          style={{
            width: 18,
            height: 18,
            accentColor: 'var(--gnf-forest)',
            cursor: 'inherit',
          }}
          {...props}
        />
        {label}
      </label>
    );
  },
);

Checkbox.displayName = 'Checkbox';
