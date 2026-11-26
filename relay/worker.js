/**
 * Instagram-relay van OWN, als Cloudflare Worker.
 *
 * Eén vaste URL in de Meta-app ("OWN Social Feed") voor alle installaties
 * van de plugin OWN Instagram Feed: OAuth redirect URI, Deauthorize callback
 * en Data deletion request. Slaat niets op; wisselt geen tokens (dat doet de
 * site zelf).
 *
 *   GET  /?code=…&state=…        Instagram na het inloggen: controleer de
 *                                handtekening in de state en stuur door naar
 *                                het terugkeeradres daarin (de callback van
 *                                de site). Zie Auth\State::sign() in de plugin
 *                                (src/Auth/State.php); tests/CoreTest.php legt
 *                                het formaat vast. Alleen naar een host op
 *                                ALLOWED_HOSTS en het callback-pad van de
 *                                plugin: iedereen met de app-secret (die in
 *                                elke installatie staat) kan een geldige state
 *                                maken, dus de handtekening alleen is niet
 *                                genoeg om logins niet elders te laten landen.
 *   POST /?deauthorize           Meta: account heeft de app verwijderd.
 *   POST /?data-deletion         Meta: verwijderverzoek; antwoordt met
 *                                { url, confirmation_code }.
 *   GET  /?deletion-status=…     Statuspagina die Meta aan de gebruiker toont.
 *   GET  /privacy                Privacybeleid van de Meta-app (Privacy policy
 *                                URL), met #verwijderen als Data deletion
 *                                instructions URL.
 *   GET  /?check=<url>&sig=…     Vraag van de plugin: staat deze callback op
 *                                ALLOWED_HOSTS? sig = HMAC-SHA256(url, app
 *                                secret), base64url, zodat buitenstaanders de
 *                                lijst niet kunnen aftasten.
 *
 * De relay weet niet op welke site een account staat en verwijdert dus niets
 * zelf. Dat doen de sites: na 30 dagen met een ongeldig token wist de plugin
 * posts en beelden (Feed\Cleanup). Ligt de relay
 * plat, dan mislukt alleen nieuw koppelen; bestaande feeds verversen zonder.
 *
 * Instellen (in deze map, relay/):
 *   npx wrangler deploy
 *   npx wrangler secret put INSTAGRAM_APP_SECRET   # Instagram app secret
 *   npx wrangler secret put ALERT_WEBHOOK_URL      # optioneel, Slack of
 *                                                  # Google Chat webhook
 * Adres en ALLOWED_HOSTS: zie wrangler.toml; ALLOWED_HOSTS is ook in het
 * Cloudflare-dashboard aan te passen (Worker → Settings → Variables) zonder
 * opnieuw uit te rollen. Zonder webhook staat elke melding in de Workers-logs
 * (observability).
 */

const STATE_MAX_AGE = 30 * 60; // Zelfde geldigheid als de state op de site.
const CALLBACK_ROUTE = '/own-instagram/v1/oauth/callback';

export default {
	async fetch(request, env, ctx) {
		const url = new URL(request.url);
		const params = url.searchParams;

		// Openbaar en zonder secret: Meta controleert deze URL.
		if (url.pathname.replace(/\/+$/, '') === '/privacy') {
			return privacyPage();
		}

		if (!env.INSTAGRAM_APP_SECRET) {
			return text('De Instagram-relay is nog niet ingesteld.', 500);
		}

		if (params.has('check')) {
			return checkAllowed(params, env.INSTAGRAM_APP_SECRET, env.ALLOWED_HOSTS);
		}

		if (params.has('deletion-status')) {
			return statusPage((params.get('deletion-status') || '').replace(/[^a-f0-9]/g, ''));
		}

		if (params.has('deauthorize') || params.has('data-deletion')) {
			if (request.method !== 'POST') {
				return text('Alleen voor Meta.', 405);
			}
			const form = await request.formData().catch(() => null);
			const data = await verifySignedRequest(String(form?.get('signed_request') || ''), env.INSTAGRAM_APP_SECRET);
			if (!data) {
				return text('Ongeldig verzoek.', 403);
			}
			const userId = String(data.user_id || '').replace(/\D/g, '');

			if (params.has('deauthorize')) {
				ctx.waitUntil(notify(env, `Instagram: gebruiker ${userId} heeft "OWN Social Feed" verwijderd. De site met dit account toont "Opnieuw koppelen" en wist na 30 dagen zelf de posts en afbeeldingen. Direct opruimen: Ontkoppelen op de ontwikkelaarspagina.`));
				return new Response(null, { status: 200 });
			}

			const code = [...crypto.getRandomValues(new Uint8Array(8))].map((b) => b.toString(16).padStart(2, '0')).join('');
			ctx.waitUntil(notify(env, `Instagram: verwijderverzoek ${code} voor gebruiker ${userId}. De site met dit account wist de posts en afbeeldingen na 30 dagen met een ongeldig token zelf. Direct opruimen: Ontkoppelen op de ontwikkelaarspagina.`));
			return Response.json({
				url: `${url.origin}${url.pathname}?deletion-status=${code}`,
				confirmation_code: code,
			});
		}

		return forwardOAuth(params, env.INSTAGRAM_APP_SECRET, env.ALLOWED_HOSTS);
	},
};

/* ---------------------------------------------------------------------- */

async function forwardOAuth(params, secret, allowedHosts) {
	const parts = (params.get('state') || '').split('.');
	if (parts.length !== 2) {
		return text('Ongeldige koppelpoging.', 400);
	}

	const [payload, signature] = parts;
	if (!(await hmacVerify(secret, payload, signature))) {
		return text('Ongeldige koppelpoging.', 403);
	}

	let data;
	try {
		data = JSON.parse(new TextDecoder().decode(b64urlDecode(payload)));
	} catch {
		return text('Ongeldige koppelpoging.', 400);
	}

	const now = Math.floor(Date.now() / 1000);
	const issued = Number(data?.t || 0);
	if (issued < now - STATE_MAX_AGE || issued > now + 300) {
		return text('Deze koppelpoging is verlopen. Begin opnieuw via de koppellink.', 410);
	}

	let target;
	try {
		target = new URL(String(data.r || ''));
	} catch {
		return text('Ongeldig terugkeeradres.', 400);
	}
	if (!isAllowedTarget(target, allowedHosts)) {
		return text('Ongeldig terugkeeradres.', 400);
	}

	// Alleen wat Instagram meestuurt; de state ongewijzigd, de site zoekt 'm op.
	for (const key of ['code', 'state', 'error', 'error_reason', 'error_description']) {
		if (params.has(key)) {
			target.searchParams.set(key, params.get(key));
		}
	}

	return new Response(null, {
		status: 302,
		headers: { Location: target.toString(), ...baseHeaders() },
	});
}

/**
 * The plugin's Meta-app card asks whether its own callback would be accepted,
 * so a missing ALLOWED_HOSTS entry shows while setting up, not when a school
 * tries to connect. Signed, so the list can't be probed from outside.
 */
async function checkAllowed(params, secret, allowedHosts) {
	const target = params.get('check') || '';
	if (!(await hmacVerify(secret, target, params.get('sig') || ''))) {
		return json({ error: 'invalid_signature' }, 403);
	}
	let allowed = false;
	let host = '';
	try {
		const url = new URL(target);
		host = url.hostname;
		allowed = isAllowedTarget(url, allowedHosts);
	} catch {
		// Not a URL: not allowed.
	}
	return json({ allowed, host });
}

function json(data, status = 200) {
	return new Response(JSON.stringify(data), {
		status,
		headers: { 'Content-Type': 'application/json', ...baseHeaders() },
	});
}

/**
 * The return address must be the plugin's callback on an allowed host.
 *
 * ALLOWED_HOSTS: hostnames separated by spaces or commas; "*.example.nl"
 * also allows every subdomain. Without it nothing is forwarded (fail closed).
 * https only, except local development hosts (localhost, *.local, *.test).
 *
 * Exported for the tests.
 */
export function isAllowedTarget(target, allowedHosts) {
	const host = target.hostname.toLowerCase();
	const local = 'localhost' === host || host.endsWith('.local') || host.endsWith('.test');
	if (target.protocol !== 'https:' && !(local && target.protocol === 'http:')) {
		return false;
	}
	if (target.username || target.password) {
		return false;
	}

	const route = target.searchParams.get('rest_route');
	const isCallback = target.pathname.endsWith('/wp-json' + CALLBACK_ROUTE) || (route === CALLBACK_ROUTE && target.pathname.endsWith('/'));
	if (!isCallback) {
		return false;
	}

	return String(allowedHosts || '')
		.toLowerCase()
		.split(/[\s,]+/)
		.filter(Boolean)
		.some((entry) => (entry.startsWith('*.') ? host === entry.slice(2) || host.endsWith(entry.slice(1)) : host === entry));
}

/** Meta's signed_request: "<handtekening>.<payload>", beide base64url. */
async function verifySignedRequest(signedRequest, secret) {
	const parts = signedRequest.split('.');
	if (parts.length !== 2) {
		return null;
	}
	const [signature, payload] = parts;

	let data;
	try {
		data = JSON.parse(new TextDecoder().decode(b64urlDecode(payload)));
	} catch {
		return null;
	}
	if (String(data?.algorithm || '').toUpperCase() !== 'HMAC-SHA256') {
		return null;
	}

	return (await hmacVerify(secret, payload, signature)) ? data : null;
}

/** crypto.subtle.verify vergelijkt in constante tijd. */
async function hmacVerify(secret, message, signatureB64url) {
	let signature;
	try {
		signature = b64urlDecode(signatureB64url);
	} catch {
		return false;
	}
	const key = await crypto.subtle.importKey('raw', new TextEncoder().encode(secret), { name: 'HMAC', hash: 'SHA-256' }, false, ['verify']);
	return crypto.subtle.verify('HMAC', key, signature, new TextEncoder().encode(message));
}

function b64urlDecode(value) {
	const base64 = value.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - (value.length % 4)) % 4);
	return Uint8Array.from(atob(base64), (c) => c.charCodeAt(0));
}

/** Slack en Google Chat accepteren allebei { text }. */
async function notify(env, message) {
	console.log(message);
	if (!env.ALERT_WEBHOOK_URL) {
		return;
	}
	try {
		await fetch(env.ALERT_WEBHOOK_URL, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ text: message }),
		});
	} catch (error) {
		console.error('Webhook mislukt', error);
	}
}

function baseHeaders() {
	return {
		'X-Robots-Tag': 'noindex',
		'Cache-Control': 'no-store',
		'Referrer-Policy': 'no-referrer',
	};
}

function text(message, status) {
	return new Response(message, {
		status,
		headers: { 'Content-Type': 'text/plain; charset=utf-8', ...baseHeaders() },
	});
}

function statusPage(code) {
	const html = `<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Verwijderverzoek ontvangen</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;padding:16px;font:16px/1.5 system-ui,sans-serif;color:#1d2327;background:#f6f7f7}main{max-width:480px;padding:32px;background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}h1{margin:0 0 8px;font-size:20px}p{margin:0 0 8px;color:#50575e}code{font-size:14px}</style></head><body><main><h1>Verwijderverzoek ontvangen</h1><p>Je verzoek om je Instagram-gegevens te verwijderen is ontvangen. De websites die OWN bouwt en beheert, verwijderen de opgeslagen posts en afbeeldingen van je account uiterlijk binnen 30 dagen. Toegang tot je account is al ingetrokken.</p>${code ? `<p>Bevestigingscode: <code>${code}</code></p>` : ''}</main></body></html>`;
	return new Response(html, {
		status: 200,
		headers: { 'Content-Type': 'text/html; charset=utf-8', ...baseHeaders() },
	});
}

/**
 * Het privacybeleid van de Meta-app. Hier, op het adres van de login, zodat
 * het niet afhangt van een website die ooit verhuist of verdwijnt. Houd het
 * gelijk met wat de plugin echt leest en bewaart (Api\Client, Feed\*).
 */
function privacyPage() {
	const html = `<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Privacy – OWN Social Feed</title><style>body{margin:0;padding:40px 16px;font:16px/1.6 system-ui,sans-serif;color:#1d2327;background:#f6f7f7}main{max-width:720px;margin:0 auto;padding:32px;background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}h1{margin:0 0 4px;font-size:26px;line-height:1.25}h2{margin:32px 0 8px;font-size:19px}p,ul,ol{margin:0 0 12px;color:#3c434a}li{margin-bottom:4px}.meta{color:#646970;font-size:14px}a{color:#2271b1}hr{margin:40px 0 0;border:0;border-top:1px solid #dcdcde}</style></head><body><main>
<h1>Privacy – OWN Social Feed</h1>
<p class="meta">Laatst bijgewerkt: 26 november 2026 · <a href="#english">English summary</a></p>

<p>OWN Social Feed is de koppeling waarmee organisaties hun eigen Instagram-posts tonen op hun website, gebouwd en beheerd door OWN agency. Alleen de beheerder van een Instagram-account kan die koppeling maken: door in te loggen bij Instagram en toestemming te geven.</p>

<h2>Wie zijn wij</h2>
<p>OWN agency B.V., Meelfabriekplein 2h, 2312 LK Leiden. KvK 52160114. Vragen: <a href="mailto:hello@ownagency.nl">hello@ownagency.nl</a>.</p>

<h2>Welke gegevens</h2>
<p>Na toestemming leest de koppeling van het gekoppelde account:</p>
<ul>
<li>het account-ID en de gebruikersnaam;</li>
<li>de meest recente posts: afbeelding, bijschrift, link, datum en soort post (foto, video of carrousel);</li>
<li>een toegangssleutel (token) waarmee de website die posts mag ophalen.</li>
</ul>
<p>We lezen geen volgers, berichten, wachtwoorden of statistieken, en niets van mensen die de website bezoeken. We plaatsen niets op Instagram.</p>

<h2>Waarvoor en waar</h2>
<p>De gegevens dienen alleen om de posts op de website van de organisatie te tonen. Ze staan op die website zelf: posts en afbeeldingen in de database en uploads, de toegangssleutel alleen op de server. De website ververst de posts elk uur en bewaart niet meer dan de laatste twaalf.</p>
<p>Het inloggen loopt via connect.myownagency.nl, een doorgeefluik bij Cloudflare. Dat stuurt je na het inloggen terug naar de juiste website en slaat geen gegevens op; alleen technische logboeken blijven korte tijd bewaard.</p>
<p>We verkopen niets door, gebruiken de gegevens niet voor advertenties of profilering en delen ze met niemand anders dan Meta (dat de gegevens levert) en de hosting van de website.</p>

<h2>Hoe lang</h2>
<ul>
<li>Zolang het account gekoppeld is: de laatste posts, steeds ververst.</li>
<li>Bij ontkoppelen op de website: direct verwijderd, inclusief afbeeldingen en toegangssleutel.</li>
<li>Als de toegang op Instagram wordt ingetrokken of verloopt: verwijderd na uiterlijk 30 dagen.</li>
</ul>

<h2 id="verwijderen">Gegevens laten verwijderen</h2>
<p>Je kunt op drie manieren zorgen dat je gegevens worden verwijderd:</p>
<ol>
<li><strong>Via Instagram.</strong> Ga in de Instagram-app naar Instellingen → Website-machtigingen → Apps en websites en verwijder "OWN Social Feed". De toegang stopt direct; de website wist de posts, afbeeldingen en toegangssleutel binnen 30 dagen.</li>
<li><strong>Via de website.</strong> Vraag de beheerder van de website om de Instagram-koppeling te ontkoppelen (Instellingen → Instagram → Ontkoppelen). Dan is alles direct weg.</li>
<li><strong>Via ons.</strong> Mail <a href="mailto:hello@ownagency.nl">hello@ownagency.nl</a> met je Instagram-gebruikersnaam. We verwijderen de gegevens binnen 30 dagen en laten je weten wanneer het klaar is.</li>
</ol>

<h2>Je rechten</h2>
<p>Je mag ons vragen welke gegevens we van je hebben, ze laten corrigeren of laten verwijderen. Mail daarvoor <a href="mailto:hello@ownagency.nl">hello@ownagency.nl</a>. Ben je niet tevreden over hoe we met je gegevens omgaan, dan kun je een klacht indienen bij de <a href="https://autoriteitpersoonsgegevens.nl">Autoriteit Persoonsgegevens</a>. Zie ook de algemene <a href="https://ownagency.nl/privacyverklaring/">privacyverklaring van OWN</a>.</p>

<hr>
<h2 id="english">English summary</h2>
<p>OWN Social Feed lets organisations show their own Instagram posts on their website, built and maintained by OWN agency B.V. (Meelfabriekplein 2h, 2312 LK Leiden, the Netherlands; <a href="mailto:hello@ownagency.nl">hello@ownagency.nl</a>). With the account owner's permission it reads the account ID, username and recent posts (image, caption, link, date, type), plus an access token. This is stored only on that organisation's website and used only to display the posts; at most the latest twelve are kept. It is never sold, used for advertising or shared beyond Meta and the website's hosting. The login passes through connect.myownagency.nl (Cloudflare), which stores no data.</p>
<p><strong>Deleting your data:</strong> remove "OWN Social Feed" in Instagram under Settings → Website permissions → Apps and websites (the website deletes everything within 30 days), ask the website's administrator to disconnect Instagram (immediate), or email <a href="mailto:hello@ownagency.nl">hello@ownagency.nl</a> with your Instagram username.</p>
</main></body></html>`;
	return new Response(html, {
		status: 200,
		headers: {
			'Content-Type': 'text/html; charset=utf-8',
			'Cache-Control': 'public, max-age=3600',
			'Referrer-Policy': 'no-referrer',
		},
	});
}
