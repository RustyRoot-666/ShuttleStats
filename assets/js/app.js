/* ============================================================
   ShuttleStats — interaksi UI (vanilla JS)
   ============================================================ */
(function () {
  'use strict';

  var $ = function (sel, ctx) { return (ctx || document).querySelector(sel); };
  var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };

  function csrfToken() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  /* ---------- Toast ---------- */
  var toastRegion = null;
  function ensureToastRegion() {
    if (!toastRegion) {
      toastRegion = document.createElement('div');
      toastRegion.className = 'toast-region';
      document.body.appendChild(toastRegion);
    }
    return toastRegion;
  }
  window.ssToast = function (type, message, duration) {
    var region = ensureToastRegion();
    var el = document.createElement('div');
    el.className = 'toast t-' + (type || 'info');
    var icons = { success: '✓', error: '✕', warn: '!', info: 'i' };
    el.innerHTML = '<span style="font-weight:800">' + (icons[type] || 'i') + '</span><span>' + message + '</span>' +
      '<button class="t-close" aria-label="Tutup">×</button>';
    region.appendChild(el);
    var kill = function () {
      el.classList.add('is-leaving');
      setTimeout(function () { el.remove(); }, 260);
    };
    el.querySelector('.t-close').addEventListener('click', kill);
    setTimeout(kill, duration || 4200);
  };

  $$('[data-flash]').forEach(function (slot) {
    window.ssToast(slot.getAttribute('data-type') || 'info', slot.getAttribute('data-message') || '');
    slot.remove();
  });

  /* ---------- Sidebar (mobile) ---------- */
  var sidebarToggle = $('#sidebarToggle');
  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function () {
      document.body.classList.toggle('sidebar-open');
    });
  }
  var backdrop = $('#sidebarBackdrop');
  if (backdrop) {
    backdrop.addEventListener('click', function () {
      document.body.classList.remove('sidebar-open');
    });
  }

  /* ---------- Dropdown pemilih pertandingan ---------- */
  var switcher = $('#matchSwitcher');
  if (switcher) {
    var btn = $('#matchSwitcherBtn');
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      switcher.classList.toggle('is-open');
    });
    document.addEventListener('click', function (e) {
      if (!switcher.contains(e.target)) switcher.classList.remove('is-open');
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') switcher.classList.remove('is-open');
    });
  }

  /* ---------- Modal generik ---------- */
  function openModal(sel) {
    var m = $(sel);
    if (!m) return;
    m.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  }
  function closeModal(m) {
    m.classList.remove('is-open');
    document.body.style.overflow = '';
  }
  $$('[data-modal-open]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      e.preventDefault();
      openModal(el.getAttribute('data-modal-open'));
    });
  });
  $$('.modal-backdrop').forEach(function (mb) {
    mb.addEventListener('click', function (e) {
      if (e.target === mb) closeModal(mb);
    });
  });
  $$('[data-modal-close]').forEach(function (el) {
    el.addEventListener('click', function () {
      var mb = el.closest('.modal-backdrop');
      if (mb) closeModal(mb);
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') $$('.modal-backdrop.is-open').forEach(closeModal);
  });

  /* Confirm berbasis modal (promise) */
  var confirmState = { resolve: null };
  window.ssConfirm = function (title, message, okLabel) {
    var mb = $('#confirmModal');
    if (!mb) return Promise.resolve(window.confirm(message));
    $('#confirmTitle').textContent = title;
    $('#confirmMessage').textContent = message;
    $('#confirmOk').textContent = okLabel || 'Ya, lanjutkan';
    openModal('#confirmModal');
    if (confirmState.resolve) confirmState.resolve(false);
    return new Promise(function (resolve) { confirmState.resolve = resolve; });
  };
  (function bindConfirm() {
    var ok = $('#confirmOk'), cancel = $('#confirmCancel'), mb = $('#confirmModal');
    if (!ok) return;
    ok.addEventListener('click', function () { closeModal(mb); if (confirmState.resolve) confirmState.resolve(true); });
    cancel.addEventListener('click', function () { closeModal(mb); if (confirmState.resolve) confirmState.resolve(false); });
    mb.addEventListener('click', function (e) {
      if (e.target === mb) { if (confirmState.resolve) confirmState.resolve(false); }
    });
  })();

  /* ---------- Panggilan API ---------- */
  function api(action, formData) {
    formData.append('_csrf', csrfToken());
    return fetch('api.php?action=' + encodeURIComponent(action), {
      method: 'POST',
      headers: { 'X-CSRF-Token': csrfToken(), 'X-Requested-With': 'fetch' },
      body: formData,
      credentials: 'same-origin'
    }).then(function (r) {
      return r.json().catch(function () { throw new Error('Respon server tidak valid.'); });
    });
  }

  /* ---------- Hapus (entri / rally / pertandingan / semua data) ---------- */
  function bindAjaxDelete() {
    $$('[data-delete-entry]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-delete-entry');
        var name = btn.getAttribute('data-label') || ('entri #' + id);
        window.ssConfirm('Hapus entri?', 'Entri ' + name + ' akan dihapus permanen.', 'Hapus')
          .then(function (yes) {
            if (!yes) return;
            var fd = new FormData();
            fd.append('id', id);
            api('delete_entry', fd).then(function (res) {
              if (res.ok) { window.ssToast('success', res.message); var tr = btn.closest('tr'); if (tr) tr.remove(); }
              else window.ssToast('error', res.message);
            }).catch(function (err) { window.ssToast('error', err.message); });
          });
      });
    });

    $$('[data-delete-rally]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var fdInfo = JSON.parse(btn.getAttribute('data-delete-rally'));
        window.ssConfirm('Hapus rally penuh?',
          'Kedua baris (2 pemain) pada ' + fdInfo.label + ' akan dihapus.', 'Hapus rally')
          .then(function (yes) {
            if (!yes) return;
            var fd = new FormData();
            fd.append('match_id', fdInfo.match_id);
            fd.append('game', fdInfo.game);
            fd.append('rally_no', fdInfo.rally_no);
            api('delete_rally', fd).then(function (res) {
              if (res.ok) { window.ssToast('success', res.message); setTimeout(function () { window.location.reload(); }, 550); }
              else window.ssToast('error', res.message);
            }).catch(function (err) { window.ssToast('error', err.message); });
          });
      });
    });

    $$('[data-delete-match]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-delete-match');
        var name = btn.getAttribute('data-label') || '';
        window.ssConfirm('Hapus pertandingan?',
          'Pertandingan "' + name + '" beserta SELURUH data rally akan dihapus permanen.', 'Hapus permanen')
          .then(function (yes) {
            if (!yes) return;
            var fd = new FormData();
            fd.append('id', id);
            api('delete_match', fd).then(function (res) {
              if (res.ok) { window.ssToast('success', res.message); setTimeout(function () { window.location.href = res.redirect; }, 600); }
              else window.ssToast('error', res.message);
            }).catch(function (err) { window.ssToast('error', err.message); });
          });
      });
    });

    $$('[data-clear-entries]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-clear-entries');
        window.ssConfirm('Kosongkan semua data rally?',
          'Semua entri rally pertandingan ini akan dihapus. Metadata pertandingan tetap ada.', 'Kosongkan')
          .then(function (yes) {
            if (!yes) return;
            var fd = new FormData();
            fd.append('id', id);
            api('clear_entries', fd).then(function (res) {
              if (res.ok) { window.ssToast('success', res.message); setTimeout(function () { window.location.reload(); }, 550); }
              else window.ssToast('error', res.message);
            }).catch(function (err) { window.ssToast('error', err.message); });
          });
      });
    });
  }
  bindAjaxDelete();

  /* ---------- Counter stepper ---------- */
  function bindCounters(ctx) {
    $$('.counter', ctx || document).forEach(function (c) {
      if (c.dataset.bound) return;
      c.dataset.bound = '1';
      var minus = $('[data-minus]', c);
      var plus = $('[data-plus]', c);
      var input = $('input', c);
      var step = function (dir) {
        var v = parseInt(input.value || '0', 10) || 0;
        v = Math.max(0, Math.min(999, v + dir));
        input.value = v;
        input.dispatchEvent(new Event('change', { bubbles: true }));
      };
      minus.addEventListener('click', function () { step(-1); });
      plus.addEventListener('click', function () { step(1); });
      input.addEventListener('change', function () {
        var v = parseInt(input.value || '0', 10);
        if (isNaN(v) || v < 0) v = 0;
        input.value = Math.min(999, v);
      });
    });
  }
  bindCounters();
  window.ssBindCounters = bindCounters;

  /* ---------- Toolbar filter auto-submit ---------- */
  $$('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () {
      el.form && el.form.submit();
    });
  });
  var debouncedSearch = null;
  $$('[data-debounce-search]').forEach(function (input) {
    input.addEventListener('input', function () {
      clearTimeout(debouncedSearch);
      debouncedSearch = setTimeout(function () { input.form && input.form.submit(); }, 650);
    });
  });

  /* ---------- Count-up angka statistik ---------- */
  $$('[data-countup]').forEach(function (el) {
    var target = parseFloat(el.getAttribute('data-countup')) || 0;
    var dec = (el.getAttribute('data-decimals') || '0') === '1' ? 1 : 0;
    var t0 = null;
    function frame(t) {
      if (!t0) t0 = t;
      var p = Math.min(1, (t - t0) / 650);
      var eased = 1 - Math.pow(1 - p, 3);
      var val = target * eased;
      el.textContent = dec
        ? val.toFixed(1).replace('.', ',')
        : Math.round(val).toLocaleString('id-ID');
      if (p < 1) requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);
  });

  /* ============================================================
     Halaman INPUT RALLY
     ============================================================ */
  var rallyForm = $('#rallyForm');
  if (rallyForm) {
    var updatePanelTotals = function () {
      $$('.player-panel', rallyForm).forEach(function (panel) {
        var total = 0;
        $$('input[type="number"]', panel).forEach(function (i) { total += parseInt(i.value || '0', 10) || 0; });
        var out = $('.pp-total strong', panel);
        if (out) out.textContent = total;
      });
    };
    rallyForm.addEventListener('change', function (e) {
      if (e.target.matches('input[type="number"]')) updatePanelTotals();
    });
    updatePanelTotals();

    // servis otomatis: hanya pemain servis yang boleh > 0? — biarkan bebas,
    // namun bantu: klik tombol "Servis" cepat pada panel.
    $$('[data-quick-serve]', rallyForm).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var panel = document.getElementById(btn.getAttribute('data-quick-serve'));
        var input = panel ? panel.querySelector('input[name$="[t_servis]"]') : null;
        if (input) {
          input.value = Math.min(999, (parseInt(input.value || '0', 10) || 0) + 1);
          input.dispatchEvent(new Event('change', { bubbles: true }));
        }
      });
    });

    var submitBtn = $('#rallySubmit');
    var resetBtn = $('#rallyReset');
    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        $$('input[type="number"]', rallyForm).forEach(function (i) { i.value = 0; });
        $$('select[name="det_technique"], select[name="position"]', rallyForm).forEach(function (s) { s.value = ''; });
        $$('input[name="result_type"]', rallyForm).forEach(function (r) { r.checked = false; });
        var f = $('#detPlayerP1'); if (f) f.checked = true;
        updatePanelTotals();
        var firstCounter = $('#rallyForm .counter input');
        if (firstCounter) firstCounter.focus();
      });
    }
    rallyForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(rallyForm);
      if (submitBtn) submitBtn.classList.add('loading');
      api('save_rally', fd).then(function (res) {
        if (submitBtn) submitBtn.classList.remove('loading');
        if (!res.ok) { window.ssToast('error', res.message); return; }
        window.ssToast('success', res.message);
        // reset & lanjut ke rally berikutnya
        $$('input[type="number"]', rallyForm).forEach(function (i) { i.value = 0; });
        var rallyInput = $('#rallyNoInput');
        if (rallyInput) rallyInput.value = res.next_rally_no;
        $$('select[name="det_technique"], select[name="position"]', rallyForm).forEach(function (s) { s.value = ''; });
        $$('input[name="result_type"]', rallyForm).forEach(function (r) { r.checked = false; });
        var f = $('#detPlayerP1'); if (f) f.checked = true;
        updatePanelTotals();
        loadRecent(res.recent_html);
        var firstCounter = $('#rallyForm .counter input');
        if (firstCounter) firstCounter.focus();
      }).catch(function (err) {
        if (submitBtn) submitBtn.classList.remove('loading');
        window.ssToast('error', err.message);
      });
    });

    // Ctrl+Enter untuk simpan
    document.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && document.activeElement && rallyForm.contains(document.activeElement)) {
        rallyForm.dispatchEvent(new Event('submit'));
      }
    });
  }

  function loadRecent(html) {
    var wrap = $('#recentEntriesWrap');
    if (!wrap || typeof html !== 'string') return;
    wrap.innerHTML = html;
    bindAjaxDelete();
  }

  /* ============================================================
     Halaman DATA — modal edit entri
     ============================================================ */
  var editModal = $('#editModal');
  if (editModal) {
    $$('[data-edit-entry]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var data = JSON.parse(btn.getAttribute('data-edit-entry'));
        $('#editId').value = data.id;
        $('#editGame').value = data.game;
        $('#editRallyNo').value = data.rally_no;
        $('#editPlayerNum').value = data.player_num;
        techCols.forEach(function (col) {
          var input = $('#edit_' + col);
          if (input) input.value = data[col];
        });
        $('#edit_det_technique').value = data.det_technique || '';
        $('#edit_result_type').value = data.result_type || '';
        $('#edit_position').value = data.position || '';
        openModal('#editModal');
      });
    });

    var editForm = $('#editForm');
    editForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(editForm);
      api('update_entry', fd).then(function (res) {
        if (!res.ok) { window.ssToast('error', res.message); return; }
        closeModal(editModal);
        window.ssToast('success', res.message);
        setTimeout(function () { window.location.reload(); }, 550);
      }).catch(function (err) { window.ssToast('error', err.message); });
    });
  }

  /* Daftar kolom teknik — diinjeksi dari server */
  var techCols = window.SS_TECH_COLS || [];

  /* ============================================================
     Dashboard — grafik Chart.js
     ============================================================ */
  if (window.SS_DASH && typeof Chart !== 'undefined') {
    Chart.defaults.font.family = "'Segoe UI', system-ui, Roboto, sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#5A6B60';

    var C = { p1: '#2F6FEB', p2: '#F97316', ws: '#22A55B', ue: '#E15B5B', fe: '#E8A23C', grid: '#EDF2ED' };
    var d = window.SS_DASH;

    function hbar(id, labels, s1, s2, height) {
      var el = document.getElementById(id);
      if (!el) return;
      el.parentNode.style.height = height + 'px';
      new Chart(el, {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [
            { label: d.n1, data: s1, backgroundColor: C.p1, borderRadius: 4, barPercentage: .78, categoryPercentage: .72 },
            { label: d.n2, data: s2, backgroundColor: C.p2, borderRadius: 4, barPercentage: .78, categoryPercentage: .72 }
          ]
        },
        options: {
          indexAxis: 'y', maintainAspectRatio: false, responsive: true,
          animation: { duration: 650 },
          plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 16 } } },
          scales: {
            x: { grid: { color: C.grid }, ticks: { precision: 0 } },
            y: { grid: { display: false } }
          }
        }
      });
    }

    hbar('chartTech', d.labels, d.shots1, d.shots2, Math.max(320, d.labels.length * 26 + 90));
    hbar('chartPenentu', d.labels, d.penentu1, d.penentu2, Math.max(300, d.labels.length * 26 + 90));

    ['1', '2'].forEach(function (p) {
      var el = document.getElementById('chartHasilP' + p);
      if (!el) return;
      var data = p === '1' ? d.hasil1 : d.hasil2;
      new Chart(el, {
        type: 'doughnut',
        data: {
          labels: ['Winning Shot', 'Unforced Error', 'Forced Error'],
          datasets: [{
            data: [data[0], data[1], data[2]],
            backgroundColor: [C.ws, C.ue, C.fe],
            borderWidth: 3, borderColor: '#fff', hoverOffset: 7
          }]
        },
        options: {
          maintainAspectRatio: false, cutout: '64%',
          plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 14 } } }
        }
      });
    });

    var elBabak = document.getElementById('chartBabak');
    if (elBabak) {
      new Chart(elBabak, {
        type: 'bar',
        data: {
          labels: d.babakLabels,
          datasets: [
            { label: d.n1, data: d.babakP1, backgroundColor: C.p1, borderRadius: 6, maxBarThickness: 54 },
            { label: d.n2, data: d.babakP2, backgroundColor: C.p2, borderRadius: 6, maxBarThickness: 54 }
          ]
        },
        options: {
          maintainAspectRatio: false,
          plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 16 } } },
          scales: {
            x: { grid: { display: false } },
            y: { grid: { color: C.grid }, ticks: { precision: 0 }, title: { display: true, text: 'Poin', font: { size: 11 } } }
          }
        }
      });
    }

    var elPos = document.getElementById('chartPosisi');
    if (elPos) {
      elPos.parentNode.style.height = '240px';
      new Chart(elPos, {
        type: 'bar',
        data: {
          labels: d.posisiLabels,
          datasets: [
            { label: d.n1, data: d.posisi1, backgroundColor: C.p1, borderRadius: 4 },
            { label: d.n2, data: d.posisi2, backgroundColor: C.p2, borderRadius: 4 }
          ]
        },
        options: {
          indexAxis: 'y', maintainAspectRatio: false,
          plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 16 } } },
          scales: { x: { stacked: false, grid: { color: C.grid }, ticks: { precision: 0 } }, y: { grid: { display: false } } }
        }
      });
    }
  }

})();
