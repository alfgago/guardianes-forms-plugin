import { execFileSync } from 'node:child_process';
import { existsSync, openSync, closeSync, readFileSync, unlinkSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { tmpdir } from 'node:os';
import assert from 'node:assert/strict';
const origin = process.env.GNF_PREVIEW_ORIGIN || 'http://127.0.0.1:5186';
const screenshots = join(tmpdir(), 'gnf-wpforms-browser');
mkdirSync(screenshots, { recursive: true });
let binary = process.env.AGENT_BROWSER_BIN || 'agent-browser';
if (process.platform === 'win32' && !process.env.AGENT_BROWSER_BIN) {
  binary = execFileSync('where.exe', ['agent-browser'], { encoding: 'utf8' }).trim().split(/\r?\n/)
    .map(path => join(dirname(path), 'node_modules/agent-browser/bin/agent-browser-win32-x64.exe')).find(existsSync);
}
assert.ok(binary);
let checks = 0;
function browser(...args) {
  const log = join(tmpdir(), `gnf-wpforms-${process.pid}.log`), fd = openSync(log, 'w');
  try { execFileSync(binary, ['--session', 'gnf-wpforms-test', ...args], { stdio: ['ignore', fd, fd], timeout: 30000, windowsHide: true }); return readFileSync(log, 'utf8').trim(); }
  finally { closeSync(fd); unlinkSync(log); }
}
function evaluate(expression) { return JSON.parse(browser('eval', expression)); }
async function verify(expression, message) {
  for (let i = 0; i < 30; i++) { if (evaluate(expression)) { checks++; console.log('ok: ' + message); return; } await new Promise(r => setTimeout(r, 100)); }
  throw new Error(message);
}
try {
  browser('set', 'viewport', '1366', '900');
  for (const mode of ['broken', 'missing', 'no-fields', 'empty']) {
    browser('open', `${origin}/preview-docente.html?p=formularios&paused=1&form-mode=${mode}`);
    await verify(`document.body.innerText.includes('Preguntas no disponibles')`, `${mode}: blank form has a visible recovery state`);
    await verify(`Array.from(document.querySelectorAll('button')).some(b => b.innerText.includes('Guardar ahora') && b.disabled)`, `${mode}: saving an unavailable form is disabled`);
    await verify(`document.body.innerText.includes('actividad.jpg') && document.body.innerText.includes('Resumen del reto')`, `${mode}: existing evidence and summary remain visible`);
    await verify(`!window.__formRequests.some(r => r.path.endsWith('/autosave'))`, `${mode}: no empty autosave can overwrite existing answers`);
  }
  browser('screenshot', join(screenshots, 'unavailable-desktop.png'), '--full');
  browser('set', 'viewport', '390', '844');
  await verify(`document.documentElement.scrollWidth <= innerWidth`, 'Recovery fits the mobile viewport');
  await verify(`Array.from(document.querySelectorAll('[role=alert]')).every(a => { const b = a.getBoundingClientRect(); return Array.from(a.querySelectorAll('svg, strong, div')).every(e => { const r = e.getBoundingClientRect(); return r.bottom <= b.bottom + 1 && r.right <= b.right + 1; }); })`, 'Recovery text and icons stay inside the mobile alert');
  browser('screenshot', join(screenshots, 'unavailable-mobile.png'), '--full');
  evaluate('window.__restorePreviewForm(); true');
  browser('find', 'role', 'button', 'click', '--name', 'Reintentar');
  await verify(`document.querySelector('.gnf-wpforms-shell .wpforms-field input') !== null`, 'Retry mounts the restored questions');
  await verify(`!document.body.innerText.includes('Preguntas no disponibles')`, 'Recovery alert disappears after a valid form loads');
  await verify(`Array.from(document.querySelectorAll('button')).some(b => b.innerText.includes('Guardar ahora') && !b.disabled)`, 'Restored form enables saving');
  await verify(`document.querySelectorAll('.gnf-wpforms-shell .gnf-required-evidence__badge').length === 1`, 'Retry preserves required evidence marking without duplication');
  browser('screenshot', join(screenshots, 'restored-mobile.png'), '--full');
  assert.equal(browser('errors'), '', 'No uncaught browser errors'); checks++;
  console.log(`${checks} browser checks passed`);
} finally { browser('close'); }
