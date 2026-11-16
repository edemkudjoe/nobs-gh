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

/* 5. Public pages: motion and small interactions.
   Everything here is optional. The pages work the same without it. */
(function () {
  'use strict';
  if (!document.body.classList.contains('site')) { return; }
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var hasIO = 'IntersectionObserver' in window;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var pad = function (n) { return (n < 10 ? '0' : '') + n; };

  /* 5a. Scroll progress bar and header shadow. */
  var top = $('#sitetop'), prog = $('#prog');
  function onScroll() {
    var h = document.documentElement, max = h.scrollHeight - h.clientHeight;
    if (prog) { prog.style.setProperty('--p', max > 0 ? Math.min(1, window.scrollY / max).toFixed(4) : '0'); }
    if (top) { top.classList.toggle('scrolled', window.scrollY > 4); }
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll);
  onScroll();

  /* 5b. The first section slides in. Later sections fade in as they scroll into view. */
  var secs = $$('.site-main > .sec');
  if (!reduce && hasIO) {
    var seen = new IntersectionObserver(function (list) {
      list.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('in'); seen.unobserve(en.target); }
      });
    }, { threshold: 0.05, rootMargin: '0px 0px -30px 0px' });
    secs.forEach(function (sec, i) {
      $$('.wrap > *', sec).forEach(function (el, k) {
        if (i === 0) { el.classList.add('rise'); el.style.setProperty('--d', (k * 0.12) + 's'); }
        else { el.classList.add('rv'); seen.observe(el); }
      });
    });
  }

  /* 5c. Home hero: the example countdowns tick and the example booking cycles. Sample numbers only. */
  if (!reduce) {
    $$('.pc .mono').forEach(function (el) {
      var txt = el.textContent.trim();
      var t = /^(\d\d):(\d\d):(\d\d)$/.exec(txt);
      if (t) {
        var start = (+t[1]) * 3600 + (+t[2]) * 60 + (+t[3]), left = start;
        setInterval(function () {
          left = left > 0 ? left - 1 : start;
          el.textContent = pad(Math.floor(left / 3600)) + ':' + pad(Math.floor(left % 3600 / 60)) + ':' + pad(left % 60);
        }, 1000);
        return;
      }
      var s = /^(\d+) of (\d+)$/.exec(txt);
      if (s) {
        var first = +s[1], free = first, total = s[2];
        setInterval(function () {
          free = free > 1 ? free - 1 : first;
          el.textContent = free + ' of ' + total;
          el.classList.remove('flash'); void el.offsetWidth; el.classList.add('flash');
        }, 3500);
      }
    });
  }

  /* 5d. Step lists (home, how it works): the active step moves on by itself.
     Hover pauses it. Click or Enter pins a step. Click it again to let it run. */
  $$('.steps').forEach(function (list) {
    var items = Array.prototype.slice.call(list.children), cur = 0;
    if (items.length < 2) { return; }
    function show(i, pin) {
      cur = i;
      items.forEach(function (it) { it.classList.remove('on'); it.removeAttribute('aria-current'); });
      void list.offsetWidth;
      items[i].classList.add('on');
      items[i].setAttribute('aria-current', 'step');
      list.classList.toggle('pin', !!pin);
    }
    items.forEach(function (it, k) {
      it.setAttribute('tabindex', '0');
      var choose = function () { show(k, !(list.classList.contains('pin') && cur === k)); };
      it.addEventListener('click', choose);
      it.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); choose(); }
      });
    });
    list.addEventListener('animationend', function (e) {
      if (e.animationName === 'stepfill' && !list.classList.contains('pin')) { show((cur + 1) % items.length, false); }
    });
    show(0, false);
    if (hasIO) {
      new IntersectionObserver(function (l) { list.classList.toggle('idle', !l[0].isIntersecting); }).observe(list);
    }
  });

  /* 5e. Home: the working view. Menu items switch the sample view, and the numbers count up. All sample data. */
  var win = $('.window.big');
  if (win) {
    var VIEWS = {
      'Dashboard': ['Here is the system at a glance.', [['Pending verification', 12], ['Open organ offers', 3], ['Groups low or out', 2], ['Expiring in 7 days', 9]], [['c', 'Critical', '4 units O-', '1 h 12 min'], ['u', 'Urgent', 'Heart, A+, new waiting-list entry', '3 d 4 h'], ['r', 'Routine', '2 units B+', '5 h 40 min']]],
      'Blood requests': ['Requests from hospitals.', [['Waiting for verification', 5], ['Verified today', 8], ['Partly offered', 2], ['Fulfilled this week', 14]], [['c', 'Critical', '4 units O-', '1 h 12 min'], ['u', 'Urgent', '3 units A+', '2 h 30 min'], ['r', 'Routine', '2 units B+', '5 h 40 min']]],
      'Blood offers': ['Units offered and waiting for an answer.', [['Open offers', 6], ['Answered today', 9], ['Returned to stock', 1], ['Units reserved', 17]], [['c', 'Critical', '2 units O- offered', '38 min left'], ['u', 'Urgent', '3 units A+ offered', '1 h 05 min left'], ['r', 'Routine', '4 units B+ offered', '19 h left']]],
      'Waiting list': ['Patients waiting for an organ, shown by code.', [['Pending verification', 12], ['Verified', 48], ['Kidney', 31], ['Heart', 4]], [['c', 'Critical', 'P-0302, liver, O+', '9 d 2 h'], ['u', 'Urgent', 'P-0231, heart, A+', '3 d 4 h'], ['r', 'Routine', 'P-0187, kidney, B+', '41 d']]],
      'Organ offers': ['Organs offered, ranked and on a deadline.', [['Open offers', 3], ['Awaiting reply', 2], ['Passed to next', 1], ['Overrides this month', 0]], [['c', 'Critical', 'Kidney O+ to P-0231', '2 h 41 min left'], ['u', 'Urgent', 'Liver A+ to P-0302', '5 h left'], ['r', 'Routine', 'Kidney B+ to P-0187', '7 h left']]],
      'Inventory': ['Blood stock by group.', [['Groups low or out', 2], ['Expiring in 7 days', 9], ['In testing', 21], ['Available', 214]], [['c', 'Out', 'O-', '0 units'], ['u', 'Low', 'B-', '3 units'], ['r', 'Healthy', 'A+', '48 units']]]
    };
    var main2 = $('.main2', win), vt = $('.main2 > .t', win), vs = $('.main2 > .s', win), vq = $('.main2 > .q', win);
    var qHead = vq ? vq.textContent : '';
    var tiles = $$('.tile', win), rows = $$('.qrow', win), menu = $$('.menu div', win);

    var countUp = function (el, to) {
      if (reduce) { el.textContent = to; return; }
      var t0 = null;
      (function step(t) {
        if (t0 === null) { t0 = t; }
        var p = Math.min(1, (t - t0) / 900);
        el.textContent = Math.round(to * (1 - Math.pow(1 - p, 3)));
        if (p < 1) { requestAnimationFrame(step); }
      })(performance.now());
    };
    var render = function (name, animate) {
      var v = VIEWS[name];
      if (!v) { return; }
      vt.textContent = name;
      vs.textContent = v[0];
      if (vq) { vq.textContent = name === 'Inventory' ? 'Stock by group, lowest first' : qHead; }
      tiles.forEach(function (tl, i) {
        $('.label', tl).textContent = v[1][i][0];
        if (animate) { countUp($('.value', tl), v[1][i][1]); } else { $('.value', tl).textContent = v[1][i][1]; }
      });
      rows.forEach(function (r, i) {
        var d = v[2][i], b = $('b', r);
        $('.dot', b).className = 'dot ' + d[0];
        b.lastChild.textContent = d[1];
        $('.d', r).textContent = d[2];
        r.lastElementChild.textContent = d[3];
      });
      menu.forEach(function (m) {
        var on = m.textContent.trim() === name;
        m.classList.toggle('on', on);
        if (on) { m.setAttribute('aria-current', 'true'); } else { m.removeAttribute('aria-current'); }
      });
      if (animate && !reduce) { main2.classList.remove('swap'); void main2.offsetWidth; main2.classList.add('swap'); }
    };
    menu.forEach(function (m) {
      m.setAttribute('tabindex', '0');
      var go = function () { render(m.textContent.trim(), true); };
      m.addEventListener('click', go);
      m.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
    });
    if (hasIO && !reduce) {
      tiles.forEach(function (tl) { $('.value', tl).textContent = '0'; });
      new IntersectionObserver(function (l, o) {
        if (l[0].isIntersecting) { render('Dashboard', true); o.disconnect(); }
      }, { threshold: 0.35 }).observe(win);
    }
  }

  /* 5f. Features: choose a role to see only its tools. */
  var chipBox = $('#chips');
  if (chipBox) {
    chipBox.hidden = false;
    var groups = $$('.fgroup'), cta = $('.site-main > .sec:last-child');
    chipBox.addEventListener('click', function (e) {
      var b = e.target.closest('.chip'), n = 0, f;
      if (!b) { return; }
      f = b.getAttribute('data-filter');
      $$('.chip', chipBox).forEach(function (c) { c.setAttribute('aria-pressed', c === b ? 'true' : 'false'); });
      groups.forEach(function (g) {
        var show = f === 'all' || g.getAttribute('data-role') === f;
        g.hidden = !show;
        if (show) {
          g.classList.toggle('grey', n % 2 === 1);
          n++;
          if (!reduce) { g.classList.remove('pop'); void g.offsetWidth; g.classList.add('pop'); }
        }
      });
      if (cta) { cta.classList.toggle('grey', n % 2 === 1); }
    });
  }

  /* 5g. About: the example record plays one row at a time. */
  var rec = $('.record');
  if (rec && !reduce) {
    var recRows = $$('.row', rec), replay = $('#replay'), timers = [];
    var play = function () {
      timers.forEach(clearTimeout);
      timers = [];
      recRows.forEach(function (r) { r.classList.remove('show'); r.classList.add('hold'); });
      recRows.forEach(function (r, k) {
        timers.push(setTimeout(function () { r.classList.remove('hold'); r.classList.add('show'); }, 400 + k * 1000));
      });
    };
    if (replay) { replay.hidden = false; replay.addEventListener('click', play); }
    if (hasIO) {
      new IntersectionObserver(function (l, o) {
        if (l[0].isIntersecting) { play(); o.disconnect(); }
      }, { threshold: 0.3 }).observe(rec);
    }
  }

  /* 5h. Sign in: the line under the headline changes, the button shows it is working, and a bad email is flagged. */
  var card = $('form.auth-card');
  if (card) {
    var line = $('.auth-brand p');
    if (line && !reduce) {
      var lines = [line.textContent, 'Sign in to manage stock and test every unit.', 'Sign in to respond to an offer before its deadline.'], li = 0;
      setInterval(function () {
        line.classList.add('fade');
        setTimeout(function () { li = (li + 1) % lines.length; line.textContent = lines[li]; line.classList.remove('fade'); }, 400);
      }, 4500);
    }
    var go = $('button[type="submit"]', card), label = go ? go.textContent : '';
    card.addEventListener('submit', function () {
      if (!go) { return; }
      go.classList.add('busy');
      go.textContent = 'Signing in\u2026';
      setTimeout(function () { go.disabled = true; }, 0);
    });
    window.addEventListener('pageshow', function (e) {
      if (e.persisted && go) { go.disabled = false; go.classList.remove('busy'); go.textContent = label; }
    });
    var em = $('#email');
    if (em) {
      var hint = document.createElement('div');
      hint.className = 'hint bad';
      hint.setAttribute('aria-live', 'polite');
      em.parentNode.appendChild(hint);
      em.addEventListener('blur', function () {
        hint.textContent = em.value && !em.validity.valid ? 'Enter a valid email address, such as name@facility.example.' : '';
      });
      em.addEventListener('input', function () { hint.textContent = ''; });
    }
  }
})();