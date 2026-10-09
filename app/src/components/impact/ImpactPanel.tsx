import { useEffect, useId, useMemo, useState, type ReactNode } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Award, BarChart3, CalendarDays, Camera, ChevronDown, ChevronLeft, ChevronRight, RefreshCw, School, Search, Star } from 'lucide-react';
import { reportsApi, type ReportMode, type ReportsOverview } from '@/api/reports';
import { useYearStore } from '@/stores/useYearStore';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import { Input } from '@/components/ui/Input';
import { ImpactTable } from './ImpactTable';
import { ImpactChart } from './ImpactChart';
import { ImpactExports } from './ImpactExports';
import { comparisonScopes, formatGeneratedAt, formatValue, getInitialReportFilters, getInitialReportYear, getPollInterval, paginateScopes, type ComparisonLevel } from './model';
import './impact.css';

export function ImpactPanel() {
  const selectedYear = useYearStore((state) => state.selectedYear);
  const [year, setYear] = useState(() => getInitialReportYear(selectedYear, window.location.search));
  const [yearInput, setYearInput] = useState(String(year));
  const yearId = useId();
  useEffect(() => {
    const initial = getInitialReportYear(selectedYear, window.location.search);
    setYear(initial);
    setYearInput(String(initial));
  }, [selectedYear]);
  const yearControl = <div className="gnf-impact-year">
    <label htmlFor={yearId}><CalendarDays size={15} aria-hidden="true" /> Año</label>
    <input id={yearId} type="number" min="2000" max="2100" step="1" value={yearInput}
      onChange={(event) => {
        const value = event.target.value;
        setYearInput(value);
        if (/^\d{4}$/.test(value) && Number(value) >= 2000 && Number(value) <= 2100) setYear(Number(value));
      }}
      onBlur={() => setYearInput(String(year))} />
  </div>;
  return <ImpactOverview year={year} yearControl={yearControl} />;
}

function ImpactOverview({ year, yearControl }: { year: number; yearControl: ReactNode }) {
  const [initialFilters] = useState(() => getInitialReportFilters(window.location.search));
  const [region, setRegion] = useState(initialFilters.region);
  const [circuit, setCircuit] = useState(initialFilters.circuit);
  const [mode, setMode] = useState<ReportMode>(initialFilters.mode);
  const [sources, setSources] = useState<string[]>(initialFilters.sources);
  const [level, setLevel] = useState<ComparisonLevel>('regions');
  const [scopeSearch, setScopeSearch] = useState('');
  const [circuitPage, setCircuitPage] = useState(0);
  const [filterOptions, setFilterOptions] = useState<Pick<ReportsOverview, 'availableRegions' | 'availableCircuits' | 'availableSources'> | null>(null);
  const id = useId();
  const queryClient = useQueryClient();
  const query = useQuery({
    queryKey: ['reports-overview', year, region, circuit, mode, sources.join(',')],
    queryFn: ({ signal }) => reportsApi.getOverview({ year, region, circuit, mode, sources }, signal),
    staleTime: 60_000,
    refetchOnWindowFocus: false,
    retry: 1,
    refetchInterval: (current) => getPollInterval(current.state.data, current.state.status === 'error'),
  });
  const { data } = query;
  useEffect(() => {
    if (data) setFilterOptions({ availableRegions: data.availableRegions, availableCircuits: data.availableCircuits, availableSources: data.availableSources });
  }, [data]);
  const refresh = useMutation({
    mutationFn: () => reportsApi.refresh(year),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['reports-overview', year] }),
  });
  const impact = data?.ready ? data.impact : null;
  const scopes = useMemo(() => impact ? comparisonScopes(impact, level, scopeSearch) : [], [impact, level, scopeSearch]);
  const circuitPagination = paginateScopes(scopes, circuitPage);
  const tableScopes = level === 'circuits' ? circuitPagination.rows : scopes;
  useEffect(() => { setCircuitPage(0); }, [year, scopeSearch, level, region, circuit, mode, sources]);
  const serverRegion = String(data?.filters.region ?? region);
  const effectiveRegion = serverRegion === '0' ? '' : serverRegion;
  const effectiveCircuit = data?.filters.circuit ?? circuit;
  const options = data ?? filterOptions;
  const regions = options?.availableRegions ?? [];
  const circuits = (options?.availableCircuits ?? []).filter((item) => !effectiveRegion || String(item.regionId) === effectiveRegion);
  const selectedCircuit = circuits.find((item) => item.value === effectiveCircuit);
  const sourceChoices = options?.availableSources ?? [];
  const summary = data?.ready ? data.summary : null;
  const generatedAt = formatGeneratedAt(data?.generatedAt);
  const usable = Boolean(data?.ready && impact);
  const pending = query.isPending || (!data?.ready && !query.isError);
  const stats = [
    { label: 'Centros en el alcance', value: summary?.totalCentros, Icon: School, color: 'ocean' },
    { label: 'Retos aprobados', value: summary?.totalAprobados, Icon: Award, color: 'forest' },
    { label: 'Promedio de estrellas', value: summary?.promedioEstrellas, Icon: Star, color: 'gold' },
    { label: 'Promedio de puntaje', value: summary?.promedioPuntaje, Icon: BarChart3, color: 'ocean' },
    { label: 'Centros con evidencias', value: summary?.centrosConEvidencias, Icon: Camera, color: 'forest' },
  ];

  function changeSources(value: string, checked: boolean) {
    setSources((current) => checked ? [...current, value].sort() : current.filter((item) => item !== value));
  }

  return <div className="gnf-impact">
    <header className="gnf-impact-header">
      <div>
        <h2>Panel de Impacto</h2>
        <div className="gnf-impact-timestamp" role="status" aria-live="polite">
          {generatedAt ? <>Última actualización: <time dateTime={data?.generatedAt?.replace(' ', 'T') ?? undefined}>{generatedAt}</time></> : 'Sin actualización disponible'}
          {data?.refreshing && <span className="gnf-impact-status">Actualizando en segundo plano</span>}
          {data?.stale && <span className="gnf-impact-status gnf-impact-status-stale">Datos pendientes de actualizar</span>}
        </div>
      </div>
      {data?.canRefresh && <Button variant="outline" size="sm" icon={<RefreshCw size={16} />} title="Actualizar indicadores"
        loading={refresh.isPending} disabled={data.refreshing || query.isFetching}
        onClick={() => refresh.mutate()}>Actualizar</Button>}
    </header>

    <div className="gnf-impact-filters" aria-label="Filtros del panel">
      {yearControl}
      <Select id={`${id}-region`} label="DRE" value={effectiveRegion}
        options={[{ value: '', label: 'Todas las DRE autorizadas' }, ...regions.map((item) => ({ value: String(item.id), label: item.name }))]}
        onChange={(event) => { setRegion(event.target.value); setCircuit(''); }} style={{ marginBottom: 0 }} />
      <Select id={`${id}-circuit`} label="Circuito" value={selectedCircuit ? `${selectedCircuit.regionId}:${selectedCircuit.value}` : ''}
        options={[{ value: '', label: 'Todos los circuitos autorizados' }, ...circuits.map((item) => ({
          value: `${item.regionId}:${item.value}`,
          label: effectiveRegion ? item.label : `${regions.find((r) => String(r.id) === String(item.regionId))?.name ?? item.regionId} / ${item.label}`,
        }))]}
        onChange={(event) => {
          const item = circuits.find((entry) => `${entry.regionId}:${entry.value}` === event.target.value);
          setCircuit(item?.value ?? '');
          if (item) setRegion(String(item.regionId));
        }} style={{ marginBottom: 0 }} />
      <Select id={`${id}-mode`} label="Resultados" value={mode}
        options={[{ value: 'approved', label: 'Resultados validados' }, { value: 'active', label: 'Resultados reportados' }]}
        onChange={(event) => setMode(event.target.value as ReportMode)} style={{ marginBottom: 0 }} />
      <details className="gnf-impact-source-filter">
        <summary><span>Fuente</span><span>{sources.length ? `${sources.length} seleccionadas` : 'Todas las fuentes'}<ChevronDown size={16} aria-hidden="true" /></span></summary>
        <fieldset>
          <legend className="gnf-impact-sr-only">Fuentes del indicador</legend>
          <label><input type="checkbox" checked={!sources.length} onChange={() => setSources([])} />Todas las fuentes</label>
          {sourceChoices.map((source) => <label key={source.value}>
            <input type="checkbox" value={source.value} checked={sources.includes(source.value)}
              onChange={(event) => changeSources(source.value, event.target.checked)} />{source.label}
          </label>)}
        </fieldset>
      </details>
    </div>

    <dl className="gnf-impact-stats" aria-label="Resumen del alcance seleccionado">
      {stats.map(({ label, value, Icon, color }) => <div key={label}>
        <dt><Icon size={18} className={`gnf-impact-stat-${color}`} aria-hidden="true" />{label}</dt>
        <dd>{summary ? formatValue(value) : '-'}</dd>
      </div>)}
    </dl>

    {(query.isError || refresh.isError) && <div className="gnf-impact-error" role="alert">
      <span>{query.isError ? 'No fue posible cargar el panel de impacto.' : 'No fue posible solicitar la actualización.'}</span>
      <Button variant="ghost" size="sm" icon={<RefreshCw size={16} />} loading={query.isFetching || refresh.isPending}
        onClick={() => query.isError ? void query.refetch() : refresh.mutate()}>Reintentar</Button>
    </div>}

    <ImpactExports exports={data?.exports} enabled={usable && !query.isFetching && !query.isError} />

    <section className="gnf-impact-indicators" aria-labelledby={`${id}-indicators`} aria-busy={pending}>
      <div className="gnf-impact-section-heading">
        <h3 id={`${id}-indicators`}>Indicadores de impacto</h3>
        <div className="gnf-impact-segments" role="group" aria-label="Comparación territorial">
          <button type="button" aria-pressed={level === 'regions'} onClick={() => setLevel('regions')}>Direcciones Regionales</button>
          <button type="button" aria-pressed={level === 'circuits'} onClick={() => setLevel('circuits')}>Circuitos educativos</button>
        </div>
      </div>
      <Input type="search" aria-label="Buscar DRE o circuito" placeholder="Buscar DRE o circuito"
        value={scopeSearch} onChange={(event) => setScopeSearch(event.target.value)} rightElement={<Search size={17} aria-hidden="true" />}
        style={{ width: 'min(100%, 360px)', marginTop: 16, marginBottom: 0 }} />
      {impact && <p className="gnf-impact-caption">{impact.total.label} · {year} · {mode === 'approved' ? 'Resultados validados' : 'Resultados reportados'}</p>}
      <p className="gnf-impact-cutoff">Corte: {generatedAt || 'Pendiente'} · Evidencias pausadas excluidas</p>
      {pending && <div className="gnf-impact-preparing" role="status">
        <RefreshCw size={24} className="gnf-impact-spin" aria-hidden="true" />
        <strong>Preparando indicadores</strong><span>{year} · {generatedAt ? `Última actualización: ${generatedAt}` : 'Sin actualización disponible'}</span>
      </div>}
      {impact && level === 'circuits' && <div className="gnf-impact-pagination" role="group" aria-label="Páginas de columnas de circuitos">
        <span aria-live="polite">Circuitos {circuitPagination.start}-{circuitPagination.end} de {circuitPagination.total}</span>
        <Button type="button" variant="ghost" size="sm" aria-label="Circuitos anteriores" title="Circuitos anteriores"
          disabled={circuitPagination.page === 0} onClick={() => setCircuitPage(circuitPagination.page - 1)} icon={<ChevronLeft size={18} />} />
        <Button type="button" variant="ghost" size="sm" aria-label="Circuitos siguientes" title="Circuitos siguientes"
          disabled={circuitPagination.page + 1 >= circuitPagination.pageCount} onClick={() => setCircuitPage(circuitPagination.page + 1)} icon={<ChevronRight size={18} />} />
      </div>}
      {impact && <ImpactTable impact={impact} scopes={tableScopes} circuits={level === 'circuits'} />}
      {impact && !scopes.length && <p role="status" className="gnf-impact-empty">No hay territorios que coincidan con la búsqueda.</p>}
      {!pending && !impact && !query.isError && <p role="status" className="gnf-impact-empty">No hay datos para el alcance seleccionado.</p>}
    </section>
    {impact && <ImpactChart catalog={impact.catalog} scopes={scopes} circuits={level === 'circuits'} />}
  </div>;
}
