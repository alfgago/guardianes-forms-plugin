import { useEffect, useRef } from 'react';
import { useQuery } from '@tanstack/react-query';
import { retosApi } from '@/api/retos';
import { Spinner } from '@/components/ui/Spinner';
import { Alert } from '@/components/ui/Alert';

interface WpFormsEmbedProps {
  retoId: number;
  year: number;
}

export function WpFormsEmbed({ retoId, year }: WpFormsEmbedProps) {
  const containerRef = useRef<HTMLDivElement>(null);

  const { data, isLoading, error } = useQuery({
    queryKey: ['wpforms-html', retoId, year],
    queryFn: () => retosApi.getFormHtml(retoId, year),
    staleTime: 5 * 60 * 1000,
  });

  useEffect(() => {
    if (!data?.html || !containerRef.current) return;

    containerRef.current.innerHTML = data.html;

    // Re-initialize WPForms JS if available
    const wpforms = (window as unknown as Record<string, unknown>).wpforms as { init?: () => void } | undefined;
    if (wpforms?.init) {
      wpforms.init();
    }

    // Re-run any inline scripts in the HTML
    const scripts = containerRef.current.querySelectorAll('script');
    scripts.forEach((oldScript) => {
      const newScript = document.createElement('script');
      if (oldScript.src) {
        newScript.src = oldScript.src;
      } else {
        newScript.textContent = oldScript.textContent;
      }
      oldScript.parentNode?.replaceChild(newScript, oldScript);
    });
  }, [data?.html]);

  if (isLoading) return <Spinner label="Cargando formulario..." />;
  if (error) return <Alert variant="error">Error al cargar el formulario.</Alert>;

  return <div ref={containerRef} />;
}
