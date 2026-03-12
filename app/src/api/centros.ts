import { get, put } from './client';
import type { Centro, CentroSearchResult } from '@/types';

export const centrosApi = {
  search(
    term: string,
    options?: { region?: number; includeClaimed?: boolean; signal?: AbortSignal },
  ) {
    return get<CentroSearchResult[]>(
      '/centros/search',
      { term, region: options?.region, includeClaimed: options?.includeClaimed ? 1 : undefined },
      options?.signal,
    );
  },

  getById(id: number) {
    return get<Centro>(`/centros/${id}`);
  },

  update(id: number, data: Partial<Centro>) {
    return put<Centro>(`/centros/${id}`, data);
  },
};
