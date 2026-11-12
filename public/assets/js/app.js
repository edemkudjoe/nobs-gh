// Small helpers for every page: password eye button and the menu button.
// Pages still work without this script.
(function () {
  'use strict';
  var root = document.documentElement;

  /* 1. Eye button on every password field (login.php already has its own). */
  var EYE = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
  var EYE_OFF = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/><path d="M3 3l18 18"/></svg>';

  document.querySelectorAll('input[type="password"]').forEach(function (input) {
    if (input.closest('.pwbox')) { return; }
    var box = document.createElement('div');
    box.className = 'pwbox';
    input.parentNode.insertBefore(box, input);
    box.appendChild(input);
    var b = document.createElement('button');
    b.type = 'button';
    b.setAttribute('aria-label', 'Show password');
    b.setAttribute('aria-pressed', 'false');
    b.innerHTML = EYE;
    box.appendChild(b);
    b.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      b.setAttribute('aria-pressed', show ? 'true' : 'false');
      b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      b.innerHTML = show ? EYE_OFF : EYE;
    });
  });

  /* 2. Tables: scroll box, labels for the phone card layout, status badges. */
  var STATUS = {
    critical: 'red', rejected: 'red', declined: 'red', expired: 'red', discarded: 'red', missed: 'red', lapsed: 'red',
    urgent: 'amber', pending: 'amber', testing: 'amber', offered: 'amber', deferred: 'amber', reserved: 'amber',
    available: 'green', accepted: 'green', fulfilled: 'green', completed: 'green', approved: 'green', active: 'green', allocated: 'green', verified: 'green',
    routine: 'grey', cancelled: 'grey', scheduled: 'grey', inactive: 'grey', submitted: 'grey'
  };
  document.querySelectorAll('.main table').forEach(function (table) {
    var heads = Array.prototype.map.call(table.querySelectorAll('thead th'), function (th) { return th.textContent.trim(); });
    table.querySelectorAll('tbody tr').forEach(function (tr) {
      Array.prototype.forEach.call(tr.children, function (td, i) {
        if (td.tagName !== 'TD' || td.colSpan > 1) { return; }
        if (heads[i]) { td.setAttribute('data-label', heads[i]); }
        var text = td.textContent.trim();
        var kind = STATUS[text.toLowerCase()];
        var cell = document.createElement('div');
        cell.className = 'cell';
        if (kind && !td.querySelector('form, a, input, button, select')) {
          var badge = document.createElement('span');
          badge.className = 'badge badge-' + kind;
          badge.textContent = text;
          cell.appendChild(badge);
          td.textContent = '';
        } else {
          while (td.firstChild) { cell.appendChild(td.firstChild); }
        }
        td.appendChild(cell);
      });
    });
    if (!table.parentNode.classList.contains('table-wrap')) {
      var wrap = document.createElement('div');
      wrap.className = 'table-wrap';
      table.parentNode.insertBefore(wrap, table);
      wrap.appendChild(table);
    }
  });

  /* 2. Menu button for the signed-in pages. */
  var toggle = document.getElementById('navtoggle');
  var nav = document.getElementById('sidenav');
  if (!toggle || !nav) { return; }
  var back = document.getElementById('navback');
  var small = window.matchMedia('(max-width: 720px)');

  function isOpen() {
    return small.matches ? root.classList.contains('nav-open') : !root.classList.contains('nav-closed');
  }
  function sync() { toggle.setAttribute('aria-expanded', isOpen() ? 'true' : 'false'); }
  function closeDrawer() { root.classList.remove('nav-open'); sync(); }

  toggle.addEventListener('click', function () {
    if (small.matches) {
      root.classList.toggle('nav-open');
    } else {
      var closed = root.classList.toggle('nav-closed');
      try { localStorage.setItem('navclosed', closed ? '1' : '0'); } catch (e) { /* storage blocked */ }
    }
    sync();
  });
  if (back) { back.addEventListener('click', closeDrawer); }
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeDrawer(); } });
  nav.addEventListener('click', function (e) { if (small.matches && e.target.closest('a')) { closeDrawer(); } });
  small.addEventListener('change', closeDrawer);
  sync();
})();