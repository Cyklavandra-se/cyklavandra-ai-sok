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

	function card(h, top) {
		var meta = (h.meta || []).map(function (m) { return '<span class="cvai-m">' + esc(m) + '</span>'; }).join('');
		if (h.level) meta += '<span class="cvai-m cvai-lvl">' + esc(h.level) + '</span>';
		return '<article class="cvai-card' + (top ? ' cvai-top' : '') + '">' +
			(h.img ? '<a class="cvai-img" href="' + esc(h.url) + '"><img src="' + esc(h.img) + '" alt="" loading="lazy"></a>' : '') +
			'<div class="cvai-body">' +
			'<div class="cvai-meta">' + meta + '</div>' +
			'<h3 class="cvai-h"><a href="' + esc(h.url) + '">' + esc(h.title) + '</a></h3>' +
			(h.why ? '<p class="cvai-why"><strong>' + esc(t('why', 'Därför passar den:')) + ' </strong>' + esc(h.why) + '</p>' : '') +
			'<a class="cvai-more" href="' + esc(h.url) + '">' + esc(t('more', 'Läs mer och boka →')) + '</a>' +
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
			html += '<h4 class="cvai-sub">' + esc(t('best', 'Bäst för er')) + '</h4><div class="cvai-grid">' +
				d.hits.map(function (h, i) { return card(h, i === 0); }).join('') + '</div>';
		}
		if (d.alternatives && d.alternatives.length) {
			html += '<h4 class="cvai-sub">' + esc(t('also', 'Kan också passa')) + '</h4><div class="cvai-grid">' +
				d.alternatives.map(function (h) { return card(h, false); }).join('') + '</div>';
		}
		if (!(d.hits && d.hits.length) && !(d.alternatives && d.alternatives.length)) {
			html += '<p>' + esc(t('none', 'Vi hittade ingen resa som passar riktigt.')) + '</p>';
		}
		out.innerHTML = html;
	}

	function init(root) {
		var form = root.querySelector('.cvai-form');
		var q = root.querySelector('.cvai-q');
		var btn = root.querySelector('.cvai-go');
		var status = root.querySelector('.cvai-status');
		var chips = root.querySelector('.cvai-chips');
		var busy = false;

		(data.examples || []).forEach(function (e) {
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'cvai-chip';
			b.textContent = e.length > 62 ? e.slice(0, 60) + '…' : e;
			b.title = e;
			b.addEventListener('click', function () { q.value = e; run(); });
			chips.appendChild(b);
		});

		function run() {
			var text = q.value.trim();
			if (busy || text.length < 5) {
				if (text.length < 5) status.innerHTML = '<span class="cvai-err">' + esc(t('err_short', 'Beskriv gärna lite mer vad ni söker.')) + '</span>';
				return;
			}
			busy = true;
			btn.disabled = true;
			root.querySelector('.cvai-out').innerHTML = '';
			status.innerHTML = '<span class="cvai-loading">' + esc(t('loading', 'Letar bland våra resor')) + '</span>';

			fetch(data.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ q: text, lang: data.lang || '' })
			})
				.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
				.then(function (res) {
					if (!res.ok || res.j.error) throw new Error(res.j.error || t('err_generic', 'Något gick fel.'));
					status.innerHTML = '';
					render(root, res.j);
					var top = root.querySelector('.cvai-out');
					if (top && top.scrollIntoView && window.innerWidth < 800) top.scrollIntoView({ behavior: 'smooth', block: 'start' });
				})
				.catch(function (e) {
					status.innerHTML = '<span class="cvai-err">' + esc(e.message || t('err_generic', 'Något gick fel. Försök igen.')) + '</span>';
				})
				.then(function () { busy = false; btn.disabled = false; });
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
