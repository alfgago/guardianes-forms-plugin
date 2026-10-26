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
function clickEvidence(name) {
  evaluate(`document.querySelectorAll('[data-review-test-action]').forEach(b => b.removeAttribute('data-review-test-action')); Array.from(document.querySelectorAll('#review-evidence-101-1 button')).find(b => b.innerText.trim() === ${JSON.stringify(name)}).setAttribute('data-review-test-action', 'true')`);
  tap('[data-review-test-action]');
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
  tap('[data-status=aprobada]');
  await verify(`document.querySelectorAll('.gnf-review-evidence').length === 1`, 'Explicit approved filter remains available');
  tap('[data-status=pendiente]');
  await verify(`document.querySelectorAll('.gnf-review-evidence').length === 4 && document.body.innerText.includes('Archivo informativo') && document.body.innerText.includes('Participantes en la actividad') && document.body.innerText.includes('Actividad realizada con estudiantes')`, 'Pending shortcut restores every active evidence and the activity context');
  await verify(`document.querySelector('[data-status=all]').getAttribute('aria-pressed') === 'true' && document.querySelector('[data-status=pendiente]').getAttribute('aria-pressed') !== 'true'`, 'All is visibly selected instead of implying a pending-only filter');
  tap('#review-evidence-101-4 .gnf-review-zoom');
  await verify(`!!document.querySelector('[role=dialog]')`, 'Rejected evidence opens its preview');
  await verify(`document.querySelector('[role=dialog]').parentElement.parentElement === document.body`, 'Preview escapes the rejected evidence stacking context');
  await verify(`(() => { let node = document.querySelector('[role=dialog]'); while (node) { if (Number(getComputedStyle(node).opacity) < 1) return false; node = node.parentElement; } return true; })()`, 'Rejected evidence does not make the popup translucent');
  await verify(`(() => { const overlay = document.querySelector('[role=dialog]').parentElement; const r = document.querySelector('.gnf-review-toolbar').getBoundingClientRect(); const x = Math.min(innerWidth - 1, r.x + r.width / 2); const y = Math.max(1, r.y + Math.min(r.height / 2, 80)); return overlay.contains(document.elementFromPoint(x, y)); })()`, 'Modal backdrop covers the sticky challenge toolbar');
  browser('press', 'Shift+Tab');
  await verify(`document.activeElement.textContent === 'Abrir archivo original'`, 'Focus wraps inside the portaled dialog');
  browser('press', 'Tab');
  await verify(`document.activeElement.getAttribute('aria-label') === 'Cerrar'`, 'Tab returns focus to the close button');
  evaluate(`document.querySelector('.gnf-shell').style.transform = 'translateZ(0)'; document.querySelector('.gnf-shell').style.opacity = '.85'`);
  await verify(`(() => { const r = document.querySelector('[role=dialog]').parentElement.getBoundingClientRect(); return r.top === 0 && r.left === 0 && r.width === innerWidth && r.height === innerHeight; })()`, 'Panel transforms cannot move or clip the viewport overlay');
  browser('screenshot', join(screenshots, 'rejected-preview-desktop.png'));
  browser('press', 'Escape');
  await verify(`!document.querySelector('[role=dialog]') && document.activeElement.closest('#review-evidence-101-4') !== null && document.body.style.overflow !== 'hidden'`, 'Closing rejected preview restores focus and page scrolling');
  evaluate(`document.querySelector('.gnf-shell').style.transform = ''; document.querySelector('.gnf-shell').style.opacity = ''`);
  tap('#review-evidence-101-1 .gnf-review-zoom');
  await verify(`!!document.querySelector('[role=dialog] img') && document.querySelector('[role=dialog] img').naturalWidth > 0`, 'Photo opens in a rendered enlarged preview');
  browser('press', 'Escape');
  await verify(`!document.querySelector('[role=dialog]') && document.activeElement.classList.contains('gnf-review-zoom')`, 'Escape closes preview and restores focus');
  clickEvidence('Aprobar');
  browser('find', 'placeholder', 'Comentario opcional para el centro educativo...', 'fill', 'Comentario que debe conservarse.');
  await verify(`document.querySelector('.gnf-review-selector select').disabled && [...document.querySelectorAll('[role=tab],.gnf-review-filters button')].every(b => b.disabled)`, 'An open review protects the comment when navigating');
  evaluate('window.failNextReview = true');
  click('Guardar aprobación');
  await verify(`document.body.innerText.includes('Error de prueba recuperable') && document.querySelector('textarea').value === 'Comentario que debe conservarse.'`, 'Server error preserves the comment and allows retry');
  click('Guardar aprobación');
  await verify(`document.querySelector('[data-status=pendiente] strong').textContent === '0' && document.querySelector('[data-status=aprobada] strong').textContent === '2'`, 'Successful review updates both counts');
  await verify(`document.querySelectorAll('.gnf-review-evidence').length === 4 && !!document.getElementById('review-evidence-101-1') && document.body.innerText.includes('Comentario que debe conservarse.')`, 'Review preserves all evidence and the saved comment');
  await verify(`window.reviewRequests.at(-1).index === 1 && !('puntos' in window.reviewRequests.at(-1))`, 'Duplicate filename sends its original evidence index and no invented points');
  clickEvidence('Editar comentario');
  await verify(`document.querySelector('textarea').value === 'Comentario que debe conservarse.'`, 'Saved comment opens prefilled');
  click('Cancelar');
  clickEvidence('En pausa');
  browser('select', '.gnf-review-evidence select', 'reto_inconcluso');
  click('Guardar pausa');
  await verify(`document.querySelector('[data-status=en_pausa] strong').textContent === '2'`, 'Approval can be reversed to paused and counts update');
  tap('[data-status=rechazada]');
  tap('[data-status=pendiente]');
  await verify(`document.querySelectorAll('.gnf-review-evidence').length === 4 && document.querySelector('[data-status=all]').getAttribute('aria-pressed') === 'true'`, 'Pending shortcut shows all evidence even with zero pending files');
  evaluate('window.beforeNavigationRequests = window.detailRequests');
  click('Siguiente pendiente (2)');
  await verify(`document.querySelector('[role=tab][aria-selected=true]').id === 'review-tab-2' && document.activeElement.id === 'review-evidence-102-1'`, 'Next pending crosses retos and focuses the correct file');
  await verify(`document.querySelector('[data-status=all]').getAttribute('aria-pressed') === 'true'`, 'Next pending keeps the complete challenge visible');
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
  tap('[data-status=pendiente]');
  await verify(`document.querySelectorAll('.gnf-review-evidence').length === 4 && document.querySelector('[data-status=all]').getAttribute('aria-pressed') === 'true'`, 'Mobile pending shortcut also restores the full challenge');
  tap('#review-evidence-101-4 .gnf-review-zoom');
  await verify(`(() => { const dialog = document.querySelector('[role=dialog]'); const r = dialog.getBoundingClientRect(); return dialog.parentElement.parentElement === document.body && r.left >= 0 && r.right <= innerWidth && dialog.parentElement.contains(document.elementFromPoint(innerWidth / 2, 20)); })()`, 'Mobile preview stays above the toolbar and inside the viewport');
  browser('screenshot', join(screenshots, 'rejected-preview-mobile.png'));
  click('Cerrar');
  await verify(`!document.querySelector('[role=dialog]') && document.body.style.overflow !== 'hidden'`, 'Mobile close button restores scrolling');
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
