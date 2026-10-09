import type { ImpactScope, ReportImpact } from '@/api/reports';
import { formatValue, groupIndicators } from './model';

function MetricValue({ scope, metricKey, available }: { scope: ImpactScope; metricKey: string; available: boolean }) {
  const coverage = scope.coverage?.[metricKey];
  return <>
    <span>{formatValue(available ? scope.values[metricKey] : null)}</span>
    {available && coverage !== undefined && <small className="gnf-impact-coverage">{coverage} centros con datos</small>}
  </>;
}

export function ImpactTable({ impact, scopes, circuits }: {
  impact: ReportImpact;
  scopes: ImpactScope[];
  circuits: boolean;
}) {
  const groups = groupIndicators(impact.catalog);
  return (
    <div className="gnf-impact-table-scroll" tabIndex={0} role="region" aria-label="Indicadores de impacto por territorio">
      <table className="gnf-impact-table">
        <caption className="gnf-impact-sr-only">Todos los indicadores por fuente, total del alcance seleccionado y comparación territorial</caption>
        <thead>
          <tr>
            <th scope="col">Indicador</th>
            <th scope="col">Unidad</th>
            <th scope="col" className="gnf-impact-total">Total del alcance</th>
            {scopes.map((scope) => (
              <th scope="col" key={scope.id}>
                {circuits && scope.regionName && <span className="gnf-impact-territory-region">{scope.regionName}</span>}
                {scope.label}
              </th>
            ))}
          </tr>
        </thead>
        {groups.map((group) => (
          <tbody key={group.source}>
            <tr className="gnf-impact-source-row">
              <th scope="rowgroup" colSpan={scopes.length + 3}>{group.label}</th>
            </tr>
            {group.metrics.map((metric) => (
              <tr key={metric.key} data-metric-key={metric.key}>
                <th scope="row">
                  {metric.title}
                  <span className="gnf-impact-mobile-unit">{metric.unit}</span>
                  {!metric.available && <span className="gnf-impact-unavailable">No disponible</span>}
                </th>
                <td className="gnf-impact-unit">{metric.unit}</td>
                <td className="gnf-impact-total"><MetricValue scope={impact.total} metricKey={metric.key} available={metric.available} /></td>
                {scopes.map((scope) => (
                  <td key={scope.id}><MetricValue scope={scope} metricKey={metric.key} available={metric.available} /></td>
                ))}
              </tr>
            ))}
          </tbody>
        ))}
      </table>
      {!groups.length && <p className="gnf-impact-empty" role="status">No hay indicadores para los filtros seleccionados.</p>}
    </div>
  );
}
