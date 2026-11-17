/* Shared UI behaviour: image fallbacks, filters, tabs, sorting, toasts, drawers. */
(function () {
  'use strict';

  /* ---- Broken images fall back to a neutral silhouette (or hide, for flags) ---- */
  var silhouette = 'data:image/svg+xml;utf8,' + encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#d9e2db"/>' +
    '<circle cx="50" cy="38" r="18" fill="#9fb3a6"/><path d="M14 100c2-26 18-38 36-38s34 12 36 38z" fill="#9fb3a6"/></svg>');
  document.addEventListener('error', function (e) {
    var t = e.target;
    if (!t || t.tagName !== 'IMG' || t.dataset.fallback) return;
    t.dataset.fallback = '1';
    if (t.classList.contains('flag') || t.classList.contains('flag-chip')) { t.hidden = true; return; }
    t.src = silhouette;
  }, true);
  // Images that already failed before this script ran
  window.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('img').forEach(function (img) {
      if (img.complete && img.naturalWidth === 0 && !img.dataset.fallback) img.dispatchEvent(new Event('error'));
    });
  });

  /* ---- Segmented filter groups: <div class="seg" data-seg> <button data-value=""> ---- */
  window.segGroup = function (el, onChange) {
    var buttons = el.querySelectorAll('button');
    buttons.forEach(function (b) {
      b.addEventListener('click', function () {
        buttons.forEach(function (o) { o.setAttribute('aria-pressed', o === b ? 'true' : 'false'); });
        onChange(b.dataset.value);
      });
    });
  };

  /* ---- Tabs: <div role="tablist"> <button role="tab" aria-controls="id"> ---- */
  window.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[role="tablist"]').forEach(function (list) {
      var tabs = list.querySelectorAll('[role="tab"]');
      tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
          tabs.forEach(function (t) {
            var on = t === tab;
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            var panel = document.getElementById(t.getAttribute('aria-controls'));
            if (panel) panel.hidden = !on;
          });
        });
      });
    });

    /* ---- Sortable tables: <table data-sortable>, <th data-sort="num|text"> ---- */
    document.querySelectorAll('table[data-sortable]').forEach(function (table) {
      var ths = table.querySelectorAll('th[data-sort]');
      ths.forEach(function (th, _i) {
        th.tabIndex = 0;
        var go = function () {
          var idx = Array.prototype.indexOf.call(th.parentNode.children, th);
          var dir = th.getAttribute('aria-sort') === 'descending' ? 'ascending' : 'descending';
          if (th.dataset.sort === 'text' && !th.getAttribute('aria-sort')) dir = 'ascending';
          ths.forEach(function (o) { o.removeAttribute('aria-sort'); });
          th.setAttribute('aria-sort', dir);
          var body = table.tBodies[0];
          var rows = Array.prototype.slice.call(body.rows);
          rows.sort(function (a, b) {
            var x = a.cells[idx].dataset.v !== undefined ? a.cells[idx].dataset.v : a.cells[idx].textContent.trim();
            var y = b.cells[idx].dataset.v !== undefined ? b.cells[idx].dataset.v : b.cells[idx].textContent.trim();
            var r = th.dataset.sort === 'num' ? (parseFloat(x) || 0) - (parseFloat(y) || 0) : x.localeCompare(y);
            return dir === 'ascending' ? r : -r;
          });
          rows.forEach(function (r) { body.appendChild(r); });
        };
        th.addEventListener('click', go);
        th.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
      });
    });

    /* ---- Table search: <input data-table-search="#tableId"> ---- */
    document.querySelectorAll('[data-table-search]').forEach(function (input) {
      var table = document.querySelector(input.dataset.tableSearch);
      if (!table) return;
      input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase();
        Array.prototype.forEach.call(table.tBodies[0].rows, function (r) {
          r.hidden = q && r.textContent.toLowerCase().indexOf(q) === -1;
        });
      });
    });
  });

  /* ---- Toast ---- */
  window.toast = function (msg, kind) {
    var host = document.querySelector('.toast-host');
    if (!host) { host = document.createElement('div'); host.className = 'toast-host'; host.setAttribute('role', 'status'); document.body.appendChild(host); }
    var t = document.createElement('div');
    t.className = 'toast' + (kind ? ' is-' + kind : '');
    t.textContent = msg;
    host.appendChild(t);
    setTimeout(function () { t.remove(); }, 3600);
  };

  /* ---- Drawer: click on the dark backdrop or press Esc to close ---- */
  document.addEventListener('click', function (e) {
    if (e.target.classList && e.target.classList.contains('modal')) e.target.style.display = 'none';
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.modal').forEach(function (m) { m.style.display = 'none'; });
  });

  /* ---- Confirm before destructive submits: <form data-confirm="Message"> ---- */
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });
})();
