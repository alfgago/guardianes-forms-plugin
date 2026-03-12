import { useEffect } from 'react';
import { trackClientEvent } from '@/utils/analytics';

interface TrackPageViewArgs {
  panel: string;
  page: string;
  year?: number;
}

export function useTrackPageView({ panel, page, year }: TrackPageViewArgs) {
  useEffect(() => {
    trackClientEvent('panel_visit', {
      panel,
      page,
      year,
    });
  }, [page, panel, year]);
}
