import { useState } from 'react';
import { Tabs } from '@/components/ui/Tabs';
import { Card } from '@/components/ui/Card';
import { LoginForm } from './LoginForm';
import { DocenteRegisterForm } from './DocenteRegisterForm';
import { SupervisorRegisterForm } from './SupervisorRegisterForm';
import { useInitData } from '@/hooks/useInitData';

const tabs = [
  { id: 'login', label: 'Iniciar Sesión' },
  { id: 'register-docente', label: 'Registro Docente' },
  { id: 'register-supervisor', label: 'Registro Supervisor' },
];

export function AuthPanel() {
  const initData = useInitData('auth');
  const defaultTab = (initData.defaultTab as string) ?? 'login';
  const [activeTab, setActiveTab] = useState(defaultTab);

  return (
    <div
      style={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        padding: 'var(--gnf-space-6)',
        background: 'linear-gradient(135deg, var(--gnf-sky) 0%, var(--gnf-sand) 100%)',
      }}
    >
      <Card style={{ width: '100%', maxWidth: 520 }} padding="var(--gnf-space-8)">
        <div style={{ textAlign: 'center', marginBottom: 'var(--gnf-space-6)' }}>
          <h1 style={{ color: 'var(--gnf-forest)', fontSize: '1.5rem', marginBottom: 'var(--gnf-space-2)' }}>
            Guardianes
          </h1>
          <p style={{ color: 'var(--gnf-muted)', fontSize: '0.875rem' }}>
            Plataforma Bandera Azul Ecológica
          </p>
        </div>

        <Tabs tabs={tabs} active={activeTab} onChange={setActiveTab} />

        {activeTab === 'login' && <LoginForm />}
        {activeTab === 'register-docente' && <DocenteRegisterForm />}
        {activeTab === 'register-supervisor' && <SupervisorRegisterForm />}
      </Card>
    </div>
  );
}
