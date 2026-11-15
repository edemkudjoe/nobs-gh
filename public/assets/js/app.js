// Small helpers for every page: password eye button and the menu button.
// Pages still work without this script.
(function () {
  'use strict';
  var root = document.documentElement;

  /* 0. Print with a plain file name. A page title with "|" or "&" can make "Save as PDF" fail on phones. */
  var origTitle = document.title;
  function cleanTitle() {
    var h = document.querySelector('.main h1, h1');
    var base = (h ? h.textContent : origTitle).replace(/[^A-Za-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'Page';
    return 'NOBS-' + base + '-' + new Date().toISOString().slice(0, 10);
  }
  window.addEventListener('beforeprint', function () { document.title = cleanTitle(); });
  window.addEventListener('afterprint', function () { document.title = origTitle; });
  window.nobsPrint = function () { document.title = cleanTitle(); window.print(); };

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
    var headText = document.createElement('div');
    headText.innerHTML = '<strong></strong><span></span>';
    headText.firstChild.textContent = 'National Organ & Blood Bank System: ' + pageTitle;
    headText.lastChild.textContent = 'Printed ' + stamp.toLocaleString() + (whoText ? ' by ' + whoText : '') + '. Contains confidential information.';
    var logoBox = tools.querySelector('.print-logos');
    if (logoBox) { head.appendChild(logoBox); }
    head.appendChild(headText);
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
    printBtn.textContent = 'Print';
    printBtn.addEventListener('click', function () { window.nobsPrint(); });
    // Download as a real PDF file (built in the browser, so it does not depend on the print dialog).
    var pdfBtn = document.createElement('button');
    pdfBtn.type = 'button';
    pdfBtn.className = 'btn';
    pdfBtn.textContent = 'Download PDF';
    var loadScript = function (src) {
      return new Promise(function (ok, fail) {
        var el = document.createElement('script');
        el.src = src; el.onload = ok; el.onerror = function () { fail(new Error('Could not load ' + src)); };
        document.head.appendChild(el);
      });
    };
    var logoData = function (img) {
      return new Promise(function (ok) {
        var i = new Image();
        i.onload = function () {
          var c = document.createElement('canvas');
          c.width = i.naturalWidth; c.height = i.naturalHeight;
          var g = c.getContext('2d'); g.fillStyle = '#fff'; g.fillRect(0, 0, c.width, c.height); g.drawImage(i, 0, 0);
          ok({ url: c.toDataURL('image/jpeg', 0.9), w: c.width, h: c.height });
        };
        i.onerror = function () { ok(null); };
        i.src = img.src;
      });
    };
    var makePdf = function () {
      var tables = Array.prototype.slice.call(tools.querySelectorAll('table:not(.no-tools)'));
      var wide = tables.some(function (t) { return t.querySelectorAll('thead th:not(.no-print)').length > 6; });
      var JsPdf = window.jspdf.jsPDF;
      var doc = new JsPdf({ orientation: wide ? 'landscape' : 'portrait', unit: 'pt', format: 'a4' });
      var pw = doc.internal.pageSize.getWidth(), ph = doc.internal.pageSize.getHeight(), m = 36;
      var imgs = Array.prototype.slice.call(head.querySelectorAll('.logos img'));
      return Promise.all(imgs.map(logoData)).then(function (logos) {
        var x = m;
        logos.forEach(function (l) {
          if (!l) { return; }
          var h = 34, w = h * l.w / l.h;
          doc.addImage(l.url, 'JPEG', x, m, w, h); x += w + 6;
        });
        var tx = x > m ? x + 8 : m;
        doc.setFont('helvetica', 'bold'); doc.setFontSize(14); doc.setTextColor(31, 58, 95);
        doc.text('National Organ & Blood Bank System', tx, m + 14);
        doc.setFont('helvetica', 'normal'); doc.setFontSize(11); doc.setTextColor(60);
        doc.text(pageTitle, tx, m + 30);
        doc.setFontSize(8.5); doc.setTextColor(110);
        var meta = 'Printed ' + stamp.toLocaleString() + (whoText ? ' by ' + whoText : '') + '. Contains confidential information.';
        doc.text(doc.splitTextToSize(meta, pw - tx - m), tx, m + 44);
        var y = m + 62;
        tables.forEach(function (table, n) {
          var skip = skipFor(table);
          var wrap = table.closest('.table-wrap');
          var prev = wrap && wrap.previousElementSibling;
          if (prev && prev.classList.contains('csv-btn')) { prev = prev.previousElementSibling; }
          var label = prev && /^H[23]$/.test(prev.tagName) ? prev.textContent.trim() : '';
          var headRow = Array.prototype.filter.call(table.querySelectorAll('thead th'), function (th, i) { return skip.indexOf(i) < 0; })
            .map(function (th) { return th.textContent.trim(); });
          var body = [];
          table.querySelectorAll('tbody tr').forEach(function (tr) {
            if (tr.querySelector('td[colspan]')) { return; }
            body.push(Array.prototype.filter.call(tr.children, function (td, i) { return skip.indexOf(i) < 0; })
              .map(function (td) { return (td.innerText || td.textContent).replace(/\s*\n\s*/g, ' / ').replace(/\s+/g, ' ').trim(); }));
          });
          if (label) {
            doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.setTextColor(31, 58, 95);
            if (y > ph - 90) { doc.addPage(); y = m; }
            doc.text(label, m, y + 8); y += 14;
          }
          doc.autoTable({
            head: [headRow], body: body, startY: y, margin: { left: m, right: m, bottom: 44 },
            styles: { font: 'helvetica', fontSize: 8.5, cellPadding: 4, overflow: 'linebreak' },
            headStyles: { fillColor: [31, 58, 95], textColor: 255 },
            alternateRowStyles: { fillColor: [244, 247, 250] }
          });
          y = doc.lastAutoTable.finalY + 22;
        });
        var pages = doc.internal.getNumberOfPages();
        for (var p = 1; p <= pages; p++) {
          doc.setPage(p); doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.setTextColor(120);
          doc.text('Page ' + p + ' of ' + pages, pw - m, ph - 20, { align: 'right' });
          doc.text('Confidential', m, ph - 20);
        }
        doc.save('NOBS-' + pageTitle.replace(/[^A-Za-z0-9]+/g, '-').replace(/^-|-$/g, '') + '-' + day + '.pdf');
      });
    };
    pdfBtn.addEventListener('click', function () {
      if (pdfBtn.disabled) { return; }
      pdfBtn.disabled = true; pdfBtn.textContent = 'Preparing PDF...';
      var ready = window.jspdf && window.jspdf.jsPDF && window.jspdf.jsPDF.API.autoTable
        ? Promise.resolve()
        : loadScript('/assets/js/vendor/jspdf.umd.min.js').then(function () { return loadScript('/assets/js/vendor/jspdf.plugin.autotable.min.js'); });
      ready.then(makePdf).catch(function () { alert('The PDF could not be made. Use Print instead.'); })
        .then(function () { pdfBtn.disabled = false; pdfBtn.textContent = 'Download PDF'; });
    });
    bar.appendChild(pdfBtn);
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
//