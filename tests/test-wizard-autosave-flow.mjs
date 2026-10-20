import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const req = createRequire(new URL('../app/package.json', import.meta.url));
const React = req('react');
const { renderToStaticMarkup } = req('react-dom/server');
const ts = req('typescript');
const source = fs.readFileSync(new URL('../app/src/panels/docente/components/WizardView.tsx', import.meta.url), 'utf8');
const steps = [1, 2].map(id => ({ retoId: id, retoTitulo: `Reto ${id}`, estado: 'en_progreso', puntaje: 5 }));
let currentIndex = 0;
let buttons = [];
let embedded = [];
const mutations = [];
const exports = {};
const mocks = {
  react: { ...React, useState: () => [currentIndex, next => { currentIndex = typeof next === 'function' ? next(currentIndex) : next; }], useEffect() {} },
  '@tanstack/react-query': { useQuery: () => ({ data: steps }), useQueryClient: () => ({ invalidateQueries() {} }), useMutation: options => ({ mutate: id => options.mutationFn(id) }) },
  '@/api/retos': { retosApi: { reopenReto: (id, year) => mutations.push({ id, year }) } },
  '@/components/domain/WpFormsEmbed': { WpFormsEmbed: props => { embedded.push(props); return React.createElement('div', null, 'Formulario con guardado'); } },
  '@/components/ui/Button': { Button: props => { buttons.push(props); return React.createElement('button', { disabled: props.disabled }, props.children); } },
  '@/components/ui/Alert': { Alert: ({ title, children }) => React.createElement('div', null, title, children) },
  '@/components/ui/Spinner': { Spinner: () => null },
  '@/components/domain/StatusBadge': { StatusBadge: () => null },
  '@/utils/analytics': { trackClientEvent() {} },
};
vm.runInNewContext(ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX } }).outputText,
  { exports, require: name => mocks[name] ?? req(name) });
function render() {
  buttons = []; embedded = [];
  return renderToStaticMarkup(React.createElement(exports.WizardView, { year: 2026 }));
}
function button(text) { return buttons.find(b => (Array.isArray(b.children) ? b.children : [b.children]).includes(text)); }
assert.ok(!render().includes('Enviar'), 'No final submission tab or screen');
assert.equal(embedded[0].retoId, 1);
assert.equal(embedded[0].year, 2026);
assert.equal(button('Anterior').disabled, true);
button('Siguiente').onClick();
assert.ok(render().includes('2 de 2'));
assert.equal(embedded[0].retoId, 2);
assert.equal(button('Siguiente').disabled, true, 'Last challenge has no extra submission step');
button('Siguiente').onClick();
assert.ok(render().includes('2 de 2'), 'Even repeated navigation stays on a valid challenge');
button('Anterior').onClick();
assert.ok(render().includes('1 de 2'));
steps[0].estado = 'completo';
assert.ok(!render().includes('enviar'), 'Legacy completed entries no longer ask for final submission');
steps[0].estado = 'correccion';
render(); button('Reabrir reto').onClick();
assert.deepEqual(mutations, [{ id: 1, year: 2026 }], 'Correction/reopen action remains available');
steps.length = 0;
assert.ok(render().includes('No hay retos disponibles'));
assert.equal(embedded.length, 0);
console.log('Teacher challenge navigation and correction checks passed');
