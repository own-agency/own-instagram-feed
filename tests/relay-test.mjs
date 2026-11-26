// Relay allowlist tests: node tests/relay-test.mjs (no Cloudflare needed).

import { isAllowedTarget } from '../relay/worker.js';
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
console.log(fail ? fail + " FAILED" : "ALL PASSED");
process.exit(fail ? 1 : 0);
