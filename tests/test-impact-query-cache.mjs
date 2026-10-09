import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const root = new URL('../', import.meta.url);
const req = createRequire(new URL('app/package.json', root));
const ts = req('typescript');
const React = req('react');
const { renderToStaticMarkup } = req('react-dom/server');
function load(relative, dependencies = {}) {
  const code = ts.transpileModule(fs.readFileSync(new URL(`app/src/${relative}`, root), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX, target: ts.ScriptTarget.ES2020 },
  }).outputText;
  const exports = {};
  vm.runInNewContext(code, {
    exports, URLSearchParams, window: { location: { search: '' } },
    require: name => name.endsWith('.css') ? {} : dependencies[name] ?? req(name),
  });
  return exports;
}
const model = load('components/impact/model.ts');
const ready = { ready: true, refreshing: false };
assert.equal(model.getPollInterval(ready, false), 2 * 60 * 60 * 1000, 'Ready reports check again after two hours');
assert.equal(model.getPollInterval({ ...ready, refreshing: true }, false), 2 * 60 * 60 * 1000, 'Automatic server refresh does not start five-second polling on a usable cut');
assert.equal(model.getPollInterval({ ...ready, refreshing: true }, false, true), 5000, 'Explicit update polls until the new cut is published');
assert.equal(model.getPollInterval({ ready: false, refreshing: true }, false), 5000, 'Initial preparation still polls');
assert.equal(model.getPollInterval(ready, true), false, 'Errors stop automatic polling');

let queryOptions;
let fetching = false;
const placeholder = () => null;
const data = {
  ...ready, canRefresh: true, year: 2026, filters: { region: 0, circuit: '', mode: 'approved' },
  availableRegions: [], availableCircuits: [], availableSources: [],
  summary: {}, impact: { total: { label: 'Total' }, catalog: [], regions: {}, circuits: {} },
  exports: Object.fromEntries(['indicators', 'centros', 'retos', 'pdfSummary', 'pdfFull'].map(key => [key, `/signed-${key}`])),
};
const realExports = load('components/impact/ImpactExports.tsx');
const panel = load('components/impact/ImpactPanel.tsx', {
  '@tanstack/react-query': {
    useQuery: options => { queryOptions = options; return { data, isFetching: fetching, isPending: false, isError: false }; },
    useMutation: () => ({ isPending: false, isError: false }),
    useQueryClient: () => ({}),
  },
  '@/api/reports': { reportsApi: {} },
  '@/stores/useYearStore': { useYearStore: () => 2026 },
  '@/components/ui/Button': { Button: placeholder },
  '@/components/ui/Select': { Select: placeholder },
  '@/components/ui/Input': { Input: placeholder },
  './ImpactTable': { ImpactTable: placeholder },
  './ImpactChart': { ImpactChart: placeholder },
  './ImpactExports': realExports,
  './model': model,
});
const render = () => renderToStaticMarkup(React.createElement(panel.ImpactPanel));
assert.equal((render().match(/href="\/signed-/g) ?? []).length, 5, 'All five downloads are enabled with a valid cut');
fetching = true;
assert.equal((render().match(/href="\/signed-/g) ?? []).length, 5, 'Background fetching does not disable valid downloads');
assert.equal(queryOptions.staleTime, 600000, 'Browser filter results stay fresh for ten minutes');
assert.equal(queryOptions.gcTime, 600000, 'Inactive browser filter results stay cached for ten minutes');
assert.equal(queryOptions.refetchOnWindowFocus, false);
assert.equal(queryOptions.refetchOnReconnect, false);
console.log('12 impact query/cache checks passed');
