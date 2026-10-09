import type { ImpactIndicator, ReportFilters, ReportImpact, ReportsOverview } from '@/api/reports';

export type ComparisonLevel = 'regions' | 'circuits';

export function getInitialReportYear(activeYear: number, search: string) {
  const value = new URLSearchParams(search).get('year') ?? '';
  return /^\d{4}$/.test(value) && Number(value) >= 2000 && Number(value) <= 2100 ? Number(value) : activeYear;
}

export function getInitialReportFilters(search: string): Omit<ReportFilters, 'year'> {
  const params = new URLSearchParams(search);
  return {
    region: params.get('region') ?? '',
    circuit: params.get('circuit') ?? '',
    mode: params.get('mode') === 'active' ? 'active' : 'approved',
    sources: [...new Set((params.get('sources') ?? '').split(',').map((value) => value.trim()).filter(Boolean))].sort(),
  };
}

export function groupIndicators(catalog: ImpactIndicator[]) {
  const groups = new Map<string, { source: string; label: string; metrics: ImpactIndicator[] }>();
  for (const metric of catalog) {
    let group = groups.get(metric.source);
    if (!group) {
      group = { source: metric.source, label: metric.sourceLabel, metrics: [] };
      groups.set(metric.source, group);
    }
    group.metrics.push(metric);
  }
  return [...groups.values()];
}

export function comparisonScopes(impact: Pick<ReportImpact, 'regions' | 'circuits'>, level: ComparisonLevel, search = '') {
  const query = search.trim().toLocaleLowerCase('es');
  return Object.values(impact[level]).filter((scope) =>
    `${scope.regionName ?? ''} ${scope.label}`.toLocaleLowerCase('es').includes(query)).sort((a, b) =>
    (a.regionName ?? '').localeCompare(b.regionName ?? '', 'es') || a.label.localeCompare(b.label, 'es'));
}

export function getPollInterval(data: ReportsOverview | undefined, isError: boolean): number | false {
  if (isError || !data) return false;
  return !data.ready || data.refreshing ? 5000 : false;
}

export function paginateScopes<T>(scopes: T[], requestedPage: number) {
  const pageSize = 25;
  const total = scopes.length;
  const pageCount = Math.max(1, Math.ceil(total / pageSize));
  const page = Math.max(0, Math.min(requestedPage, pageCount - 1));
  return {
    rows: scopes.slice(page * pageSize, (page + 1) * pageSize),
    page, pageCount, total,
    start: total ? page * pageSize + 1 : 0,
    end: Math.min(total, (page + 1) * pageSize),
  };
}

const numberFormatter = new Intl.NumberFormat('es-CR', { maximumFractionDigits: 2 });
export function formatValue(value: number | null | undefined) {
  return typeof value === 'number' && Number.isFinite(value) ? numberFormatter.format(value) : 'Sin datos';
}

export function formatGeneratedAt(value?: string | null) {
  if (!value) return '';
  // WordPress timestamps without an offset are local Costa Rica time.
  const normalized = value.replace(' ', 'T');
  const date = new Date(/(?:Z|[+-]\d{2}:?\d{2})$/.test(normalized) ? normalized : `${normalized}-06:00`);
  if (Number.isNaN(date.getTime())) return '';
  return new Intl.DateTimeFormat('es-CR', {
    dateStyle: 'medium', timeStyle: 'short', timeZone: 'America/Costa_Rica',
  }).format(date);
}
