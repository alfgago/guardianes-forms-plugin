import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
const root = new URL('../', import.meta.url);
const req = createRequire(new URL('app/package.json', root));
const ts = req('typescript'); const React = req('react'); const { renderToStaticMarkup } = req('react-dom/server');
function load(path, mocks = {}) {
  const exports = {};
  const code = ts.transpileModule(fs.readFileSync(new URL('app/src/' + path, root), 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX } }).outputText;
  vm.runInNewContext(code, { exports, window: { location: { href: '' } }, require: name => mocks[name] ?? req(name) });
  return exports;
}
const result = { rubricLabel: 'Tipo IV/V', score: 90, stars: 1, thresholds: { 1: 60, 2: 90 }, missingRequired: [], awards: {} };
const { AwardSummary } = load('components/domain/AwardSummary.tsx', { '@/components/ui/Button': { Button: ({ children }) => React.createElement('button', null, children) } });
const markup = renderToStaticMarkup(React.createElement(AwardSummary, { year: 2026, award: { projected: result, validated: result } }));
assert.ok(markup.includes('gnf-award-summary'), 'Award summary has a dedicated contrasting full-width band');
assert.ok(markup.includes('Progreso estimado') && markup.includes('Resultado validado'), 'Contrast update preserves the two distinct score meanings');
const { CentroDetailPage } = load('panels/supervisor/pages/CentroDetailPage.tsx', {
  '@tanstack/react-query': { useQuery: () => ({ data: { centro: { annual: { puntajeTotal: 90, reportPdfUrl: '/stale-signed-url' } }, entries: [] } }) },
  '@/api/supervisor': { supervisorApi: {} }, '@/stores/useYearStore': { useYearStore: () => 2026 },
  '@/components/ui/Spinner': { Spinner: () => null }, '@/components/ui/Alert': { Alert: () => null },
  '@/components/ui/Button': { Button: ({ children }) => React.createElement('button', null, children) },
  '@/components/domain/CentroCard': { CentroCard: () => null }, '../components/EntryReviewCard': { EntryReviewCard: () => null },
  '../components/ChallengeReview': { ChallengeReview: () => null },
  '@/components/domain/StatusBadge': { StatusBadge: () => null }, '@/components/domain/AwardSummary': { AwardSummary: () => null },
});
const detail = renderToStaticMarkup(React.createElement(CentroDetailPage, { centroId: 1, onBack() {} }));
assert.ok(!detail.includes('Descargar') && !detail.includes('stale-signed-url'), 'Supervisor cannot see an individual report button even with a stale payload URL');
console.log('3 closing UI checks passed');
