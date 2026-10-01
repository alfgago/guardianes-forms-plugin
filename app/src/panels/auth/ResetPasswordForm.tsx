import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { KeyRound, Mail, LogIn } from 'lucide-react';
import { PasswordInput } from '@/components/ui/PasswordInput';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { authApi } from '@/api/auth';
import { ApiRequestError } from '@/api/client';

interface ResetPasswordFormProps {
  login: string;
  resetKey: string;
  onBack: () => void;
  onRequestNewLink: () => void;
}

export function ResetPasswordForm({ login, resetKey, onBack, onRequestNewLink }: ResetPasswordFormProps) {
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');

  const mutation = useMutation({
    mutationFn: () => {
      if (password !== confirmPassword) {
        throw new Error('Las contraseñas no coinciden.');
      }

      return authApi.resetPassword({
        login,
        key: resetKey,
        password,
      });
    },
    onSuccess: () => {
      const url = new URL(window.location.href);
      url.searchParams.delete('reset');
      url.searchParams.delete('login');
      url.searchParams.delete('key');
      window.history.replaceState({}, '', url.toString());
      setPassword('');
      setConfirmPassword('');
    },
  });

  if (mutation.isSuccess) {
    return (
      <div>
        <Alert variant="success" title="Contraseña actualizada">{mutation.data.message}</Alert>
        <Button type="button" icon={<LogIn size={16} />} onClick={onBack} style={{ width: '100%' }}>
          Iniciar sesión
        </Button>
      </div>
    );
  }

  const invalidLink = mutation.error instanceof ApiRequestError
    && ['invalid_reset_key', 'expired_reset_key'].includes(mutation.error.code);

  if (invalidLink) {
    return (
      <div>
        <Alert variant="error">{mutation.error!.message}</Alert>
        <Button type="button" icon={<Mail size={16} />} onClick={onRequestNewLink} style={{ width: '100%', whiteSpace: 'normal' }}>
          Solicitar un nuevo enlace
        </Button>
        <Button type="button" variant="ghost" onClick={onBack} style={{ width: '100%', marginTop: 'var(--gnf-space-3)' }}>
          Volver
        </Button>
      </div>
    );
  }

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        mutation.mutate();
      }}
    >
      <h2 style={{ marginBottom: 'var(--gnf-space-4)', textAlign: 'center' }}>Crear nueva contraseña</h2>
      <p style={{ color: 'var(--gnf-muted)', fontSize: '0.9375rem', marginBottom: 'var(--gnf-space-5)', overflowWrap: 'anywhere' }}>
        Define una nueva contraseña para la cuenta <strong>{login}</strong>.
      </p>

      {mutation.error && <Alert variant="error">{(mutation.error as Error).message}</Alert>}

      <PasswordInput
        label="Nueva contraseña"
        value={password}
        onChange={(e) => setPassword(e.target.value)}
        minLength={8}
        required
      />
      <PasswordInput
        label="Confirmar contraseña"
        value={confirmPassword}
        onChange={(e) => setConfirmPassword(e.target.value)}
        minLength={8}
        required
      />

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 150px), 1fr))', gap: 'var(--gnf-space-3)', marginTop: 'var(--gnf-space-4)' }}>
        <Button type="button" variant="ghost" style={{ flex: 1 }} onClick={onBack}>
          Volver
        </Button>
        <Button type="submit" loading={mutation.isPending} icon={<KeyRound size={16} />} style={{ whiteSpace: 'normal' }}>
          Guardar contraseña
        </Button>
      </div>
    </form>
  );
}
