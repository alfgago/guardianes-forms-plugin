import { useState, type ComponentPropsWithoutRef } from 'react';
import { Eye, EyeOff } from 'lucide-react';
import { Input } from './Input';

type PasswordInputProps = Omit<ComponentPropsWithoutRef<typeof Input>, 'type' | 'rightElement'>;

export function PasswordInput(props: PasswordInputProps) {
  const [showPassword, setShowPassword] = useState(false);
  const label = showPassword ? 'Ocultar contrasena' : 'Mostrar contrasena';

  return (
    <Input
      {...props}
      type={showPassword ? 'text' : 'password'}
      rightElement={
        <button
          type="button"
          aria-label={label}
          aria-pressed={showPassword}
          title={label}
          onMouseDown={(event) => event.preventDefault()}
          onClick={() => setShowPassword((current) => !current)}
          style={{
            width: 32,
            height: 32,
            display: 'inline-flex',
            alignItems: 'center',
            justifyContent: 'center',
            border: 'none',
            borderRadius: 'var(--gnf-radius-sm)',
            background: 'transparent',
            color: 'var(--gnf-ocean-dark)',
            cursor: 'pointer',
          }}
        >
          {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
        </button>
      }
    />
  );
}
