import { get, put } from './client';
import type { Centro, CentroSearchResult } from '@/types';

export const centrosApi = {
  search(term: string, signal?: AbortSignal) {
    return get<CentroSearchResult[]>('/centros/search', { term }, signal);
  },

  getById(id: number) {
    return get<Centro>(`/centros/${id}`);
  },

  update(id: number, data: Partial<Centro>) {
    return put<Centro>(`/centros/${id}`, data);
  },
};
