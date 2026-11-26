// Relay allowlist tests: node tests/relay-test.mjs (no Cloudflare needed).

import worker, { isAllowedTarget } from '../relay/worker.js';
import { createHmac } from 'node:crypto';
const A = 'ldvleiden.nl own-framework.local *.example.nl';
let fail = 0;
const t = (label, url, expect, hosts = A) => { const ok = isAllowedTarget(new URL(url), hosts) === expect; console.log((ok ? 'PASS ' : 'FAIL ') + label); if (!ok) fail++; };
t('main site callback', 'https://ldvleiden.nl/wp-json/own-instagram/v1/oauth/callback', true);
t('local http callback', 'http://own-framework.local/wp-json/own-instagram/v1/oauth/callback', true);
t('rest_route form', 'https://ldvleiden.nl/?rest_route=/own-instagram/v1/oauth/callback', true);
t('wildcard subdomain', 'https://a.example.nl/wp-json/own-instagram/v1/oauth/callback', true);
t('wildcard apex', 'https://example.nl/wp-json/own-instagram/v1/oauth/callback', true);
t('foreign host', 'https://evil.test.com/wp-json/own-instagram/v1/oauth/callback', false);
t('lookalike host', 'https://ldvleiden.nl.evil.com/wp-json/own-instagram/v1/oauth/callback', false);
t('suffix trick', 'https://evilldvleiden.nl/wp-json/own-instagram/v1/oauth/callback', false);
t('http on real host', 'http://ldvleiden.nl/wp-json/own-instagram/v1/oauth/callback', false);
t('other path on allowed host', 'https://ldvleiden.nl/wp-admin/', false);
t('credentials in url', 'https://x:y@ldvleiden.nl/wp-json/own-instagram/v1/oauth/callback', false);
t('javascript scheme', 'javascript:alert(1)//ldvleiden.nl/wp-json/own-instagram/v1/oauth/callback', false);
t('no allowlist = refuse', 'https://ldvleiden.nl/wp-json/own-instagram/v1/oauth/callback', false, '');
t('subdir install', 'https://ldvleiden.nl/site/wp-json/own-instagram/v1/oauth/callback', true);

// --- Signed allowlist check (?check=…&sig=…)
const secret = 'test-secret';
const env = { INSTAGRAM_APP_SECRET: secret, ALLOWED_HOSTS: A };
const sign = (v) => createHmac('sha256', secret).update(v).digest('base64url');
const ask = async (target, sig) => {
	const res = await worker.fetch(new Request('https://relay.test/?' + new URLSearchParams({ check: target, sig })), env, { waitUntil() {} });
	return { status: res.status, body: await res.json() };
};
const cb = 'https://ldvleiden.nl/wp-json/own-instagram/v1/oauth/callback';
const bad = 'https://unknown.nl/wp-json/own-instagram/v1/oauth/callback';
let r = await ask(cb, sign(cb));
if (!(r.status === 200 && r.body.allowed === true && r.body.host === 'ldvleiden.nl')) { console.log('FAIL check: allowed host'); fail++; } else console.log('PASS check: allowed host');
r = await ask(bad, sign(bad));
if (!(r.status === 200 && r.body.allowed === false)) { console.log('FAIL check: unknown host'); fail++; } else console.log('PASS check: unknown host');
r = await ask(cb, sign(cb) + 'x');
if (r.status !== 403) { console.log('FAIL check: bad signature refused'); fail++; } else console.log('PASS check: bad signature refused');
r = await ask(cb, sign(bad));
if (r.status !== 403) { console.log('FAIL check: signature for another url refused'); fail++; } else console.log('PASS check: signature for another url refused');

console.log(fail ? fail + " FAILED" : "ALL PASSED");
process.exit(fail ? 1 : 0);
