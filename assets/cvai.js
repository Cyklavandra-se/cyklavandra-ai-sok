(function () {
	'use strict';
	var data = window.cvaiData || {};
	var T = data.i18n || {};
	function t(k, fb) { return T[k] || fb; }

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	var LABELS = {
		land: t('lbl_land', 'Land'), manad: t('lbl_manad', 'Månad'), aktivitet: t('lbl_aktivitet', 'Aktivitet'),
		niva: t('lbl_niva', 'Nivå'), antal: t('lbl_antal', 'Antal'), ovrigt: t('lbl_ovrigt', 'Övrigt')
	};

	function card(h, top, del, pos) {
		var meta = (h.meta || []).map(function (m) { return '<span class="cvai-m">' + esc(m) + '</span>'; }).join('');
		if (h.level) meta += '<span class="cvai-m cvai-lvl">' + esc(h.level) + '</span>';
		return '<article class="cvai-card' + (top ? ' cvai-top' : '') + '" data-id="' + esc(h.id) + '" data-del="' + del + '" data-pos="' + pos + '">' +
			(h.img ? '<a class="cvai-img" href="' + esc(h.url) + '"><img src="' + esc(h.img) + '" alt="" loading="lazy"></a>' : '') +
			'<div class="cvai-body">' +
			'<div class="cvai-meta">' + meta + '</div>' +
			'<h3 class="cvai-h"><a href="' + esc(h.url) + '">' + esc(h.title) + '</a></h3>' +
			(h.why ? '<p class="cvai-why"><strong>' + esc(t('why', 'Därför passar den:')) + ' </strong>' + esc(h.why) + '</p>' : '') +
			'<a class="cvai-more" href="' + esc(h.url) + '">' + esc(t('more', 'Läs mer och boka →')) + '</a>' +
			'</div></article>';
	}

	// Samma kortlayout som resorna, med en kort text från destinationssidan.
	function destCard(h, i) {
		return '<article class="cvai-card cvai-destcard" data-id="' + esc(h.id) + '" data-del="dest" data-pos="' + (i + 1) + '">' +
			(h.img ? '<a class="cvai-img" href="' + esc(h.url) + '"><img src="' + esc(h.img) + '" alt="" loading="lazy"></a>' : '') +
			'<div class="cvai-body">' +
			'<h3 class="cvai-h"><a href="' + esc(h.url) + '">' + esc(h.title) + '</a></h3>' +
			(h.text ? '<p class="cvai-why">' + esc(h.text) + '</p>' : '') +
			'<a class="cvai-more" href="' + esc(h.url) + '">' + esc(t('dest_more', 'Till destinationssidan →')) + '</a>' +
			'</div></article>';
	}

	function render(root, d) {
		var out = root.querySelector('.cvai-out');
		var html = '';
		var interp = d.interpretation || {};
		var tags = Object.keys(LABELS).filter(function (k) { return interp[k]; }).map(function (k) {
			return '<span class="cvai-tag">' + LABELS[k] + ': ' + esc(interp[k]) + '</span>';
		}).join('');
		if (tags) html += '<div class="cvai-interp"><span>' + esc(t('interp_title', 'Så tolkade vi er fråga')) + '</span>' + tags + '</div>';
		if (d.summary) html += '<p class="cvai-summary">' + esc(d.summary) + '</p>';
		if (d.hits && d.hits.length) {
			html += '<h2 class="cvai-sub">' + esc(t('best', 'Bäst för er')) + '</h2><div class="cvai-grid">' +
				d.hits.map(function (h, i) { return card(h, i === 0, 'best', i + 1); }).join('') + '</div>';
		}
		if (d.alternatives && d.alternatives.length) {
			html += '<h2 class="cvai-sub">' + esc(t('also', 'Kan också passa')) + '</h2><div class="cvai-grid">' +
				d.alternatives.map(function (h, i) { return card(h, false, 'also', i + 1); }).join('') + '</div>';
		}
		if (d.destinations && d.destinations.length) {
			html += '<h2 class="cvai-sub">' + esc(t('dests', 'Läs mer om destinationen')) + '</h2><div class="cvai-grid">' +
				d.destinations.map(destCard).join('') + '</div>';
		}
		if (!(d.hits && d.hits.length) && !(d.alternatives && d.alternatives.length)) {
			html += '<p>' + esc(t('none', 'Vi hittade ingen resa som passar riktigt.')) + '</p>';
		}
		out.innerHTML = html;
		out.setAttribute('data-sok', d.sok || '');
		out.setAttribute('data-token', d.token || '');
	}

	// Sparat resultat i fliken (sessionStorage), så att det finns kvar när kunden går tillbaka.
	var STORE_MAX_AGE = 30 * 60 * 1000;
	function storeKey() { return 'cvai:' + location.pathname + ':' + (data.lang || ''); }
	function storeGet() {
		try {
			var v = JSON.parse(sessionStorage.getItem(storeKey()) || 'null');
			return v && v.d && Date.now() - v.t < STORE_MAX_AGE ? v : null;
		} catch (e) { return null; }
	}
	function storeSet(v) {
		try { sessionStorage.setItem(storeKey(), JSON.stringify(v)); } catch (e) { /* privat läge m.m. */ }
	}

	// Klick på en resa/destination skickas i bakgrunden (sendBeacon stoppar inte sidbytet).
	function trackClick(out, el) {
		var a = el.closest ? el.closest('a') : null;
		var card = a && a.closest('.cvai-card');
		var sok = out.getAttribute('data-sok');
		if (!card || !sok || !data.clickUrl) return;
		var body = JSON.stringify({
			sok: Number(sok), token: out.getAttribute('data-token'),
			id: card.getAttribute('data-id'), del: card.getAttribute('data-del'), plats: Number(card.getAttribute('data-pos'))
		});
		try {
			if (navigator.sendBeacon && navigator.sendBeacon(data.clickUrl, new Blob([body], { type: 'application/json' }))) return;
		} catch (e) { /* faller tillbaka på fetch */ }
		try { fetch(data.clickUrl, { method: 'POST', credentials: 'same-origin', keepalive: true, headers: { 'Content-Type': 'application/json' }, body: body }); } catch (e) {}
	}

	function init(root) {
		var form = root.querySelector('.cvai-form');
		var q = root.querySelector('.cvai-q');
		var btn = root.querySelector('.cvai-go');
		var status = root.querySelector('.cvai-status');
		var chips = root.querySelector('.cvai-chips');
		var busy = false;

		// Allt med klassen cvai-dolj (t.ex. kartan, satt som CSS-klass i Avada) och det som
		// shortcodens attribut dolj pekar ut döljs när kunden söker, och visas igen vid fel.
		var hideSel = '.cvai-dolj' + (root.getAttribute('data-cvai-hide') ? ', ' + root.getAttribute('data-cvai-hide') : '');
		function toggleExtras(hide) {
			var els;
			try { els = document.querySelectorAll(hideSel); } catch (e) { els = document.querySelectorAll('.cvai-dolj'); }
			Array.prototype.forEach.call(els, function (el) {
				if (root.contains(el)) return;
				if (hide) {
					if (!el.hasAttribute('data-cvai-hidden')) {
						el.setAttribute('data-cvai-hidden', el.style.getPropertyValue('display') + '|' + el.style.getPropertyPriority('display'));
					}
					el.style.setProperty('display', 'none', 'important'); // Avada sätter ibland display med !important
				} else if (el.hasAttribute('data-cvai-hidden')) {
					var prev = el.getAttribute('data-cvai-hidden').split('|');
					el.style.removeProperty('display');
					if (prev[0]) el.style.setProperty('display', prev[0], prev[1] || '');
					el.removeAttribute('data-cvai-hidden');
				}
			});
		}

		(data.examples || []).forEach(function (e) {
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'cvai-chip';
			var span = document.createElement('span');
			span.className = 'cvai-chip-t';
			span.textContent = e;
			b.appendChild(span);
			b.title = e;
			b.addEventListener('click', function () { q.value = e; run(true); });
			chips.appendChild(b);
		});

		// fromExample = sökningen startades med en exempelknapp (markeras i sökhistoriken).
		function run(fromExample) {
			var text = q.value.trim();
			if (busy || text.length < 5) {
				if (text.length < 5) status.innerHTML = '<span class="cvai-err">' + esc(t('err_short', 'Beskriv gärna lite mer vad ni söker.')) + '</span>';
				return;
			}
			busy = true;
			btn.disabled = true;
			root.querySelector('.cvai-out').innerHTML = '';
			if (chips) chips.hidden = true;
			toggleExtras(true);
			status.innerHTML = '<div class="cvai-loading">' +
				'<span class="cvai-spin" aria-hidden="true"></span>' +
				'<span class="cvai-loading-txt">' + esc(t('loading', 'Letar bland våra resor')) + '</span>' +
				'<span class="cvai-bar" aria-hidden="true"><span></span></span></div>';
			if (window.innerWidth < 800 && status.scrollIntoView) status.scrollIntoView({ behavior: 'smooth', block: 'center' });

			fetch(data.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ q: text, lang: data.lang || '', exempel: fromExample === true })
			})
				.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
				.then(function (res) {
					if (!res.ok || res.j.error) throw new Error(res.j.error || t('err_generic', 'Något gick fel.'));
					status.innerHTML = '';
					render(root, res.j);
					storeSet({ t: Date.now(), q: text, d: res.j });
					var top = root.querySelector('.cvai-out');
					if (top && top.scrollIntoView && window.innerWidth < 800) top.scrollIntoView({ behavior: 'smooth', block: 'start' });
				})
				.catch(function (e) {
					if (chips) chips.hidden = false;
					toggleExtras(false);
					status.innerHTML = '<span class="cvai-err">' + esc(e.message || t('err_generic', 'Något gick fel. Försök igen.')) + '</span>';
				})
				.then(function () { busy = false; btn.disabled = false; });
		}

		var out = root.querySelector('.cvai-out');
		out.addEventListener('click', function (e) { trackClick(out, e.target); });
		out.addEventListener('auxclick', function (e) { if (e.button === 1) trackClick(out, e.target); }); // mittenklick = ny flik

		// Spara var på sidan kunden var när de lämnar, och visa det sparade resultatet när de kommer tillbaka.
		window.addEventListener('pagehide', function () {
			var v = storeGet();
			if (v && v.q === q.value.trim()) { v.y = window.scrollY; storeSet(v); }
		});
		var saved = storeGet();
		if (saved) {
			q.value = saved.q;
			if (chips) chips.hidden = true;
			toggleExtras(true);
			render(root, saved.d);
			if (typeof saved.y === 'number') {
				if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
				setTimeout(function () { window.scrollTo(0, saved.y); }, 0);
			}
		}

		form.addEventListener('submit', function (e) { e.preventDefault(); run(); });
		q.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) { e.preventDefault(); run(); }
		});
	}

	function boot() {
		document.querySelectorAll('[data-cvai]').forEach(init);
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
	else boot();
})();
