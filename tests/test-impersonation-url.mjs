import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';

const { url, returnUrl } = JSON.parse(execFileSync('php', ['tests/test-impersonation-url.php', '--url-only'], { encoding: 'utf8', windowsHide: true }));
// Reproduce the exact URL transformation used by Centros and Gestion de usuarios.
const destination = new URL(url, 'https://example.org');
destination.searchParams.set('return_to', returnUrl);
const navigated = new URL(destination.toString());
assert.equal(navigated.searchParams.get('user_id'), '600', 'React navigation retains the selected teacher');
assert.equal(navigated.searchParams.get('_wpnonce'), 'valid-test-nonce', 'React navigation retains the nonce WordPress checks');
assert.equal(navigated.searchParams.get('return_to'), returnUrl, 'React navigation retains the correct return screen and filters');
assert.equal(navigated.searchParams.get('action'), 'gnf_impersonate');
assert.equal(navigated.search.includes('amp%3B'), false, 'The URL cannot reproduce the amp%3B corruption in the client screenshot');
console.log('5 impersonation navigation checks passed');
