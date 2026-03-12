import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Mail } from 'lucide-react';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { authApi } from '@/api/auth';

interface ForgotPasswordFormProps {
  onBack: () => void;
}

export function ForgotPasswordForm({ onBack }: ForgotPasswordFormProps) {
  const [identifier, setIdentifier] = useState('');

  const mutation = useMutation({
    mutationFn: () =>
      authApi.forgotPassword({
        identifier,
        redirectUrl: `${window.location.origin}${window.location.pathname}`,
      }),
  });

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        mutation.mutate();
      }}
    >
      <h2 style={{ marginBottom: 'var(--gnf-space-4)', textAlign: 'center' }}>Recuperar contrasena</h2>
      <p style={{ color: 'var(--gnf-muted)', fontSize: '0.9375rem', marginBottom: 'var(--gnf-space-5)' }}>
        Ingresa tu correo o usuario y te enviaremos un enlace para recuperar el acceso.
      </p>

      {mutation.isSuccess && (
        <Alert variant="success" title="Correo enviado">
          {mutation.data.message}
        </Alert>
      )}

      {mutation.error && <Alert variant="error">{(mutation.error as Error).message}</Alert>}

      <Input
        label="Correo o usuario"
        type="text"
        value={identifier}
        onChange={(e) => setIdentifier(e.target.value)}
        required
      />

      <div style={{ display: 'flex', gap: 'var(--gnf-space-3)', marginTop: 'var(--gnf-space-4)' }}>
        <Button type="button" variant="ghost" style={{ flex: 1 }} onClick={onBack}>
          Volver
        </Button>
        <Button type="submit" loading={mutation.isPending} icon={<Mail size={16} />} style={{ flex: 1 }}>
          Enviar enlace
        </Button>
      </div>
    </form>
  );
}
