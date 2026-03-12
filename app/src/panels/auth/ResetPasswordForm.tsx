import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { KeyRound } from 'lucide-react';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { authApi } from '@/api/auth';

interface ResetPasswordFormProps {
  login: string;
  resetKey: string;
  onBack: () => void;
}

export function ResetPasswordForm({ login, resetKey, onBack }: ResetPasswordFormProps) {
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');

  const mutation = useMutation({
    mutationFn: () => {
      if (password !== confirmPassword) {
        throw new Error('Las contrasenas no coinciden.');
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
    },
  });

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        mutation.mutate();
      }}
    >
      <h2 style={{ marginBottom: 'var(--gnf-space-4)', textAlign: 'center' }}>Crear nueva contrasena</h2>
      <p style={{ color: 'var(--gnf-muted)', fontSize: '0.9375rem', marginBottom: 'var(--gnf-space-5)' }}>
        Define una nueva contrasena para la cuenta <strong>{login}</strong>.
      </p>

      {mutation.isSuccess && (
        <Alert variant="success" title="Contrasena actualizada">
          {mutation.data.message}
        </Alert>
      )}

      {mutation.error && <Alert variant="error">{(mutation.error as Error).message}</Alert>}

      <Input
        label="Nueva contrasena"
        type="password"
        value={password}
        onChange={(e) => setPassword(e.target.value)}
        minLength={8}
        required
      />
      <Input
        label="Confirmar contrasena"
        type="password"
        value={confirmPassword}
        onChange={(e) => setConfirmPassword(e.target.value)}
        minLength={8}
        required
      />

      <div style={{ display: 'flex', gap: 'var(--gnf-space-3)', marginTop: 'var(--gnf-space-4)' }}>
        <Button type="button" variant="ghost" style={{ flex: 1 }} onClick={onBack}>
          Volver
        </Button>
        <Button type="submit" loading={mutation.isPending} icon={<KeyRound size={16} />} style={{ flex: 1 }}>
          Guardar contrasena
        </Button>
      </div>
    </form>
  );
}
