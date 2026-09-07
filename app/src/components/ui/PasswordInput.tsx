import { useState, type ComponentPropsWithoutRef } from 'react';
import { Eye, EyeOff } from 'lucide-react';
import { Input } from './Input';

type PasswordInputProps = Omit<ComponentPropsWithoutRef<typeof Input>, 'type' | 'rightElement'>;

export function PasswordInput(props: PasswordInputProps) {
  const [showPassword, setShowPassword] = useState(false);
  const label = showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña';

  return (
    <Input
      {...props}
      data-gnf-password-toggle="1"
      type={showPassword ? 'text' : 'password'}
      rightElement={
        <button
          type="button"
          className="gnf-password-input__toggle"
          aria-label={label}
          aria-pressed={showPassword}
          title={label}
          onMouseDown={(event) => event.preventDefault()}
          onClick={() => setShowPassword((current) => !current)}
        >
          {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
        </button>
      }
    />
  );
}
