import { useEffect, useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { adminApi } from '@/api/admin';
import { useYearStore } from '@/stores/useYearStore';
import { Spinner } from '@/components/ui/Spinner';
import { Card } from '@/components/ui/Card';
import { StatsGrid } from '@/components/data/StatsGrid';
import { StatCard } from '@/components/data/StatCard';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Alert } from '@/components/ui/Alert';
import { School, Award, BarChart3, Search } from 'lucide-react';

function formatGeneratedAt(value?: string) {
  if (!value) return '';

  const date = new Date(value.includes('T') ? value : value.replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return '';

  return new Intl.DateTimeFormat('es-CR', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date);
}

export function ReportesPage() {
  const year = useYearStore((s) => s.selectedYear);
  const [selectedMetric, setSelectedMetric] = useState('centros_con_evidencias');
  const [scopeView, setScopeView] = useState<'regions' | 'circuits'>('regions');
  const [scopeSearch, setScopeSearch] = useState('');
  const [showAllScopes, setShowAllScopes] = useState(false);

  const { data, isLoading: reportsLoading } = useQuery({
    queryKey: ['admin-reports', year],
    queryFn: () => adminApi.getReports(year),
  });

  const { data: impact, isLoading: impactLoading, error: impactError } = useQuery({
    queryKey: ['admin-impact', year],
    queryFn: () => adminApi.getImpact(year),
  });

  const availableMetrics = useMemo(
    () => (impact?.catalog ?? []).filter((metric) => metric.available),
    [impact],
  );
  const currentMetric = availableMetrics.find((metric) => metric.key === selectedMetric) ?? availableMetrics[0];
  const regions = useMemo(
    () => Object.values(impact?.regions ?? {}).sort((a, b) => a.label.localeCompare(b.label, 'es')),
    [impact],
  );
  const circuits = useMemo(
    () => Object.values(impact?.circuits ?? {}).sort((a, b) =>
      (a.regionName ?? '').localeCompare(b.regionName ?? '', 'es') || a.label.localeCompare(b.label, 'es')),
    [impact],
  );
  const scopes = scopeView === 'regions' ? regions : circuits;
  const filteredScopes = useMemo(() => {
    const query = scopeSearch.trim().toLocaleLowerCase('es');

    return scopes
      .filter((scope) => {
        if (!query) return true;
        return `${scope.regionName ?? ''} ${scope.label}`.toLocaleLowerCase('es').includes(query);
      })
      .sort((a, b) => {
        const metricKey = currentMetric?.key;
        if (!metricKey) return a.label.localeCompare(b.label, 'es');
        return Number(b.values[metricKey] ?? 0) - Number(a.values[metricKey] ?? 0)
          || a.label.localeCompare(b.label, 'es');
      });
  }, [currentMetric, scopeSearch, scopes]);
  const visibleScopes = showAllScopes ? filteredScopes : filteredScopes.slice(0, 25);
  const maxScopeValue = currentMetric
    ? Math.max(1, ...filteredScopes.map((scope) => Number(scope.values[currentMetric.key] ?? 0)))
    : 1;

  useEffect(() => {
    const firstMetric = availableMetrics[0];
    if (firstMetric && !availableMetrics.some((metric) => metric.key === selectedMetric)) {
      setSelectedMetric(firstMetric.key);
    }
  }, [availableMetrics, selectedMetric]);

  useEffect(() => {
    setShowAllScopes(false);
  }, [scopeSearch, scopeView, selectedMetric]);

  if (reportsLoading || impactLoading) return <Spinner />;

  const summary = data?.summary;
  const numberFormatter = new Intl.NumberFormat('es-CR', { maximumFractionDigits: 2 });
  const generatedAtLabel = formatGeneratedAt(impact?.generatedAt);

  return (
    <div>
      <div style={{ marginBottom: 'var(--gnf-space-6)' }}>
        <h2>Reportes</h2>
      </div>

      {summary && (
        <StatsGrid>
          <StatCard label="Total centros" value={summary.totalCentros} icon={<School size={24} />} color="var(--gnf-ocean)" bg="#e0f2fe" />
          <StatCard label="Retos aprobados" value={summary.totalAprobados} icon={<Award size={24} />} color="#16a34a" bg="#dcfce7" />
          <StatCard label="Promedio puntaje" value={Math.round(summary.promedioPuntaje)} color="var(--gnf-forest)" bg="#dcfce7" />
        </StatsGrid>
      )}

      <section style={{ marginTop: 'var(--gnf-space-7)' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end', gap: 'var(--gnf-space-4)', flexWrap: 'wrap', marginBottom: 'var(--gnf-space-4)' }}>
          <div>
            <h3 style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-2)', margin: 0 }}>
              <BarChart3 size={20} /> Indicadores de impacto
            </h3>
            <p style={{ color: 'var(--gnf-muted)', margin: '6px 0 0' }}>Datos registrados con evidencia activa en {year}.</p>
            <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-2)', flexWrap: 'wrap', marginTop: '8px', color: 'var(--gnf-muted)', fontSize: '0.8125rem' }}>
              {impact?.rollout && (
                <span style={{ border: '1px solid var(--gnf-border)', borderRadius: 4, padding: '3px 7px', background: 'var(--gnf-white)', color: 'var(--gnf-text)' }}>
                  Alcance: {impact.rollout.label}
                </span>
              )}
              {generatedAtLabel && <span>Última actualización: {generatedAtLabel}</span>}
            </div>
          </div>
          {availableMetrics.length > 0 && (
            <Select
              aria-label="Indicador de impacto"
              value={currentMetric?.key ?? ''}
              onChange={(event) => setSelectedMetric(event.target.value)}
              options={availableMetrics.map((metric) => ({ value: metric.key, label: metric.title }))}
              style={{ width: 'min(100%, 420px)', marginBottom: 0 }}
            />
          )}
        </div>

        {impactError && <Alert variant="error">No fue posible calcular los indicadores de impacto.</Alert>}

        {impact && currentMetric && (
          <>
            <Card style={{ marginBottom: 'var(--gnf-space-4)' }}>
              <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1fr) auto', gap: 'var(--gnf-space-4)', alignItems: 'center' }}>
                <div>
                  <div style={{ color: 'var(--gnf-muted)', fontSize: '0.875rem' }}>{currentMetric.title}</div>
                  <div style={{ color: 'var(--gnf-text)', fontSize: '2rem', fontWeight: 700, lineHeight: 1.2 }}>
                    {numberFormatter.format(impact.total.values[currentMetric.key] ?? 0)}
                  </div>
                </div>
                <span style={{ color: 'var(--gnf-muted)', fontSize: '0.875rem' }}>{currentMetric.unit}</span>
              </div>
            </Card>

            <div style={{ marginTop: 'var(--gnf-space-5)' }}>
              <div
                role="tablist"
                aria-label="Nivel territorial"
                style={{ display: 'flex', width: 'fit-content', maxWidth: '100%', border: '1px solid var(--gnf-border)', borderRadius: 4, overflow: 'hidden', marginBottom: 'var(--gnf-space-4)' }}
              >
                <button
                  type="button"
                  role="tab"
                  aria-selected={scopeView === 'regions'}
                  onClick={() => setScopeView('regions')}
                  style={{ border: 0, borderRight: '1px solid var(--gnf-border)', padding: '8px 12px', cursor: 'pointer', background: scopeView === 'regions' ? 'var(--gnf-forest)' : 'var(--gnf-white)', color: scopeView === 'regions' ? 'var(--gnf-white)' : 'var(--gnf-text)', fontWeight: 600 }}
                >
                  Direcciones Regionales
                </button>
                <button
                  type="button"
                  role="tab"
                  aria-selected={scopeView === 'circuits'}
                  onClick={() => setScopeView('circuits')}
                  style={{ border: 0, padding: '8px 12px', cursor: 'pointer', background: scopeView === 'circuits' ? 'var(--gnf-forest)' : 'var(--gnf-white)', color: scopeView === 'circuits' ? 'var(--gnf-white)' : 'var(--gnf-text)', fontWeight: 600 }}
                >
                  Circuitos educativos
                </button>
              </div>

              <Input
                type="search"
                aria-label="Buscar DRE o circuito"
                placeholder="Buscar DRE o circuito"
                value={scopeSearch}
                onChange={(event) => setScopeSearch(event.target.value)}
                rightElement={<Search size={17} aria-hidden="true" />}
                style={{ width: 'min(100%, 360px)', marginBottom: 'var(--gnf-space-4)' }}
              />

              <div style={{ display: 'grid', gap: '10px' }}>
                {visibleScopes.map((scope) => {
                  const value = Number(scope.values[currentMetric.key] ?? 0);
                  const label = scopeView === 'circuits' && scope.regionName
                    ? `${scope.regionName} · ${scope.label}`
                    : scope.label;
                  return (
                    <div key={scope.id} className="gnf-impact-row">
                      <span style={{ overflowWrap: 'anywhere', fontSize: '0.875rem' }}>{label}</span>
                      <div style={{ background: '#e7ece9', height: 12, overflow: 'hidden', borderRadius: 4 }}>
                        <div style={{ width: `${Math.max(value > 0 ? 2 : 0, (value / maxScopeValue) * 100)}%`, height: '100%', background: '#16866f' }} />
                      </div>
                      <strong style={{ textAlign: 'right', fontSize: '0.875rem', fontVariantNumeric: 'tabular-nums' }}>{numberFormatter.format(value)}</strong>
                    </div>
                  );
                })}
              </div>

              {filteredScopes.length === 0 && (
                <p role="status" style={{ color: 'var(--gnf-muted)', margin: 'var(--gnf-space-5) 0 0' }}>
                  No hay territorios que coincidan con la búsqueda.
                </p>
              )}

              {filteredScopes.length > 25 && (
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => setShowAllScopes((current) => !current)}
                  style={{ marginTop: 'var(--gnf-space-4)' }}
                >
                  {showAllScopes ? 'Mostrar menos' : `Mostrar todos (${filteredScopes.length})`}
                </Button>
              )}
            </div>
          </>
        )}
      </section>

      <section style={{ marginTop: 'var(--gnf-space-7)' }}>
        <h3 style={{ marginBottom: 'var(--gnf-space-2)' }}>Detalle por centro</h3>
        <p style={{ color: 'var(--gnf-muted)' }}>{data?.centros.length ?? 0} centros con matrícula activa en {year}.</p>
      </section>
    </div>
  );
}
