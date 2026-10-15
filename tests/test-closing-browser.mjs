import { execFileSync } from 'node:child_process';
import { existsSync, openSync, closeSync, readFileSync, unlinkSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { tmpdir } from 'node:os';
import assert from 'node:assert/strict';
const origin = process.env.GNF_PREVIEW_ORIGIN || 'http://127.0.0.1:5186';
const screenshots = join(tmpdir(), 'gnf-closing-browser'); mkdirSync(screenshots, { recursive: true });
let binary = process.env.AGENT_BROWSER_BIN || 'agent-browser';
if (process.platform === 'win32' && !process.env.AGENT_BROWSER_BIN) {
  binary = execFileSync('where.exe', ['agent-browser'], { encoding: 'utf8' }).trim().split(/\r?\n/)
    .map(path => join(dirname(path), 'node_modules/agent-browser/bin/agent-browser-win32-x64.exe')).find(existsSync);
}
assert.ok(binary);
let checks = 0;
function browser(...args) {
  const log = join(tmpdir(), `gnf-closing-${process.pid}.log`), fd = openSync(log, 'w');
  try { execFileSync(binary, ['--session', 'gnf-closing-test', ...args], { stdio: ['ignore', fd, fd], timeout: 30000, windowsHide: true }); return readFileSync(log, 'utf8').trim(); }
  finally { closeSync(fd); unlinkSync(log); }
}
function evaluate(expression) { return JSON.parse(browser('eval', expression)); }
async function verify(expression, message) {
  for (let i = 0; i < 40; i++) { if (evaluate(expression)) { checks++; console.log('ok: ' + message); return; } await new Promise(r => setTimeout(r, 100)); }
  throw new Error(message);
}
try {
  browser('set', 'viewport', '1366', '900');
  browser('open', `${origin}/preview-evidence-review.html?unscored=1`);
  await verify(`Array.from(document.querySelectorAll('button')).some(b => b.innerText === 'Aprobar')`, 'An unscored activity presents review actions');
  await verify(`getComputedStyle(document.querySelector('.gnf-award-summary')).backgroundColor === 'rgb(233, 245, 239)'`, 'Award information uses a distinct green band');
  browser('screenshot', join(screenshots, 'review-desktop.png'), '--full');
  browser('find', 'role', 'button', 'click', '--name', 'Aprobar', '--exact');
  browser('find', 'placeholder', 'Comentario opcional para el centro educativo...', 'fill', 'Actividad revisada sin modificar puntos.');
  browser('find', 'role', 'button', 'click', '--name', 'Guardar aprobación', '--exact');
  await verify(`window.reviewRequests.length === 1 && window.reviewRequests[0].action === 'aprobar' && !('puntos' in window.reviewRequests[0])`, 'Approval sends one request without inventing a score');
  await verify(`Array.from(document.querySelectorAll('button')).some(b => b.innerText === 'Editar comentario')`, 'Approved unscored activity retains comment editing');
  browser('find', 'role', 'button', 'click', '--name', 'En pausa', '--exact');
  browser('select', 'select', 'reto_inconcluso');
  browser('find', 'role', 'button', 'click', '--name', 'Guardar pausa', '--exact');
  await verify(`window.reviewRequests.at(-1).action === 'pausar' && document.body.innerText.includes('En pausa')`, 'An unscored approval can be changed to paused');
  browser('set', 'viewport', '390', '844');
  await verify(`document.documentElement.scrollWidth <= innerWidth`, 'Review and award band fit a narrow mobile screen');
  await verify(`Array.from(document.querySelectorAll('.gnf-award-summary strong')).every(e => e.getBoundingClientRect().right <= innerWidth)`, 'Award values do not overlap outside the mobile viewport');
  browser('screenshot', join(screenshots, 'review-mobile.png'), '--full');
  assert.equal(browser('errors'), '', 'No uncaught browser errors'); checks++;
  console.log(`${checks} closing browser checks passed; screenshots: ${screenshots}`);
} finally { browser('close'); }
