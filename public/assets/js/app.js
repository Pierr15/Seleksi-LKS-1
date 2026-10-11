/* ==========================================================================
   ORBIT Building — inti frontend (vanilla JS, tanpa CDN)
   Menyediakan: tema, fetch + CSRF, toast, modal, drawer, konfirmasi.
   ========================================================================== */
(function () {
  'use strict';

  var body = document.body;
  var CSRF = body.getAttribute('data-csrf') || '';
  var ROLE = body.getAttribute('data-role') || '';

  /* ---------------- Tema ---------------- */
  var THEME_ORDER = ['system', 'light', 'dark'];
  var THEME_ICON = { system: 'system', light: 'sun', dark: 'moon' };

  function applyTheme(theme) {
    // Sinkronkan html DAN body — body juga membawa atribut data-theme dari
    // server, dan CSS variabel warna dideklarasikan pada elemen yang match,
    // sehingga isi halaman (di dalam body) mengikuti atribut body.
    document.documentElement.setAttribute('data-theme', theme);
    if (body) { body.setAttribute('data-theme', theme); }
    var btn = document.querySelector('[data-theme-toggle]');
    if (btn) {
      btn.innerHTML = orbitIcon(THEME_ICON[theme] || 'system');
      btn.setAttribute('title', 'Tema: ' + theme + ' (klik untuk ganti)');
    }
  }

  function cycleTheme() {
    var current = document.documentElement.getAttribute('data-theme') || 'system';
    var next = THEME_ORDER[(THEME_ORDER.indexOf(current) + 1) % THEME_ORDER.length];
    applyTheme(next);
    try { localStorage.setItem('orbit-theme', next); } catch (e) { /* abaikan */ }

    if (ROLE) {
      orbitFetch('/api/preferences', { method: 'PUT', body: { theme: next } })
        .catch(function () { /* preferensi lokal tetap berlaku */ });
    }
  }

  /* ---------------- Ikon (ringkas, dipakai toast/dinamis) ---------------- */
  function orbitIcon(name, size) {
    var paths = {
      check: '<path d="m5 13 4 4L19 7"/>',
      alert: '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>',
      info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
      x: '<path d="M6 6l12 12M18 6 6 18"/>',
      sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
      moon: '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/>',
      system: '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
      power: '<path d="M12 2v10"/><path d="M18.4 6.6a9 9 0 1 1-12.77.04"/>'
    };
    var s = size || 18;
    return '<svg width="' + s + '" height="' + s + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
      'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      (paths[name] || paths.info) + '</svg>';
  }

  /* ---------------- Toast ---------------- */
  function toast(message, type, ms) {
    var wrap = document.querySelector('.toast-wrap');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.className = 'toast-wrap';
      document.body.appendChild(wrap);
    }

    var el = document.createElement('div');
    el.className = 'toast ' + (type || 'info');
    var icon = type === 'success' ? 'check' : type === 'error' ? 'alert' : type === 'warn' ? 'alert' : 'info';
    el.innerHTML = '<span>' + orbitIcon(icon) + '</span><span class="msg"></span>' +
      '<button class="close" aria-label="Tutup">' + orbitIcon('x', 15) + '</button>';
    el.querySelector('.msg').textContent = message;
    el.querySelector('.close').addEventListener('click', function () { el.remove(); });
    wrap.appendChild(el);

    var ttl = ms === undefined ? 4200 : ms;
    if (ttl > 0) { setTimeout(function () { el.remove(); }, ttl); }

    return el;
  }

  /* ---------------- Fetch + CSRF ---------------- */
  function orbitFetch(url, opts) {
    opts = opts || {};
    var headers = { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' };
    var init = { method: opts.method || 'GET', headers: headers, credentials: 'same-origin' };

    if (opts.body !== undefined) {
      headers['Content-Type'] = 'application/json';
      init.body = JSON.stringify(opts.body);
    }

    return fetch(url, init).then(function (res) {
      var ct = res.headers.get('content-type') || '';
      var isJson = ct.indexOf('application/json') !== -1;

      return (isJson ? res.json() : Promise.resolve(null)).then(function (data) {
        if (res.status === 401 && url.indexOf('/api/') === 0) {
          window.location.href = '/login';
          return Promise.reject({ message: 'Sesi berakhir.', errors: {}, status: 401 });
        }

        if (!res.ok || (data && data.success === false)) {
          var err = {
            message: (data && data.message) || ('Permintaan gagal (HTTP ' + res.status + ').'),
            errors: (data && data.errors) || {},
            status: res.status
          };
          return Promise.reject(err);
        }

        return data;
      });
    }).catch(function (e) {
      if (e && e.message && e.errors !== undefined) { throw e; }
      throw { message: 'Tidak dapat terhubung ke server.', errors: {}, status: 0 };
    });
  }

  /* ---------------- Modal ---------------- */
  function openModal(id) {
    var root = document.getElementById(id);
    if (!root) { return; }
    root.classList.add('open');
    var focusable = root.querySelector('input, select, textarea, button');
    if (focusable) { focusable.focus(); }
  }

  function closeModal(id) {
    var root = typeof id === 'string' ? document.getElementById(id) : id;
    if (root) { root.classList.remove('open'); }
  }

  /* ---------------- Konfirmasi ---------------- */
  function confirmDialog(opts) {
    return new Promise(function (resolve) {
      var root = document.getElementById('confirm-modal');
      if (!root) {
        root = document.createElement('div');
        root.id = 'confirm-modal';
        root.className = 'modal-root';
        root.innerHTML =
          '<div class="modal-card" role="dialog" aria-modal="true">' +
          '<div class="card-header" id="confirm-title">Konfirmasi</div>' +
          '<div class="card-body"><p id="confirm-msg" class="mb-0"></p></div>' +
          '<div class="modal-actions">' +
          '<button class="btn btn-outline" data-confirm="no">Batal</button>' +
          '<button class="btn btn-danger" data-confirm="yes">Ya, lanjutkan</button>' +
          '</div></div>';
        document.body.appendChild(root);
      }

      root.querySelector('#confirm-title').textContent = opts.title || 'Konfirmasi';
      root.querySelector('#confirm-msg').textContent = opts.message || 'Lanjutkan?';
      var yesBtn = root.querySelector('[data-confirm="yes"]');
      yesBtn.textContent = opts.yesLabel || 'Ya, lanjutkan';
      yesBtn.className = 'btn ' + (opts.danger === false ? 'btn-primary' : 'btn-danger');
      root.classList.add('open');

      function done(ok) {
        root.classList.remove('open');
        yesBtn.removeEventListener('click', onYes);
        root.querySelector('[data-confirm="no"]').removeEventListener('click', onNo);
        resolve(ok);
      }
      function onYes() { done(true); }
      function onNo() { done(false); }

      yesBtn.addEventListener('click', onYes);
      root.querySelector('[data-confirm="no"]').addEventListener('click', onNo);
    });
  }

  /* ---------------- Field errors ---------------- */
  function showFieldErrors(form, errors) {
    clearFieldErrors(form);
    var first = null;

    Object.keys(errors || {}).forEach(function (key) {
      var field = form.querySelector('[name="' + key + '"]');
      var wrap = field ? field.closest('.field') : null;
      if (wrap) {
        wrap.classList.add('invalid');
        var msg = wrap.querySelector('.error-text');
        if (msg) { msg.textContent = errors[key]; }
        if (!first) { first = field; }
      } else {
        toast(errors[key], 'error');
      }
    });

    if (first) { first.focus(); }
  }

  function clearFieldErrors(form) {
    if (!form) { return; }
    form.querySelectorAll('.field.invalid').forEach(function (el) { el.classList.remove('invalid'); });
  }

  function formData(form) {
    var out = {};
    new FormData(form).forEach(function (v, k) { out[k] = v; });
    return out;
  }

  /* ---------------- Event global ---------------- */
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-theme-toggle]');
    if (t) { cycleTheme(); return; }

    var open = e.target.closest('[data-modal-open]');
    if (open) { openModal(open.getAttribute('data-modal-open')); return; }

    var close = e.target.closest('[data-modal-close]');
    if (close) { closeModal(close.getAttribute('data-modal-close') || close.closest('.modal-root')); return; }

    if (e.target.classList && e.target.classList.contains('modal-root')) {
      e.target.classList.remove('open');
      return;
    }

    var drawerBtn = e.target.closest('[data-drawer-toggle]');
    if (drawerBtn) {
      if (window.matchMedia('(min-width: 981px)').matches) {
        var rail = body.classList.toggle('rail');
        try { localStorage.setItem('orbit-sidebar', rail ? 'rail' : 'open'); } catch (e) { /* abaikan */ }
      } else {
        document.querySelector('.sidebar').classList.toggle('open');
        document.querySelector('.overlay').classList.toggle('show');
      }
      return;
    }

    if (e.target.classList && e.target.classList.contains('overlay')) {
      e.target.classList.remove('show');
      document.querySelector('.sidebar').classList.remove('open');
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-root.open').forEach(function (m) { m.classList.remove('open'); });
    }
  });

  /* ---------------- Toast dari flash server ---------------- */
  function bootFlash() {
    ['success', 'error'].forEach(function (key) {
      var el = document.querySelector('[data-flash="' + key + '"]');
      if (el && el.textContent.trim()) {
        toast(el.textContent.trim(), key === 'error' ? 'error' : 'success');
        el.remove();
      }
    });
  }

  /* ---------------- Boot ---------------- */
  var saved = null;
  try { saved = localStorage.getItem('orbit-theme'); } catch (e) { /* abaikan */ }
  applyTheme(saved || body.getAttribute('data-theme') || 'system');
  var savedSidebar = null;
  try { savedSidebar = localStorage.getItem('orbit-sidebar'); } catch (e) { /* abaikan */ }
  if (savedSidebar === 'rail') { body.classList.add('rail'); }
  document.addEventListener('DOMContentLoaded', bootFlash);
  if (document.readyState !== 'loading') { bootFlash(); }

  window.orbit = {
    fetch: orbitFetch,
    toast: toast,
    openModal: openModal,
    closeModal: closeModal,
    confirm: confirmDialog,
    showFieldErrors: showFieldErrors,
    clearFieldErrors: clearFieldErrors,
    formData: formData,
    csrf: CSRF,
    role: ROLE,
    icon: orbitIcon
  };
})();
