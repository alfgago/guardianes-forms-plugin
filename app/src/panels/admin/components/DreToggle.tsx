import { useMutation, useQueryClient } from '@tanstack/react-query';
import { adminApi } from '@/api/admin';
import { useToast } from '@/components/ui/Toast';

interface Dre {
  id: number;
  name: string;
  enabled: boolean;
}

interface DreToggleProps {
  dre: Dre;
}

export function DreToggle({ dre }: DreToggleProps) {
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const mutation = useMutation({
    mutationFn: () => adminApi.toggleDre(dre.id),
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['admin-dres'] });
      toast('success', `${dre.name} ${data.enabled ? 'habilitada' : 'deshabilitada'}.`);
    },
  });

  return (
    <div
      style={{
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        padding: 'var(--gnf-space-3) var(--gnf-space-4)',
        borderBottom: '1px solid var(--gnf-gray-100)',
      }}
    >
      <span style={{ fontWeight: 500 }}>{dre.name}</span>
      <button
        onClick={() => mutation.mutate()}
        disabled={mutation.isPending}
        style={{
          width: 44,
          height: 24,
          borderRadius: 'var(--gnf-radius-full)',
          border: 'none',
          cursor: 'pointer',
          background: dre.enabled ? 'var(--gnf-forest)' : 'var(--gnf-gray-300)',
          position: 'relative',
          transition: 'background var(--gnf-transition-fast)',
        }}
      >
        <span
          style={{
            position: 'absolute',
            top: 2,
            left: dre.enabled ? 22 : 2,
            width: 20,
            height: 20,
            borderRadius: '50%',
            background: 'var(--gnf-white)',
            boxShadow: 'var(--gnf-shadow-sm)',
            transition: 'left var(--gnf-transition-fast)',
          }}
        />
      </button>
    </div>
  );
}
