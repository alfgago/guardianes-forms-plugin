import { execFileSync } from 'node:child_process';
import { existsSync, openSync, closeSync, readFileSync, unlinkSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { tmpdir } from 'node:os';
import assert from 'node:assert/strict';
const origin = process.env.GNF_PREVIEW_ORIGIN || 'http://127.0.0.1:5186';
const screenshots = join(tmpdir(), 'gnf-challenge-review'); mkdirSync(screenshots, { recursive: true });
let binary = process.env.AGENT_BROWSER_BIN || 'agent-browser';
if (process.platform === 'win32' && !process.env.AGENT_BROWSER_BIN) {
  binary = execFileSync('where.exe', ['agent-browser'], { encoding: 'utf8' }).trim().split(/\r?\n/)
    .map(path => join(dirname(path), 'node_modules/agent-browser/bin/agent-browser-win32-x64.exe')).find(existsSync);
}
assert.ok(binary);
let checks = 0;
function browser(...args) {
  const log = join(tmpdir(), `gnf-challenges-${process.pid}.log`), fd = openSync(log, 'w');
  try { execFileSync(binary, ['--session', 'gnf-challenges-test', ...args], { stdio: ['ignore', fd, fd], timeout: 30000, windowsHide: true }); return readFileSync(log, 'utf8').trim(); }
  finally { closeSync(fd); unlinkSync(log); }
}
function evaluate(expression) { return JSON.parse(browser('eval', expression)); }
function click(name) {
  evaluate(`Array.from(document.querySelectorAll('button')).find(b => b.innerText.trim() === ${JSON.stringify(name)})?.scrollIntoView({block:'center'})`);
  browser('find', 'role', 'button', 'click', '--name', name, '--exact');
}
function tap(selector) {
  evaluate(`document.querySelector(${JSON.stringify(selector)})?.scrollIntoView({block:'center'})`);
  browser('click', selector);
}
async function verify(expression, message) {
  for (let i = 0; i < 30; i++) { if (evaluate(expression)) { checks++; console.log('ok: ' + message); return; } await new Promise(r => setTimeout(r, 100)); }
  console.log(evaluate(`({requests:window.reviewRequests,fail:window.failNextReview,comment:document.querySelector('textarea')?.value,text:document.body.innerText.slice(-2000)})`));
  browser('screenshot', join(screenshots, 'failure.png'));
  throw new Error(message);
}
try {
  browser('set', 'viewport', '1440', '1000');
  browser('open', `${origin}/preview-challenge-review.html`);
  await verify(`document.querySelectorAll('[role=tab]').length === 7`, 'All seven retos have horizontal tabs');
  await verify(`document.querySelectorAll('[role=tabpanel]').length === 1`, 'Only the selected reto is mounted');
  await verify(`document.querySelector('[data-status=pendiente] strong').textContent === '1'`, 'Replaced and informational files do not inflate pending counts');
  await verify(`!!document.querySelector('.gnf-review-requirement')`, 'Official required activity is highlighted');
  browser('screenshot', join(screenshots, 'desktop-overview.png'), '--full');
  click('Contraer detalle');
  await verify(`document.querySelector('.gnf-award-summary button').getAttribute('aria-expanded') === 'false'`, 'Award details can be collapsed');
  tap('[data-status=pendiente]');
  await verify(`document.querySelectorAll('.gnf-review-evidence').length === 1 && document.body.innerText.includes('Actividad realizada con estudiantes')`, 'Filtering preserves the activity question and answer context');
  tap('.gnf-review-zoom');
  await verify(`!!document.querySelector('[role=dialog] img') && document.querySelector('[role=dialog] img').naturalWidth > 0`, 'Photo opens in a rendered enlarged preview');
  browser('press', 'Escape');
  await verify(`!document.querySelector('[role=dialog]') && document.activeElement.classList.contains('gnf-review-zoom')`, 'Escape closes preview and restores focus');
  click('Aprobar');
  browser('find', 'placeholder', 'Comentario opcional para el centro educativo...', 'fill', 'Comentario que debe conservarse.');
  await verify(`document.querySelector('.gnf-review-selector select').disabled && [...document.querySelectorAll('[role=tab],.gnf-review-filters button')].every(b => b.disabled)`, 'An open review protects the comment when navigating');
  evaluate('window.failNextReview = true');
  click('Guardar aprobación');
  await verify(`document.body.innerText.includes('Error de prueba recuperable') && document.querySelector('textarea').value === 'Comentario que debe conservarse.'`, 'Server error preserves the comment and allows retry');
  click('Guardar aprobación');
  await verify(`document.querySelector('[data-status=pendiente] strong').textContent === '0' && document.querySelector('[data-status=aprobada] strong').textContent === '2'`, 'Successful review updates both counts');
  await verify(`!!document.getElementById('review-evidence-101-1') && document.body.innerText.includes('Comentario que debe conservarse.')`, 'Just-reviewed evidence stays in place under the previous filter');
  await verify(`window.reviewRequests.at(-1).index === 1 && !('puntos' in window.reviewRequests.at(-1))`, 'Filtered duplicate filename sends its original evidence index and no invented points');
  click('Editar comentario');
  await verify(`document.querySelector('textarea').value === 'Comentario que debe conservarse.'`, 'Saved comment opens prefilled');
  click('Cancelar');
  click('En pausa');
  browser('select', '.gnf-review-evidence select', 'reto_inconcluso');
  click('Guardar pausa');
  await verify(`document.querySelector('[data-status=en_pausa] strong').textContent === '2'`, 'Approval can be reversed to paused and counts update');
  tap('[data-status=all]');
  tap('[data-status=pendiente]');
  await verify(`document.querySelectorAll('.gnf-review-evidence').length === 0 && document.body.innerText.includes('No hay evidencias con este estado')`, 'Changing filters clears retained rows and shows an explicit empty state');
  evaluate('window.beforeNavigationRequests = window.detailRequests');
  click('Siguiente pendiente (2)');
  await verify(`document.querySelector('[role=tab][aria-selected=true]').id === 'review-tab-2' && document.activeElement.id === 'review-evidence-102-1'`, 'Next pending crosses retos and focuses the correct file');
  await verify(`document.getElementById('review-evidence-102-1').getBoundingClientRect().top >= document.querySelector('.gnf-review-toolbar').getBoundingClientRect().bottom - 1`, 'Sticky navigation does not obscure the focused evidence');
  click('Siguiente pendiente (2)');
  await verify(`document.activeElement.id === 'review-evidence-102-2'`, 'Next pending includes evidence from a retired question');
  await verify(`window.detailRequests === window.beforeNavigationRequests`, 'Switching retos and filters does not fetch the center again');
  browser('focus', '#review-tab-2');
  browser('press', 'ArrowRight');
  await verify(`document.querySelector('[aria-selected=true]').id === 'review-tab-3'`, 'Arrow keys select the next reto');
  browser('press', 'End');
  await verify(`document.querySelector('[aria-selected=true]').id === 'review-tab-7'`, 'End selects the last reto');
  browser('select', '.gnf-review-selector select', '2');
  click('Volver al listado');
  click('Volver al centro');
  await verify(`document.querySelector('[aria-selected=true]').id === 'review-tab-2'`, 'Returning to the center restores the selected reto');
  browser('set', 'viewport', '390', '844');
  await verify(`document.documentElement.scrollWidth <= innerWidth && getComputedStyle(document.querySelector('.gnf-review-tabrow')).display === 'none'`, 'Mobile uses the selector without horizontal page overflow');
  browser('select', '.gnf-review-selector select', '1');
  tap('[data-status=en_pausa]');
  await verify(`document.querySelectorAll('.gnf-review-evidence').length === 2`, 'Mobile status filtering includes both paused evidences');
  evaluate(`document.querySelector('.gnf-review-toolbar').scrollIntoView({block:'start'})`);
  browser('screenshot', join(screenshots, 'mobile-review.png'), '--full');
  await verify(`Array.from(document.querySelectorAll('.gnf-review-toolbar button,.gnf-review-selector select')).filter(e=>e.getClientRects().length).every(e=>e.getBoundingClientRect().right <= innerWidth)`, 'Mobile controls fit within the viewport');
  browser('set', 'viewport', '320', '740');
  click('Siguiente pendiente (2)');
  await verify(`document.documentElement.scrollWidth <= innerWidth && document.activeElement.id === 'review-evidence-102-1'`, 'Small mobile viewport supports next-pending navigation without overflow');
  await verify(`document.activeElement.getBoundingClientRect().top >= document.querySelector('.gnf-review-toolbar').getBoundingClientRect().bottom - 1`, 'Mobile sticky controls leave the focused evidence visible');
  browser('screenshot', join(screenshots, 'mobile-small.png'));
  browser('set', 'viewport', '1440', '1000');
  evaluate(`document.querySelector('.gnf-review-toolbar').scrollIntoView({block:'start'})`);
  browser('screenshot', join(screenshots, 'desktop-review.png'));
  assert.equal(browser('errors'), '', 'No uncaught browser errors'); checks++;
  console.log(`${checks} challenge review browser checks passed; screenshots: ${screenshots}`);
} finally { browser('close'); }
