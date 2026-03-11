import { Bell } from 'lucide-react';
import { useNotificationStore } from '@/stores/useNotificationStore';

interface NotificationBellProps {
  onClick?: () => void;
}

export function NotificationBell({ onClick }: NotificationBellProps) {
  const unreadCount = useNotificationStore((s) => s.unreadCount);

  return (
    <button
      onClick={onClick}
      aria-label={`Notificaciones (${unreadCount} sin leer)`}
      style={{
        position: 'relative',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        width: 40,
        height: 40,
        border: 'none',
        background: 'none',
        cursor: 'pointer',
        borderRadius: 'var(--gnf-radius-sm)',
        color: 'var(--gnf-gray-600)',
      }}
    >
      <Bell size={20} />
      {unreadCount > 0 && (
        <span
          style={{
            position: 'absolute',
            top: 4,
            right: 4,
            minWidth: 16,
            height: 16,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            borderRadius: 'var(--gnf-radius-full)',
            background: 'var(--gnf-coral)',
            color: 'var(--gnf-white)',
            fontSize: '0.625rem',
            fontWeight: 700,
            padding: '0 4px',
          }}
        >
          {unreadCount > 9 ? '9+' : unreadCount}
        </span>
      )}
    </button>
  );
}
