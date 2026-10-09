import { execFileSync } from 'node:child_process';
import { existsSync, openSync, closeSync, readFileSync, unlinkSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { tmpdir } from 'node:os';
import assert from 'node:assert/strict';

const origin = process.env.GNF_PREVIEW_ORIGIN || 'http://127.0.0.1:5186';
const screenshotDir = join(tmpdir(), 'gnf-impact-browser');
mkdirSync(screenshotDir, { recursive: true });
let binary = process.env.AGENT_BROWSER_BIN || 'agent-browser';
if (process.platform === 'win32' && !process.env.AGENT_BROWSER_BIN) {
  const commands = execFileSync('where.exe', ['agent-browser'], { encoding: 'utf8' }).trim().split(/\r?\n/);
  binary = commands.map(command => join(dirname(command), 'node_modules/agent-browser/bin/agent-browser-win32-x64.exe')).find(existsSync);
  assert.ok(binary, 'agent-browser must be installed');
}
let checks = 0;
function browser(...args) {
  const output = join(tmpdir(), `gnf-impact-browser-${process.pid}.log`);
  const fd = openSync(output, 'w');
  try {
    execFileSync(binary, ['--session', 'gnf-impact-regression', ...args], { stdio: ['ignore', fd, fd], timeout: 30000, windowsHide: true });
    return readFileSync(output, 'utf8').trim();
  } finally { closeSync(fd); unlinkSync(output); }
}
function evaluate(expression) { return JSON.parse(browser('eval', expression)); }
async function verify(expression, message) {
  for (let attempt = 0; attempt < 50; attempt++) {
    if (evaluate(expression)) { checks++; console.log('ok: ' + message); return; }
    await new Promise(resolve => setTimeout(resolve, 200));
  }
  throw new Error(message);
}
const body = 'document.body.innerText';
const metrics = "document.querySelectorAll('.gnf-impact-table tbody tr:not(.gnf-impact-source-row)').length";
const total = "document.querySelector('.gnf-impact-table tbody tr:not(.gnf-impact-source-row) td.gnf-impact-total span')?.textContent";
try {
  browser('set', 'viewport', '1366', '900');
  browser('open', `${origin}/preview-impact.html`);
  await verify(`${metrics} === 41`, 'All 41 indicators render together');
  await verify(`${body}.includes('Inscripción') && ${body}.includes('Reto Agua')`, 'Indicators are grouped by their real source');
  await verify(`document.querySelector('.gnf-impact-timestamp time') !== null`, 'The reporting cutoff is visible');
  await verify(`document.querySelector('[data-metric-key="agua_captacion"] td.gnf-impact-total span').textContent === '0' && document.querySelector('[data-metric-key="agua_riego"] td:last-child span').textContent === 'Sin datos'`, 'Real zero and missing regional data remain distinct');
  browser('eval', `window.__firstImpactTotal = ${total}`);
  browser('select', 'select[id$="-region"]', '2');
  await verify(`window.impactRequests.at(-1).params.region === '2' && ${total} !== window.__firstImpactTotal`, 'Regional filter changes totals across the matrix');
  await verify(`Array.from(document.querySelectorAll('.gnf-impact-table thead th')).some(e => e.innerText === 'Heredia') && !Array.from(document.querySelectorAll('.gnf-impact-table thead th')).some(e => e.innerText === 'Alajuela')`, 'The selected region applies to every comparison column');
  browser('select', 'select[id$="-circuit"]', '2:01');
  await verify(`window.impactRequests.at(-1).params.circuit === '01' && document.querySelector('.gnf-impact-stats dd').innerText === '1'`, 'Circuit filter applies to summary and indicators');
  browser('click', 'details.gnf-impact-source-filter summary');
  browser('check', 'details.gnf-impact-source-filter input[value="reto-agua"]');
  await verify(`window.impactRequests.at(-1).params.sources === 'reto-agua' && ${metrics} === 4`, 'Source selection shows all four water indicators, not one at a time');
  browser('select', 'select[id$="-mode"]', 'active');
  await verify(`window.impactRequests.at(-1).params.mode === 'active'`, 'Reported and validated results use separate requests');
  await verify(`Array.from(document.querySelectorAll('.gnf-impact-download[href]')).length === 5 && Array.from(document.querySelectorAll('.gnf-impact-download[href]')).every(e => {const p = new URL(e.href).searchParams; return p.get('region') === '2' && p.get('circuit') === '01' && p.get('sources') === 'reto-agua' && p.get('_wpnonce')})`, 'All five download URLs retain territory, source and signed permission');
  browser('check', 'details.gnf-impact-source-filter input[value="reto-siembra-de-arboles"]');
  await verify(`window.impactRequests.at(-1).params.sources === 'reto-agua,reto-siembra-de-arboles' && ${metrics} === 8`, 'Multiple sources can be added from the full unfiltered source catalog');

  browser('open', `${origin}/preview-impact.html`);
  await verify(`${metrics} === 41`, 'Reset restores all indicators');
  browser('find', 'role', 'button', 'click', '--name', 'Circuitos educativos');
  await verify(`document.querySelector('.gnf-impact-pagination')?.innerText.includes('1-25 de 58') && document.querySelectorAll('.gnf-impact-table thead th').length === 28 && ${metrics} === 41`, 'Circuit column pagination keeps every indicator visible');
  browser('eval', `window.__pageTotal = ${total}; window.__pageExport = document.querySelector('.gnf-impact-download[href]').href`);
  browser('scrollintoview', '.gnf-impact-pagination');
  browser('find', 'role', 'button', 'click', '--name', 'Circuitos siguientes');
  await verify(`document.querySelector('.gnf-impact-pagination').innerText.includes('26-50 de 58') && ${total} === window.__pageTotal && document.querySelector('.gnf-impact-download[href]').href === window.__pageExport`, 'Paging comparison columns never changes totals or full exports');
  browser('scrollintoview', '.gnf-impact-table-scroll');
  browser('screenshot', join(screenshotDir, 'impact-desktop.png'));
  browser('set', 'viewport', '390', '844');
  await verify('document.documentElement.scrollWidth <= innerWidth', 'Mobile impact panel does not overflow the page');
  await verify(`(() => { const cell = document.querySelector('.gnf-impact-table tbody tr:not(.gnf-impact-source-row) td.gnf-impact-total'); const box = document.querySelector('.gnf-impact-table-scroll'); return cell.getBoundingClientRect().right <= box.getBoundingClientRect().right + 1; })()`, 'Mobile selected total is visible without horizontal scrolling');
  browser('screenshot', join(screenshotDir, 'impact-mobile.png'));
  browser('set', 'viewport', '320', '844');
  await verify('document.documentElement.scrollWidth <= innerWidth', 'Impact filters and downloads fit a narrow phone');
  await verify(`(() => { const wrap = document.querySelector('.gnf-impact-table-scroll'); wrap.scrollTop = 400; wrap.scrollLeft = 500; const first = document.querySelector('thead th:first-child').getBoundingClientRect(); const last = document.querySelector('thead .gnf-impact-total').getBoundingClientRect(); const box = wrap.getBoundingClientRect(); return first.width === 140 && Math.abs(first.top - box.top) <= 2 && last.right <= box.right; })()`, 'Narrow table keeps headers, indicator and total fixed during scrolling');

  browser('open', `${origin}/preview-impact.html?year=2026&region=2&circuit=01&mode=active&sources=reto-agua`);
  await verify(`window.impactRequests.at(-1).params.region === '2' && window.impactRequests.at(-1).params.circuit === '01' && window.impactRequests.at(-1).params.mode === 'active' && ${metrics} === 4`, 'Quicklink initializes local territorial, source and mode filters');
  browser('open', `${origin}/preview-impact.html?year=2025`);
  await verify(`${metrics} === 0 && ${body}.includes('No hay indicadores para los filtros seleccionados')`, 'Years outside the implemented catalog do not invent indicators');
  browser('open', `${origin}/preview-impact.html?year=2101`);
  await verify(`${metrics} === 41 && window.impactRequests.at(-1).params.year === '2026' && document.querySelector('input[type="number"]').max === '2100'`, 'Invalid quicklink year falls back to active year and input is bounded at 2100');

  browser('set', 'viewport', '1366', '900');
  browser('open', `${origin}/preview-impact.html?role=dre`);
  await verify(`${metrics} === 41 && document.querySelector('select[id$="-region"]').value === '2'`, 'DRE sees the same complete panel with its assigned region');
  await verify(`!Array.from(document.querySelectorAll('button')).some(e => e.textContent === 'Actualizar') && !document.querySelector('select[id$="-region"]').innerText.includes('Alajuela')`, 'DRE has no administrative refresh or other regional option');
  browser('open', `${origin}/preview-impact.html?state=cold`);
  await verify(`${body}.includes('Preparando indicadores') && document.querySelector('.gnf-impact-stats dd').innerText === '-'`, 'First build shows preparation, not fictitious zero totals');
  await verify(`${metrics} === 41`, 'Preparation polls until the snapshot is available');
  const readyRequests = evaluate('window.impactRequests.length');
  await new Promise(resolve => setTimeout(resolve, 5500));
  await verify(`window.impactRequests.length === ${readyRequests}`, 'Polling stops once the snapshot is ready and not refreshing');
  browser('open', `${origin}/preview-impact.html?state=refreshing`);
  await verify(`${metrics} === 41`, 'Existing cut remains available during automatic server refresh');
  const autoRefreshRequests = evaluate('window.impactRequests.length');
  await new Promise(resolve => setTimeout(resolve, 5500));
  await verify(`window.impactRequests.length === ${autoRefreshRequests} && document.querySelectorAll('.gnf-impact-download[href]').length === 5`, 'Automatic server refresh does not poll or disable cached downloads');
  browser('open', `${origin}/preview-impact.html`);
  await verify(`${metrics} === 41`, 'Ready report available for explicit refresh');
  browser('find', 'role', 'button', 'click', '--name', 'Actualizar', '--exact');
  await verify(`${body}.includes('Actualizando en segundo plano') && document.querySelectorAll('.gnf-impact-download[href]').length === 5`, 'Manual update keeps all five downloads active while polling the new cut');
  await verify(`!${body}.includes('Actualizando en segundo plano')`, 'Manual polling stops after the refreshed cut is published');
  const manualRefreshRequests = evaluate('window.impactRequests.length');
  await new Promise(resolve => setTimeout(resolve, 5500));
  await verify(`window.impactRequests.length === ${manualRefreshRequests}`, 'Completed manual update returns to two-hour cached checks');
  browser('open', `${origin}/preview-impact.html?state=error`);
  await verify(`${body}.includes('Reintentar')`, 'Network failure offers a retry');
  browser('eval', "window.impactMock.state = 'ready'");
  browser('find', 'role', 'button', 'click', '--name', 'Reintentar');
  await verify(`${metrics} === 41`, 'Retry recovers the full panel');
  browser('open', `${origin}/preview-impact.html?state=stale`);
  await verify(`${metrics} === 41 && ${body}.includes('Datos pendientes de actualizar')`, 'A stale snapshot remains readable during refresh');

  browser('open', `${origin}/preview-docente.html`);
  await verify(`${body}.includes('Eco puntos acumulados') && !${body}.includes('Descargar Reporte')`, 'Ordinary teacher sees no report button');
  browser('open', `${origin}/preview-docente.html?report-preview=1`);
  await verify(`Array.from(document.querySelectorAll('button')).some(e => e.textContent.includes('Descargar Reporte') && !e.disabled) && ${body}.includes('Reporte provisional')`, 'Administrative teacher preview has an enabled provisional download');
  browser('set', 'viewport', '390', '844');
  await verify('document.documentElement.scrollWidth <= innerWidth', 'Teacher provisional report controls fit mobile');
  browser('screenshot', join(screenshotDir, 'report-preview-mobile.png'));
  assert.equal(browser('errors'), '', 'No uncaught browser errors'); checks++;
  console.log(`\n${checks} browser checks passed`);
  console.log(`Screenshots: ${screenshotDir}`);
} finally { browser('close'); }
