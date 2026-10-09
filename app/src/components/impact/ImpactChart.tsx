import { useState } from 'react';
import { BarChart3 } from 'lucide-react';
import type { ImpactIndicator, ImpactScope } from '@/api/reports';
import { Select } from '@/components/ui/Select';
import { Button } from '@/components/ui/Button';
import { formatValue } from './model';

export function ImpactChart({ catalog, scopes, circuits }: {
  catalog: ImpactIndicator[];
  scopes: ImpactScope[];
  circuits: boolean;
}) {
  const [metricKey, setMetricKey] = useState('');
  const [showAll, setShowAll] = useState(false);
  const available = catalog.filter((metric) => metric.available);
  const metric = available.find((item) => item.key === metricKey) ?? available[0];
  if (!metric) return null;
  const rows = scopes.filter((scope) => typeof scope.values[metric.key] === 'number')
    .sort((a, b) => Number(b.values[metric.key]) - Number(a.values[metric.key]));
  const max = Math.max(1, ...rows.map((row) => Math.abs(Number(row.values[metric.key]))));
  const visible = showAll ? rows : rows.slice(0, 10);
  return (
    <section className="gnf-impact-charts" aria-labelledby="impact-chart-heading">
      <div className="gnf-impact-section-heading">
        <h3 id="impact-chart-heading"><BarChart3 size={19} aria-hidden="true" /> Comparación territorial</h3>
        <Select id="impact-chart-metric" aria-label="Indicador del gráfico" value={metric.key}
          onChange={(event) => { setMetricKey(event.target.value); setShowAll(false); }}
          options={available.map((item) => ({ value: item.key, label: `${item.sourceLabel} / ${item.title}` }))}
          style={{ marginBottom: 0 }} />
      </div>
      <p className="gnf-impact-caption">{metric.title} · {metric.unit}</p>
      <ul className="gnf-impact-bars" aria-label={`${metric.title} por territorio`}>
        {visible.map((scope) => {
          const value = Number(scope.values[metric.key]);
          return <li key={scope.id}>
            <span>{circuits && scope.regionName ? `${scope.regionName} / ${scope.label}` : scope.label}</span>
            <div className="gnf-impact-bar-track" aria-hidden="true">
              <div className={value < 0 ? 'gnf-impact-bar-negative' : ''} style={{ width: `${Math.abs(value) / max * 100}%` }} />
            </div>
            <strong>{formatValue(value)}</strong>
          </li>;
        })}
      </ul>
      {!rows.length && <p role="status" className="gnf-impact-empty">Sin datos territoriales para este indicador.</p>}
      {rows.length > 10 && <Button variant="ghost" size="sm" onClick={() => setShowAll(!showAll)}>
        {showAll ? 'Mostrar menos' : `Ver todos (${rows.length})`}
      </Button>}
    </section>
  );
}
