import { get, post } from './client';

export type ReportMode = 'active' | 'approved';
export interface ReportFilters {
  year: number;
  region: string;
  circuit: string;
  mode: ReportMode;
  sources: string[];
}
export interface ImpactIndicator {
  key: string;
  title: string;
  unit: string;
  source: string;
  sourceLabel: string;
  available: boolean;
}
export interface ImpactScope {
  id: string | number;
  label: string;
  regionId?: string | number;
  regionName?: string;
  values: Record<string, number | null>;
  coverage?: Record<string, number>;
}
export interface ReportImpact {
  year: number;
  mode: ReportMode;
  generatedAt: string | null;
  catalog: ImpactIndicator[];
  total: ImpactScope;
  regions: Record<string, ImpactScope>;
  circuits: Record<string, ImpactScope>;
}
export interface ReportExports {
  indicators?: string | null;
  centros?: string | null;
  retos?: string | null;
  pdfSummary?: string | null;
  pdfFull?: string | null;
}
export interface ReportsOverview {
  ready: boolean;
  stale: boolean;
  refreshing: boolean;
  year: number;
  generatedAt: string | null;
  canRefresh: boolean;
  filters: {
    region: string | number | null;
    circuit: string | null;
    mode: ReportMode;
    sources: string[];
  };
  availableRegions: { id: string | number; name: string }[];
  availableCircuits: { regionId: string | number; value: string; label: string }[];
  availableSources: { value: string; label: string }[];
  impact?: ReportImpact | null;
  summary?: {
    totalCentros: number;
    totalAprobados: number;
    promedioEstrellas: number;
    promedioPuntaje: number;
    centrosConEvidencias: number;
  } | null;
  exports?: ReportExports | null;
}

export const reportsApi = {
  getOverview(filters: ReportFilters, signal?: AbortSignal) {
    const { year, region, circuit, mode, sources } = filters;
    return get<ReportsOverview>('/reports/overview', {
      year, region, circuit, mode, sources: sources.length ? sources.join(',') : undefined,
    }, signal);
  },
  refresh(year: number) {
    return post<unknown>('/reports/refresh', { year });
  },
};
