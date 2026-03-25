import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { LogIn } from 'lucide-react';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { authApi } from '@/api/auth';
import { useAuthStore } from '@/stores/useAuthStore';

interface LoginFormProps {
  onForgotPassword: () => void;
}

export function LoginForm({ onForgotPassword }: LoginFormProps) {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const setUser = useAuthStore((s) => s.setUser);

  const mutation = useMutation({
    mutationFn: () => authApi.login({ username, password }),
    onSuccess: (data) => {
      setUser(data.user);
      if (data.redirectUrl) {
        window.location.href = data.redirectUrl;
      }
    },
  });

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    mutation.mutate();
  }

  return (
    <form onSubmit={handleSubmit}>
      <h2 style={{ marginBottom: 'var(--gnf-space-6)', textAlign: 'center' }}>Iniciar sesion</h2>

      {mutation.error && (
        <Alert variant="error" title="Error de autenticacion">
          {(mutation.error as Error).message || 'Credenciales invalidas.'}
        </Alert>
      )}

      <div style={{ marginTop: 'var(--gnf-space-4)' }}>
        <Input
          label="Usuario (Correo Institucional)"
          type="text"
          value={username}
          onChange={(e) => setUsername(e.target.value)}
          required
          autoComplete="username"
        />
        <Input
          label="Contrasena"
          type="password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          required
          autoComplete="current-password"
        />
        <div style={{ textAlign: 'right', marginTop: '-4px', marginBottom: 'var(--gnf-space-4)' }}>
          <button
            type="button"
            onClick={onForgotPassword}
            style={{
              border: 'none',
              background: 'none',
              padding: 0,
              fontSize: '0.875rem',
              fontWeight: 600,
              color: 'var(--gnf-ocean)',
              cursor: 'pointer',
            }}
          >
            Olvide mi contrasena
          </button>
        </div>
      </div>

      <Button
        type="submit"
        loading={mutation.isPending}
        icon={<LogIn size={16} />}
        style={{ width: '100%', marginTop: 'var(--gnf-space-4)' }}
      >
        Ingresar
      </Button>
    </form>
  );
}
