import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const root = new URL('../', import.meta.url);
const require = createRequire(new URL('app/package.json', root));
const ts = require('typescript');
function load(path, imports) {
  const code = ts.transpileModule(fs.readFileSync(new URL(path, root), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX },
  }).outputText;
  const exports = {};
  vm.runInNewContext(code, { exports, require: name => {
    assert.ok(name in imports, `Unexpected import ${name}`);
    return imports[name];
  }, window: { addEventListener() {}, removeEventListener() {} } });
  return exports;
}

const { useNotificationStore: store } = load('app/src/stores/useNotificationStore.ts', { zustand: require('zustand') });
assert.equal(typeof store.getState().setSummary, 'function', 'Badge has a summary setter independent of loaded details');
store.getState().setSummary({ unreadCount: 7, total: 10, version: 'a' });
assert.equal(store.getState().unreadCount, 7);
assert.equal(store.getState().notifications.length, 0, 'Heartbeat does not fabricate detail rows');
store.getState().setNotifications([{ id: 1, leido: false }, { id: 2, leido: true }]);
store.getState().setSummary({ unreadCount: 6, total: 10, version: 'b' });
store.getState().markRead(2);
assert.equal(store.getState().unreadCount, 6, 'Reading an already-read detail cannot replace the current summary count');
store.getState().markRead(1);
assert.equal(store.getState().unreadCount, 5, 'Reading an unread detail decrements the current count exactly once');
store.getState().markRead(1);
assert.equal(store.getState().unreadCount, 5);
store.getState().markAllRead();
assert.equal(store.getState().unreadCount, 0);
assert.ok(store.getState().notifications.every(n => n.leido));

const requests = [];
const api = load('app/src/api/notifications.ts', { './client': {
  get: (path, params, signal) => { requests.push({ path, signal }); return Promise.resolve({}); }, put() {},
} }).notificationsApi;
const abort = new AbortController();
await api.getSummary(abort.signal);
assert.equal(requests[0].path, '/notifications/summary');
assert.equal(requests[0].signal, abort.signal, 'Heartbeat forwards query cancellation');

let summary = { unreadCount: 4, total: 5, version: 'a' };
let queryOptions;
const invalidations = [];
const refs = [];
let refIndex = 0;
const client = { invalidateQueries: options => { invalidations.push(options); } };
const hook = load('app/src/hooks/useBootstrapNotifications.ts', {
  react: { useEffect: callback => callback(), useRef: initial => refs[refIndex++] ?? (refs[refIndex - 1] = { current: initial }) },
  '@tanstack/react-query': { useQueryClient: () => client, useQuery: options => { queryOptions = options; return { data: summary }; } },
  '@/api/notifications': { notificationsApi: api },
  '@/api/panel-cache': { configurePanelQueryCache() {}, applyPanelRevision() {}, PANEL_REVISION_EVENT: 'revision' },
  '@/stores/useNotificationStore': { useNotificationStore: selector => selector(store.getState()) },
});
hook.useBootstrapNotifications();
assert.deepEqual(Array.from(queryOptions.queryKey), ['notifications', 'summary']);
assert.equal(queryOptions.refetchInterval, 60_000);
assert.equal(store.getState().unreadCount, 4);
assert.equal(invalidations.length, 0, 'Initial heartbeat does not cause duplicate detail requests');
refIndex = 0;
summary = { unreadCount: 4, total: 5, version: 'b' };
hook.useBootstrapNotifications();
assert.deepEqual(Array.from(invalidations[0].queryKey), ['notifications', 'list'], 'Changed version invalidates only detail data; inactive pages stay lazy');
refIndex = 0;
hook.useBootstrapNotifications();
assert.equal(invalidations.length, 1, 'Unchanged heartbeat does not refetch evidence details');
await queryOptions.queryFn({ signal: abort.signal });
assert.equal(requests.at(-1).path, '/notifications/summary', 'Minute polling requests counts, not the evidence list');
assert.equal(requests.at(-1).signal, abort.signal);
console.log('Notification summary, store, and polling behavior checks passed');
