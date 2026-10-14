(() => {
  'use strict';
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  /* Shared singleton lightbox: only one active at a time. */
  let lb = null, ctx = null, opener = null;
  function ensureLB() {
    if (lb) return lb;
    lb = document.createElement('div');
    lb.className = 'pgl-lightbox';
    lb.setAttribute('role', 'dialog');
    lb.setAttribute('aria-modal', 'true');
    lb.setAttribute('aria-label', 'Image viewer');
    lb.innerHTML =
      '<img class="pgl-lb-img" alt="Gallery image preview">' +
      '<div class="pgl-lb-ui">' +
      '<button type="button" class="pgl-lb-prev">←</button>' +
      '<span class="pgl-lb-cap"></span>' +
      '<button type="button" class="pgl-lb-next">→</button>' +
      '<button type="button" class="pgl-lb-close">✕ Close</button>' +
      '</div>';
    document.body.appendChild(lb);
    const img = lb.querySelector('.pgl-lb-img');
    img.addEventListener('error', () => {
      // Broken/deleted file: drop it from navigation and move on.
      if (!ctx) return;
      ctx.items.splice(ctx.idx, 1);
      if (!ctx.items.length) { close(); return; }
      if (ctx.idx >= ctx.items.length) ctx.idx = 0;
      show();
    });
    lb.querySelector('.pgl-lb-prev').addEventListener('click', (e) => { e.stopPropagation(); step(-1); });
    lb.querySelector('.pgl-lb-next').addEventListener('click', (e) => { e.stopPropagation(); step(1); });
    lb.querySelector('.pgl-lb-close').addEventListener('click', close);
    lb.addEventListener('click', (e) => { if (e.target === lb) close(); });
    document.addEventListener('keydown', (e) => {
      if (!ctx) return;
      if (e.key === 'Escape') { close(); return; }
      if (e.key === 'ArrowLeft') { step(-1); return; }
      if (e.key === 'ArrowRight') { step(1); return; }
      if (e.key === 'Tab') {
        const btns = [...lb.querySelectorAll('button')].filter((b) => !b.hidden);
        if (!btns.length) return;
        const first = btns[0], last = btns[btns.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    });
    return lb;
  }
  function step(d) {
    if (!ctx || !ctx.items.length) return;
    ctx.idx = (ctx.idx + d + ctx.items.length) % ctx.items.length;
    show();
  }
  function show() {
    const it = ctx.items[ctx.idx];
    if (!it) { close(); return; }
    const box = ensureLB();
    const img = box.querySelector('.pgl-lb-img');
    const cap = box.querySelector('.pgl-lb-cap');
    img.src = it.large || it.full;
    img.alt = it.alt || it.title;
    let text = (ctx.idx + 1) + ' of ' + ctx.items.length + ' · ' + it.title;
    if (ctx.captions && it.caption) text += ' — ' + it.caption;
    cap.textContent = text;
    box.querySelector('.pgl-lb-prev').hidden = ctx.items.length < 2;
    box.querySelector('.pgl-lb-next').hidden = ctx.items.length < 2;
  }
  function open(items, idx, openEl, captions) {
    if (!items.length) return;
    if (ctx) close(true);
    ensureLB();
    ctx = { items: items.slice(), idx, captions };
    opener = openEl || null;
    lb.classList.add('open');
    document.body.style.overflow = 'hidden';
    show();
    lb.querySelector('.pgl-lb-close').focus();
  }
  function close(silent) {
    if (!lb) { ctx = null; return; }
    lb.classList.remove('open');
    document.body.style.overflow = '';
    ctx = null;
    if (!silent && opener && opener.focus) { try { opener.focus(); } catch (e) {} }
    opener = null;
  }

  function init(root) {
    let items = [];
    let catNames = {};
    root.querySelectorAll('script.pgl-data').forEach((el) => {
      try { const v = JSON.parse(el.textContent || '[]'); if (Array.isArray(v)) items = v; } catch (e) {}
    });
    root.querySelectorAll('script.pgl-cats').forEach((el) => {
      try { const v = JSON.parse(el.textContent || '{}'); if (v && typeof v === 'object') catNames = v; } catch (e) {}
    });
    const grid = root.querySelector('.pgl-grid');
    const filters = [...root.querySelectorAll('.pgl-filter')];
    const captions = root.dataset.pglCaptions === '1';
    const emptyMsg = root.dataset.pglEmpty || 'No images in this category yet.';
    let active = 'all';
    const on = root.querySelector('.pgl-filter.is-active');
    if (on && on.dataset.cat) active = on.dataset.cat;
    const broken = new Set();

    const filtered = () => items.filter((it) => !broken.has(it.id) && (active === 'all' || (it.cats || []).includes(active)));
    const labelFor = (it) => {
      const order = Object.keys(catNames);
      for (const s of order) { if ((it.cats || []).includes(s)) return catNames[s]; }
      return '';
    };
    const render = () => {
      const list = filtered();
      filters.forEach((b) => {
        const is = b.dataset.cat === active;
        b.classList.toggle('is-active', is);
        if (is) b.setAttribute('aria-current', 'true');
        else b.removeAttribute('aria-current');
      });
      if (!list.length) {
        grid.innerHTML = '<p class="pgl-empty">' + esc(emptyMsg) + '</p>';
        return;
      }
      grid.innerHTML = list.map((it) => {
        const bits = [];
        if (captions && it.caption) bits.push(esc(it.caption));
        const lab = labelFor(it);
        if (lab) bits.push(esc(lab));
        const cap = bits.length ? '<figcaption><strong>' + esc(it.title) + '</strong>' + bits.join(' · ') + '</figcaption>' : (captions ? '<figcaption><strong>' + esc(it.title) + '</strong></figcaption>' : '');
        return '<figure class="pgl-item" data-pgl-id="' + it.id + '">' +
          '<a class="pgl-open" href="' + esc(it.full) + '" data-pgl-open="' + it.id + '" data-elementor-open-lightbox="no" aria-label="Open image: ' + esc(it.title) + '">' +
          '<img src="' + esc(it.thumb) + '" alt="' + esc(it.alt || it.title) + '" loading="lazy" decoding="async"></a>' + cap + '</figure>';
      }).join('');
      grid.querySelectorAll('img').forEach((im) => {
        im.addEventListener('error', () => {
          const fig = im.closest('figure');
          const id = fig ? parseInt(fig.dataset.pglId || '0', 10) : 0;
          if (id) broken.add(id);
          if (fig) fig.remove();
          if (!grid.querySelector('.pgl-item')) grid.innerHTML = '<p class="pgl-empty">' + esc(emptyMsg) + '</p>';
        }, { once: true });
      });
    };
    filters.forEach((b) => b.addEventListener('click', (e) => {
      e.preventDefault();
      active = b.dataset.cat || 'all';
      render();
    }));
    grid.addEventListener('click', (e) => {
      const a = e.target.closest('a[data-pgl-open]');
      if (!a) return;
      e.preventDefault();
      e.stopPropagation(); // keep outer galleries/lightboxes (e.g. Elementor) from also opening
      const id = parseInt(a.dataset.pglOpen || '0', 10);
      const list = filtered();
      const k = list.findIndex((it) => it.id === id);
      if (k < 0) return;
      open(list, k, a, captions);
    });
    render();
  }

  function boot() {
    document.querySelectorAll('.phagebiome-gallery').forEach(init);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
