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

  /* 2b. Print / PDF and CSV for administrators and hospitals. */
  var tools = document.querySelector('.main[data-tools]');
  if (tools && tools.querySelector('table:not(.no-tools)')) {
    var h1 = tools.querySelector('h1');
    var pageTitle = h1 ? h1.textContent.trim() : document.title;
    var stamp = new Date();
    var day = stamp.toISOString().slice(0, 10);
    var whoEl = document.querySelector('.who');
    var whoText = whoEl ? whoEl.textContent.replace('Sign out', '').replace(/\s+/g, ' ').trim() : '';

    // Header that only shows on paper.
    var head = document.createElement('div');
    head.className = 'print-head';
    head.innerHTML = '<strong></strong><span></span>';
    head.firstChild.textContent = 'National Organ & Blood Bank System: ' + pageTitle;
    head.lastChild.textContent = 'Printed ' + stamp.toLocaleString() + (whoText ? ' by ' + whoText : '') + '. Contains confidential information.';
    tools.insertBefore(head, tools.firstChild);

    // Columns that only hold buttons or forms are left out of print and CSV.
    var skipFor = function (table) {
      var rows = Array.prototype.filter.call(table.querySelectorAll('tbody tr'), function (tr) { return !tr.querySelector('td[colspan]'); });
      var n = table.querySelectorAll('thead th').length;
      var skip = [];
      for (var i = 0; i < n; i++) {
        var allActions = rows.length > 0;
        rows.forEach(function (tr) {
          var td = tr.children[i];
          if (!td || !td.querySelector('form, button')) { allActions = false; }
        });
        if (allActions) { skip.push(i); }
      }
      skip.forEach(function (i) {
        var th = table.querySelectorAll('thead th')[i];
        if (th) { th.classList.add('no-print'); }
        rows.forEach(function (tr) { if (tr.children[i]) { tr.children[i].classList.add('no-print'); } });
      });
      return skip;
    };

    var csvCell = function (text) { return '"' + text.replace(/"/g, '""') + '"'; };
    var downloadCsv = function (table, skip, name) {
      var lines = [];
      var heads = Array.prototype.filter.call(table.querySelectorAll('thead th'), function (th, i) { return skip.indexOf(i) < 0; });
      lines.push(heads.map(function (th) { return csvCell(th.textContent.trim()); }).join(','));
      table.querySelectorAll('tbody tr').forEach(function (tr) {
        if (tr.querySelector('td[colspan]')) { return; }
        var cells = Array.prototype.filter.call(tr.children, function (td, i) { return skip.indexOf(i) < 0; });
        lines.push(cells.map(function (td) {
          var t = (td.innerText || td.textContent).replace(/\s*\n\s*/g, ' / ').replace(/\s+/g, ' ').trim();
          return csvCell(t);
        }).join(','));
      });
      var blob = new Blob(['\ufeff' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') + '-' + day + '.csv';
      document.body.appendChild(a);
      a.click();
      a.remove();
    };

    var bar = document.createElement('div');
    bar.className = 'tools';
    var printBtn = document.createElement('button');
    printBtn.type = 'button';
    printBtn.className = 'btn btn-quiet';
    printBtn.textContent = 'Print or save as PDF';
    printBtn.addEventListener('click', function () { window.print(); });
    bar.appendChild(printBtn);
    if (h1) { h1.insertAdjacentElement('afterend', bar); } else { tools.insertBefore(bar, tools.firstChild); }

    tools.querySelectorAll('table:not(.no-tools)').forEach(function (table, n) {
      var skip = skipFor(table);
      var prev = table.closest('.table-wrap');
      var label = prev && prev.previousElementSibling && /^H[23]$/.test(prev.previousElementSibling.tagName)
        ? prev.previousElementSibling.textContent.trim() : pageTitle + (n ? ' ' + (n + 1) : '');
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'btn btn-quiet btn-small csv-btn';
      b.textContent = 'Download CSV';
      b.setAttribute('aria-label', 'Download ' + label + ' as CSV');
      b.addEventListener('click', function () { downloadCsv(table, skip, label); });
      if (prev) { prev.parentNode.insertBefore(b, prev); }
    });
  }
  
    /* 3. Menu button for the public pages (phones). */
  var siteTop = document.getElementById('sitetop');
  var siteToggle = document.getElementById('sitetoggle');
  if (siteTop && siteToggle) {
    var setSite = function (open) {
      siteTop.classList.toggle('site-open', open);
      siteToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };
    siteToggle.addEventListener('click', function () { setSite(!siteTop.classList.contains('site-open')); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { setSite(false); } });
    document.addEventListener('click', function (e) { if (!siteTop.contains(e.target)) { setSite(false); } });
  }

  
  /* 4. Menu button for the signed-in pages. */
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
