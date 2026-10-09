import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const ts = require('typescript');
function load(relative, imports = {}) {
  const source = readFileSync(new URL(relative, import.meta.url), 'utf8');
  const js = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText;
  const exports = {};
  vm.runInNewContext(js, { exports, require: (name) => imports[name], Intl, Date, Map, Set, URLSearchParams });
  return exports;
}

test('groups every catalog row by dynamic source, preserving unavailable metrics', () => {
  const model = load('./model.ts');
  const catalog = [
    { key: 'a', source: 'custom', sourceLabel: 'Nueva fuente', available: true },
    { key: 'b', source: 'other', sourceLabel: 'Otra', available: false },
    { key: 'c', source: 'custom', sourceLabel: 'Nueva fuente', available: false },
  ];
  const groups = model.groupIndicators(catalog);
  assert.equal(groups.length, 2);
  assert.deepEqual(Array.from(groups[0].metrics, (item) => item.key), ['a', 'c']);
  assert.equal(groups[1].metrics[0].available, false);
  assert.equal(groups[0].label, 'Nueva fuente');
});

test('polls only cold or refreshing data and stops on errors', () => {
  const { getPollInterval } = load('./model.ts');
  assert.equal(getPollInterval(undefined, false), false);
  assert.equal(getPollInterval({ ready: false, refreshing: false }, false), 5000);
  assert.equal(getPollInterval({ ready: true, refreshing: true }, false), 5000);
  assert.equal(getPollInterval({ ready: true, refreshing: false, stale: true }, false), false);
  assert.equal(getPollInterval({ ready: false, refreshing: true }, true), false);
});

test('report-local year accepts quicklink year without changing the active year', () => {
  const { getInitialReportYear } = load('./model.ts');
  assert.equal(getInitialReportYear(2026, '?page=impacto&year=2025'), 2025);
  assert.equal(getInitialReportYear(2026, ''), 2026);
  assert.equal(getInitialReportYear(2026, '?year=not-a-year'), 2026);
  assert.equal(getInitialReportYear(2026, '?year=19'), 2026);
  assert.equal(getInitialReportYear(2026, '?year=2101'), 2026);
  assert.equal(getInitialReportYear(2026, '?year=2100'), 2100);
});

test('quicklink restores territory, mode and comma sources with an approved default', () => {
  const { getInitialReportFilters } = load('./model.ts');
  const filters = getInitialReportFilters('?p=reportes&region=2&circuit=01&mode=active&sources=custom,other,custom');
  assert.equal(filters.region, '2');
  assert.equal(filters.circuit, '01');
  assert.equal(filters.mode, 'active');
  assert.deepEqual(Array.from(filters.sources), ['custom', 'other']);
  assert.equal(getInitialReportFilters('').mode, 'approved');
  assert.equal(getInitialReportFilters('?mode=invalid').mode, 'approved');
});

test('missing values are not zero; zero and decimals remain valid', () => {
  const { formatValue, formatGeneratedAt } = load('./model.ts');
  assert.equal(formatValue(0), '0');
  assert.equal(formatValue(undefined), 'Sin datos');
  assert.equal(formatValue(null), 'Sin datos');
  assert.equal(formatValue(NaN), 'Sin datos');
  assert.ok(formatValue(12.5).includes('12,5'));
  assert.equal(formatGeneratedAt('invalid'), '');
  assert.ok(formatGeneratedAt('2026-10-08 10:30:00'));
});

test('circuits with identical labels retain separate regional identities', () => {
  const { comparisonScopes } = load('./model.ts');
  const rows = comparisonScopes({
    regions: {}, circuits: {
      b: { id: '2:01', label: '01', regionName: 'Heredia', values: {} },
      a: { id: '1:01', label: '01', regionName: 'Alajuela', values: {} },
    },
  }, 'circuits');
  assert.deepEqual(Array.from(rows, (row) => row.id), ['1:01', '2:01']);
  assert.deepEqual(Array.from(comparisonScopes({ regions: {}, circuits: {
    a: { id: '1:01', label: '01', regionName: 'Alajuela', values: {} },
    b: { id: '2:01', label: '01', regionName: 'Heredia', values: {} },
  } }, 'circuits', ' HEREDIA '), (row) => row.id), ['2:01']);
});

test('shared API sends all server filters and abort signal; refresh is a POST', async () => {
  const calls = [];
  const client = {
    get: (...args) => { calls.push(['GET', ...args]); return Promise.resolve({ ready: false }); },
    post: (...args) => { calls.push(['POST', ...args]); return Promise.resolve({}); },
  };
  const { reportsApi } = load('../../api/reports.ts', { './client': client });
  const signal = new AbortController().signal;
  await reportsApi.getOverview({ year: 2026, region: '2', circuit: '01', mode: 'approved', sources: ['custom', 'other'] }, signal);
  assert.equal(calls[0][1], '/reports/overview');
  assert.equal(calls[0][2].sources, 'custom,other');
  assert.equal(calls[0][2].year, 2026);
  assert.equal(calls[0][2].region, '2');
  assert.equal(calls[0][2].circuit, '01');
  assert.equal(calls[0][2].mode, 'approved');
  assert.equal(calls[0][3], signal);
  await reportsApi.getOverview({ year: 2026, region: '', circuit: '', mode: 'active', sources: [] });
  assert.equal(calls[1][2].sources, undefined);
  await reportsApi.refresh(2026);
  assert.equal(calls[2][0], 'POST');
  assert.equal(calls[2][1], '/reports/refresh');
  assert.equal(calls[2][2].year, 2026);
});

test('circuit column pages clamp safely without changing totals or indicator rows', () => {
  const { paginateScopes } = load('./model.ts');
  const scopes = Array.from({ length: 58 }, (_, id) => ({ id }));
  const first = paginateScopes(scopes, 0);
  assert.equal(first.rows.length, 25);
  assert.equal(first.start, 1);
  assert.equal(first.end, 25);
  assert.equal(first.total, 58);
  const last = paginateScopes(scopes, 99);
  assert.equal(last.page, 2);
  assert.equal(last.rows.length, 8);
  assert.equal(last.start, 51);
  assert.equal(last.end, 58);
  assert.equal(scopes.length, 58);
  const empty = paginateScopes([], 1);
  assert.equal(empty.start, 0);
  assert.equal(empty.end, 0);
  assert.equal(empty.page, 0);
});

test('preview catalog exactly matches backend titles, units, keys and source groups', () => {
  const preview = readFileSync(new URL('../../../preview-impact.html', import.meta.url), 'utf8');
  const fixture = JSON.parse(preview.match(/const catalog = (\[[\s\S]*?\]);/)[1]);
  const impactPath = fileURLToPath(new URL('../../../../includes/impact-metrics.php', import.meta.url));
  const snapshotPath = fileURLToPath(new URL('../../../../includes/report-snapshots.php', import.meta.url));
  const php = `define('ABSPATH', '.'); function add_action(){} function add_filter(){} require ${JSON.stringify(impactPath.replaceAll('\\', '/'))}; require ${JSON.stringify(snapshotPath.replaceAll('\\', '/'))}; $result=[]; foreach(gnf_get_impact_metric_catalog(2026) as $metric){$result[]=['key'=>$metric['key'],'title'=>$metric['title'],'unit'=>$metric['unit'],'source'=>gnf_report_source_key($metric),'sourceLabel'=>gnf_report_source_label($metric)];} echo json_encode($result);`;
  const actual = JSON.parse(execFileSync('php', ['-r', php], { encoding: 'utf8' }));
  assert.equal(fixture.length, 41);
  assert.deepEqual(fixture.map(({ key, title, unit, source, sourceLabel }) => ({ key, title, unit, source, sourceLabel })), actual);
  assert.ok(preview.includes('Datos de prueba'));
});
