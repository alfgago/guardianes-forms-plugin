import { execFileSync } from 'node:child_process';
import { existsSync, openSync, closeSync, readFileSync, unlinkSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert/strict';

const root = dirname(dirname(fileURLToPath(import.meta.url)));
const origin = process.env.GNF_PREVIEW_ORIGIN || 'http://127.0.0.1:5186';
let binary = process.env.AGENT_BROWSER_BIN || 'agent-browser';
if (process.platform === 'win32' && !process.env.AGENT_BROWSER_BIN) {
  const commands = execFileSync('where.exe', ['agent-browser'], { encoding: 'utf8' }).trim().split(/\r?\n/);
  binary = commands.map(command => join(dirname(command), 'node_modules/agent-browser/bin/agent-browser-win32-x64.exe')).find(existsSync);
  assert.ok(binary, 'agent-browser must be installed');
}
const session = 'gnf-notifications-auth-regression';
let checks = 0;
function browser(...args) {
  // A newly launched Windows daemon can inherit pipes and keep execFileSync waiting.
  const output = join(tmpdir(), `gnf-browser-test-${process.pid}.log`);
  const fd = openSync(output, 'w');
  try {
    execFileSync(binary, ['--session', session, ...args], { stdio: ['ignore', fd, fd], timeout: 30000, windowsHide: true });
    return readFileSync(output, 'utf8').trim();
  } finally {
    closeSync(fd);
    unlinkSync(output);
  }
}
function evaluate(expression) {
  return JSON.parse(browser('eval', expression));
}
async function verify(expression, message) {
  for (let attempt = 0; attempt < 20; attempt++) {
    if (evaluate(expression)) { checks++; console.log('ok: ' + message); return; }
    await new Promise(resolve => setTimeout(resolve, 200));
  }
  throw new Error(message);
}
const text = 'document.body.innerText';
try {
  browser('set', 'viewport', '1366', '900');
  browser('open', `${origin}/preview-docente.html?paused=1&p=notificaciones`);
  await verify(`${text}.includes('2 sin leer y 1 leída.')`, 'Unread and read counts are separate');
  await verify(`${text}.includes('Evidencia en pausa') && ${text}.includes('Reto inconcluso')`, 'Paused evidence exposes its cause');
  await verify(`!${text}.includes('Pendiente')`, 'Notification read states no longer say pending');
  browser('screenshot', join(root, 'tests/notifications-desktop.png'), '--full');
  browser('set', 'viewport', '390', '844');
  await verify('document.documentElement.scrollWidth <= innerWidth', 'Mobile notifications do not overflow horizontally');
  await verify(`Array.from(document.querySelectorAll('main p')).filter(p => p.innerText.includes('evidencia')).every(p => p.getBoundingClientRect().width > 220)`, 'Mobile notification messages are not squeezed by action buttons');
  browser('screenshot', join(root, 'tests/notifications-mobile.png'), '--full');
  browser('find', 'role', 'button', 'click', '--name', 'Marcar todas como leídas');
  await verify(`${text}.includes('0 sin leer y 3 leídas.')`, 'Marking read updates both groups without resolving the evidence');

  browser('set', 'viewport', '1366', '900');
  browser('open', `${origin}/preview-auth.html?reset=1&login=school%2Bpilot%40example.org&key=valid`);
  await verify(`${text}.includes('school+pilot@example.org')`, 'Email usernames survive URL decoding');
  browser('fill', '#nueva-contraseña', 'new-password-123');
  browser('fill', '#confirmar-contraseña', 'new-password-123');
  browser('click', 'button[type=submit]');
  await verify(`${text}.includes('Contraseña actualizada')`, 'Valid recovery displays success');
  await verify(`document.querySelector('button[type=submit]') === null && document.querySelector('input') === null`, 'A consumed key cannot be submitted again');
  await verify(`!new URL(location.href).searchParams.has('key')`, 'Successful recovery removes credentials from the address bar');
  await verify(`window.__authRequests.filter(r => r.path.endsWith('/auth/reset-password')).length === 1`, 'Recovery sends exactly one request');
  browser('find', 'role', 'button', 'click', '--name', 'Iniciar sesión');
  await verify(`${text}.includes('Olvidé mi contraseña')`, 'Success can return to login');

  browser('open', `${origin}/preview-auth.html?reset=1&login=school%2Bpilot%40example.org&key=expired`);
  await verify(`${text}.includes('Crear nueva contraseña')`, 'Recovery form renders for an expired test link');
  browser('fill', '#nueva-contraseña', 'new-password-123');
  browser('fill', '#confirmar-contraseña', 'new-password-123');
  browser('click', 'button[type=submit]');
  await verify(`${text}.includes('Solicitar un nuevo enlace')`, 'Expired recovery offers a new link');
  await verify(`document.querySelector('button[type=submit]') === null && document.querySelector('input') === null`, 'An invalid link cannot be repeatedly submitted');
  browser('set', 'viewport', '320', '844');
  await verify('document.documentElement.scrollWidth <= innerWidth', 'Recovery fits a small mobile viewport');
  browser('screenshot', join(root, 'tests/password-reset-mobile.png'), '--full');
  browser('find', 'role', 'button', 'click', '--name', 'Solicitar un nuevo enlace');
  await verify(`document.querySelector('input').value === 'school+pilot@example.org'`, 'Retry prefills the existing username');
  await verify(`location.search === ''`, 'Retry discards the invalid credentials');
  browser('set', 'viewport', '1366', '900');
  browser('click', 'button[type=submit]');
  await verify(`${text}.includes('Correo enviado')`, 'Retry can request a new email');
  browser('open', `${origin}/preview-auth.html?reset=1&login=school&key=valid`);
  await verify(`${text}.includes('Crear nueva contraseña')`, 'A fresh reset starts normally');
  browser('find', 'role', 'button', 'click', '--name', 'Volver');
  await verify(`${text}.includes('Olvidé mi contraseña') && location.search === ''`, 'Back leaves recovery and removes the token');
  assert.equal(browser('errors'), '', 'No uncaught browser errors');
  checks++;
  console.log(`\n${checks} browser checks passed`);
} finally {
  browser('close');
}
