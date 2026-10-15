import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
const req = createRequire(new URL('../app/package.json', import.meta.url));
const ts = req('typescript');
function load(path, imports = {}) {
  const exports = {};
  const code = ts.transpileModule(fs.readFileSync(new URL('../app/src/' + path, import.meta.url), 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText;
  vm.runInNewContext(code, { exports, require: name => imports[name] });
  return exports;
}
const statuses = load('utils/evidenceReview.ts');
const { reviewCounts, evidenceMatches, nextPending } = load('panels/supervisor/components/reviewNavigation.ts', { '@/utils/evidenceReview': statuses });
const entry = { id: 1, retoId: 10, evidencias: [
  { field_id: 5, estado: 'pendiente', replaced: true },
  { field_id: 5, estado: 'pendiente', puntos: null },
  { field_id: 5, estado: 'aprobada', puntos: 0 },
  { field_id: 5, estado: 'rechazada' },
  { field_id: 5, estado: 'en_pausa' },
  { field_id: 0, reviewable: false, estado: null, puntos: null },
] };
assert.deepEqual(JSON.parse(JSON.stringify(reviewCounts(entry))), { all: 5, pendiente: 1, aprobada: 1, rechazada: 1, en_pausa: 1 });
assert.equal(evidenceMatches(entry.evidencias[5], 'pendiente'), false);
assert.equal(evidenceMatches(entry.evidencias[1], 'pendiente'), true);
assert.equal(evidenceMatches(entry.evidencias[0], 'all'), false);
const other = { id: 2, retoId: 20, evidencias: [{ field_id: 99, estado: 'pendiente' }] };
assert.equal(nextPending([entry, other], 10, -1).index, 1);
assert.equal(nextPending([entry, other], 10, 1).retoId, 20);
assert.equal(nextPending([entry, other], 20, 0).retoId, 10);
assert.equal(nextPending([{ ...entry, id: 0 }], 10, -1), undefined);
assert.equal(nextPending([{ ...entry, evidencias: [] }], 10, -1), undefined);
console.log('9 review navigation checks passed');
