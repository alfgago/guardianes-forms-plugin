import { useEffect, useMemo, useState } from 'react';
import { Tabs } from '@/components/ui/Tabs';
import { Card } from '@/components/ui/Card';
import { LoginForm } from './LoginForm';
import { DocenteRegisterForm } from './DocenteRegisterForm';
import { SupervisorRegisterForm } from './SupervisorRegisterForm';
import { ForgotPasswordForm } from './ForgotPasswordForm';
import { ResetPasswordForm } from './ResetPasswordForm';
import { useInitData } from '@/hooks/useInitData';
import { useTrackPageView } from '@/hooks/useTrackPageView';

const HOME_URL = 'https://movimientoguardianes.org/bae2026/';

const allTabs = [
  { id: 'login', label: 'Ingresar' },
  { id: 'register-docente', label: 'Registrar centro educativo' },
  { id: 'register-supervisor', label: 'Registrarse (DRE - Supervisores)' },
];

export function AuthPanel() {
  const initData = useInitData('auth');
  const authLogoUrl = initData.authLogoUrl || initData.logoUrl;
  const defaultTab = (initData.defaultTab as string) ?? 'login';
  const redirectTo = (initData.redirectTo as string) ?? '';
  const searchParams = new URLSearchParams(window.location.search);
  const isResetMode = searchParams.get('reset') === '1';
  const resetLogin = searchParams.get('login') ?? '';
  const resetKey = searchParams.get('key') ?? '';

  const headerContent = useMemo(() => {
    if (redirectTo === 'docente') {
      return {
        title: 'Plataforma Bandera Azul - Centros Educativos',
        subtitle: 'Acceso para centros educativos.',
      };
    }

    if (redirectTo === 'supervisor') {
      return {
        title: 'Plataforma Bandera Azul - DRE',
        subtitle: 'Acceso para Direcciones Regionales y Supervisores.',
      };
    }

    if (redirectTo === 'comite') {
      return {
        title: 'Plataforma Bandera Azul - DRE',
        subtitle: 'Acceso para Direcciones Regionales y Supervisores.',
      };
    }

    if (redirectTo === 'admin') {
      return {
        title: 'Plataforma Bandera Azul - Administracion',
        subtitle: 'Acceso para administracion de la plataforma.',
      };
    }

    return {
      title: 'Plataforma Bandera Azul',
      subtitle: 'Acceso para centros educativos, supervisiones y comites regionales.',
    };
  }, [redirectTo]);

  const tabs = useMemo(() => {
    if (redirectTo === 'docente') {
      return allTabs.filter((tab) => tab.id !== 'register-supervisor');
    }

    if (redirectTo === 'supervisor' || redirectTo === 'comite') {
      return allTabs.filter((tab) => tab.id !== 'register-docente');
    }

    return allTabs;
  }, [redirectTo]);

  const safeDefaultTab = tabs.some((tab) => tab.id === defaultTab) ? defaultTab : tabs[0]?.id ?? 'login';
  const [activeTab, setActiveTab] = useState(safeDefaultTab);
  const [forgotMode, setForgotMode] = useState(false);
  useTrackPageView({ panel: 'auth', page: isResetMode ? 'reset-password' : forgotMode ? 'forgot-password' : activeTab });

  useEffect(() => {
    if (!tabs.some((tab) => tab.id === activeTab)) {
      setActiveTab(safeDefaultTab);
    }
  }, [activeTab, safeDefaultTab, tabs]);

  function handleBackToLogin() {
    setForgotMode(false);
    setActiveTab('login');
  }

  function renderContent() {
    if (isResetMode && resetLogin && resetKey) {
      return <ResetPasswordForm login={resetLogin} resetKey={resetKey} onBack={handleBackToLogin} />;
    }

    if (forgotMode) {
      return <ForgotPasswordForm onBack={handleBackToLogin} />;
    }

    if (activeTab === 'login') {
      return <LoginForm onForgotPassword={() => setForgotMode(true)} />;
    }

    if (activeTab === 'register-docente') {
      return <DocenteRegisterForm />;
    }

    return <SupervisorRegisterForm />;
  }

  return (
    <div
      style={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        padding: 'var(--gnf-space-6)',
        background: 'linear-gradient(135deg, #e8f7ef 0%, #f7fbff 52%, #fff8e9 100%)',
      }}
    >
      <Card style={{ width: '100%', maxWidth: 560 }} padding="var(--gnf-space-8)">
        <div style={{ textAlign: 'center', marginBottom: 'var(--gnf-space-6)' }}>
          {authLogoUrl && (
            <img
              src={authLogoUrl}
              alt="Bandera Azul y Guardianes"
              style={{
                height: 72,
                width: 'auto',
                margin: '0 auto var(--gnf-space-4)',
              }}
            />
          )}
          <h1 style={{ color: 'var(--gnf-ocean-dark)', fontSize: '1.65rem', marginBottom: 'var(--gnf-space-2)' }}>
            {headerContent.title}
          </h1>
          <p style={{ color: 'var(--gnf-muted)', fontSize: '0.9375rem', marginBottom: 0 }}>
            {headerContent.subtitle}
          </p>
          <a
            href={HOME_URL}
            style={{
              display: 'inline-flex',
              alignItems: 'center',
              justifyContent: 'center',
              marginTop: 'var(--gnf-space-3)',
              color: 'var(--gnf-forest)',
              fontSize: '0.9375rem',
              fontWeight: 700,
              textDecoration: 'none',
            }}
          >
            Ir al sitio principal
          </a>
        </div>

        {!forgotMode && !isResetMode && <Tabs tabs={tabs} active={activeTab} onChange={setActiveTab} />}

        {renderContent()}
      </Card>
    </div>
  );
}
