/* ==========================================================================
   Denah gedung — render dari database + mode edit posisi (Admin).
   Koordinat perangkat: persen 0–100 relatif AREA RUANGAN.
   ========================================================================== */
(function () {
  'use strict';

  var CFG = window.ORBIT_DENAH || { initialFloor: 1, isAdmin: false, canControl: false };
  var O = window.orbit;

  var state = {
    floorId: CFG.initialFloor,
    floorName: '',
    rooms: [],
    devices: [],
    counts: { placed: 0, unplaced: 0, total: 0 },
    editMode: false,
    selectedId: null,
    pending: {},   // deviceId -> { position_x, position_y }
    dirty: false
  };

  var el = {
    denah: document.getElementById('denah'),
    list: document.getElementById('device-list'),
    counts: document.getElementById('device-counts'),
    loading: document.getElementById('denah-loading'),
    tabs: document.getElementById('floor-tabs'),
    editorBar: document.getElementById('editor-bar'),
    dirtyStatus: document.getElementById('dirty-status'),
    btnSave: document.getElementById('btn-save'),
    detail: document.getElementById('device-detail'),
    detailBody: document.getElementById('device-detail-body'),
    form: document.getElementById('position-form'),
    posRoom: document.getElementById('pos-room'),
    posX: document.getElementById('pos-x'),
    posY: document.getElementById('pos-y')
  };

  var ICONS = {
    ac: 'system', ac_central: 'system', light: 'sun', computer: 'cpu',
    projector: 'eye', videotron: 'eye', audio: 'zap', exhaust_fan: 'wind',
    studio_lighting: 'sun', other: 'cpu'
  };

  function icon(name, size) {
    var s = size || 15;
    var paths = {
      wind: '<path d="M3 8h10a3 3 0 1 0-3-3"/><path d="M3 12h14a3 3 0 1 1-3 3"/><path d="M3 16h7a2.5 2.5 0 1 1-2.5 2.5"/>'
    };
    if (paths[name]) {
      return '<svg width="' + s + '" height="' + s + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
        'stroke-width="1.8" stroke-linecap="round">' + paths[name] + '</svg>';
    }
    return O.icon(name, s);
  }

  /* ------------------------------------------------ Load & render -------- */

  function loadFloor(id) {
    state.floorId = id;
    el.loading.textContent = 'Memuat denah…';

    return O.fetch('/api/floors/' + id).then(function (res) {
      var data = res.data;
      state.floorName = data.floor.name;
      state.rooms = data.rooms;
      state.devices = data.devices;
      state.counts = data.counts;
      render();
      el.loading.textContent = data.floor.name + ' · ' + data.rooms.length + ' ruangan';
    }).catch(function (err) {
      el.loading.textContent = 'Gagal memuat: ' + err.message;
      O.toast(err.message, 'error');
    });
  }

  function roomClass(room) {
    if (room.mode === 'inactive') { return 'room-box inactive'; }
    if (room.mode === 'standby') { return 'room-box standby'; }
    if (room.occupancy === 'vacant') { return 'room-box vacant'; }
    return 'room-box';
  }

  function render() {
    el.denah.innerHTML = '';

    state.rooms.forEach(function (room) {
      var box = document.createElement('div');
      box.className = roomClass(room);
      box.dataset.roomId = room.id;
      box.style.left = num(room.map_x) + '%';
      box.style.top = num(room.map_y) + '%';
      box.style.width = num(room.map_w) + '%';
      box.style.height = num(room.map_h) + '%';

      box.innerHTML =
        '<span class="room-name">' + esc(room.name) + '</span>' +
        '<span class="room-meta">' + room.placed_count + '/' + room.device_count + ' alat</span>' +
        sensorMiniHtml(room);

      el.denah.appendChild(box);
    });

    state.devices.forEach(function (device) {
      if (device.position_x === null || device.position_y === null) { return; }
      var box = el.denah.querySelector('.room-box[data-room-id="' + device.room_id + '"]');
      if (!box) { return; }
      box.appendChild(buildDot(device));
    });

    renderList();
    renderCounts();
    restorePendingDots();
  }

  function buildDot(device) {
    var dot = document.createElement('div');
    var power = device.power_state || 'unknown';
    dot.className = 'device-dot ' + (power === 'on' ? 'on' : power === 'off' ? 'off' : power === 'sleep' ? 'sleep' : '') +
      (device.inventory_status === 'needs_verification' ? ' needs-verify' : '') +
      (String(state.selectedId) === String(device.id) ? ' selected' : '');
    dot.dataset.deviceId = device.id;
    dot.style.left = num(device.position_x) + '%';
    dot.style.top = num(device.position_y) + '%';
    dot.title = device.name + ' · ' + (powerLabel(power));
    dot.innerHTML =
      '<span class="bubble">' + O.icon(ICONS[device.device_type] || 'cpu', 15) + '</span>' +
      '<span class="dlabel">' + esc(device.name) + '</span>';
    return dot;
  }

  function renderList() {
    if (state.devices.length === 0) {
      el.list.innerHTML = '<div class="empty">Tidak ada perangkat di lantai ini.</div>';
      return;
    }

    el.list.innerHTML = '';
    state.devices.forEach(function (device) {
      var pending = state.pending[device.id];
      var placed = pending ? (pending.position_x !== null) : (device.position_x !== null);
      var item = document.createElement('div');
      item.className = 'side-item' + (String(state.selectedId) === String(device.id) ? ' selected' : '');
      item.dataset.deviceId = device.id;
      item.innerHTML =
        '<span class="bubble" style="width:26px;height:26px;border-radius:50%;display:grid;place-items:center;' +
        'border:2px solid ' + (device.power_state === 'on' ? 'var(--success)' : device.power_state === 'off' ? 'var(--danger)' : 'var(--muted)') + '">' +
        O.icon(ICONS[device.device_type] || 'cpu', 14) + '</span>' +
        '<span class="grow"><span class="name">' + esc(device.name) + '</span>' +
        '<div class="meta">' + esc(device.room_name || '—') +
        (device.inventory_status === 'needs_verification' ? ' · perlu verifikasi' : '') + '</div></span>' +
        (placed
          ? '<span class="badge badge-success plain" title="Sudah ditempatkan">x,y</span>'
          : '<span class="badge badge-warn plain">belum</span>') +
        cmdQuickButton(device);
      el.list.appendChild(item);
    });
  }

  function renderCounts() {
    var placed = state.counts.placed, unplaced = state.counts.unplaced;
    Object.keys(state.pending).forEach(function (id) {
      var p = state.pending[id];
      var dev = findDevice(id);
      if (!dev) { return; }
      var was = dev.position_x !== null;
      var now = p.position_x !== null;
      if (was && !now) { placed--; unplaced++; }
      if (!was && now) { placed++; unplaced--; }
    });
    el.counts.textContent = placed + ' ditempatkan · ' + unplaced + ' belum';
  }

  function restorePendingDots() {
    Object.keys(state.pending).forEach(function (id) {
      var p = state.pending[id];
      var dot = el.denah.querySelector('.device-dot[data-device-id="' + id + '"]');
      if (p.position_x === null) {
        if (dot) { dot.remove(); }
        return;
      }
      if (!dot) {
        var dev = findDevice(id);
        if (dev) {
          var box = el.denah.querySelector('.room-box[data-room-id="' + dev.room_id + '"]');
          if (box) { dot = buildDot(dev); box.appendChild(dot); }
        }
      }
      if (dot) {
        dot.style.left = p.position_x + '%';
        dot.style.top = p.position_y + '%';
      }
    });
  }

  /* ------------------------------------------------ Selection ------------- */

  function findDevice(id) {
    for (var i = 0; i < state.devices.length; i++) {
      if (String(state.devices[i].id) === String(id)) { return state.devices[i]; }
    }
    return null;
  }

  function canCommand(device) {
    return !!device
      && CFG.canControl
      && device.control_capability === 'controllable'
      && String(device.is_active) === '1'
      && !device.archived_at;
  }

  function cmdQuickButton(device) {
    if (!canCommand(device)) { return ''; }
    var next = device.power_state === 'on' ? 'off' : 'on';
    var title = next === 'on' ? 'Nyalakan ' : 'Matikan ';
    var color = device.power_state === 'on' ? 'var(--danger)' : 'var(--success)';
    return '<button class="cmd-quick" type="button" data-cmd-device="' + device.id + '" data-cmd="' + next + '"'
      + ' title="' + esc(title + device.name) + '" style="color:' + color + '">'
      + O.icon('power', 15) + '</button>';
  }

  function setCmdBusy(id, busy) {
    document.querySelectorAll('[data-cmd-device="' + id + '"]').forEach(function (b) {
      b.disabled = busy;
    });
  }

  function sendCommand(id, cmd) {
    var device = findDevice(id);
    if (!canCommand(device)) { return; }

    var CMD_LABELS = { on: 'nyalakan', off: 'matikan', sleep: 'masukkan mode sleep', shutdown: 'shutdown (matikan total)' };
    var label = CMD_LABELS[cmd] || cmd;
    O.confirm({
      title: 'Kirim perintah?',
      message: device.name + ' akan ' + label + '.',
      yesLabel: 'Kirim'
    }).then(function (ok) {
      if (!ok) { return; }
      setCmdBusy(id, true);

      O.fetch('/api/devices/' + id + '/command', {
        method: 'POST',
        body: { command: cmd }
      }).then(function (res) {
        device.power_state = res.data.state.power_state;
        device.connection_state = res.data.state.connection_state;
        render();
        if (String(state.selectedId) === String(id)) { select(id); }
        O.toast(res.message, 'success');
      }).catch(function (err) {
        O.toast(err.message, 'error');
        setCmdBusy(id, false);
      });
    });
  }

  function select(id) {
    state.selectedId = id;

    el.denah.querySelectorAll('.device-dot').forEach(function (d) {
      d.classList.toggle('selected', String(d.dataset.deviceId) === String(id));
    });
    el.list.querySelectorAll('.side-item').forEach(function (d) {
      d.classList.toggle('selected', String(d.dataset.deviceId) === String(id));
    });

    var device = findDevice(id);
    if (!device) {
      el.detail.hidden = true;
      el.form.hidden = true;
      return;
    }

    el.detail.hidden = false;
    var pending = state.pending[id];
    var x = pending ? pending.position_x : device.position_x;
    var y = pending ? pending.position_y : device.position_y;

    var isAc = device.device_type === 'ac' || device.device_type === 'ac_central';
    var isComputer = device.device_type === 'computer';

    var acInfo = '';
    if (isAc) {
      var t = device.target_temperature !== null && device.target_temperature !== undefined ? Math.round(device.target_temperature) : 24;
      var m = device.ac_mode || 'cool';
      var b = Number(device.ac_boost) === 1;
      acInfo = '<div class="flex gap-2 wrap mt-1">' +
        '<span class="badge badge-primary plain">' + t + '°C</span>' +
        '<span class="badge ' + (m === 'dry' ? 'badge-warn' : 'badge-primary') + ' plain">' + (m === 'dry' ? 'Dry' : 'Cool') + '</span>' +
        (b ? '<span class="badge badge-danger plain">Powerful</span>' : '') +
        '</div>';
    }

    el.detailBody.innerHTML =
      '<strong>' + esc(device.name) + '</strong>' +
      '<div class="muted small">' + esc(deviceTypeName(device.device_type)) + ' · ' + esc(device.room_name || '—') + '</div>' +
      '<div class="flex gap-2 wrap mt-2">' +
      '<span class="badge ' + (device.power_state === 'on' ? 'badge-success' : device.power_state === 'off' ? 'badge-danger' : device.power_state === 'sleep' ? 'badge-warn' : '') + ' plain">' +
      esc(powerLabel(device.power_state)) + '</span>' +
      '<span class="badge ' + (device.connection_state === 'online' ? 'badge-success' : device.connection_state === 'offline' ? 'badge-danger' : '') + ' plain">' +
      esc(connectionLabel(device.connection_state)) + '</span>' +
      (x !== null ? '<span class="badge badge-primary plain">(' + x + ', ' + y + ')</span>' : '<span class="badge badge-warn plain">belum ditempatkan</span>') +
      '</div>' +
      acInfo +
      (canCommand(device)
        ? '<div class="flex gap-2 wrap mt-2">' +
          '<button class="btn btn-soft btn-sm" type="button" data-cmd-device="' + device.id + '" data-cmd="on">' +
          O.icon('power', 15) + ' Nyalakan</button>' +
          '<button class="btn btn-outline btn-sm" type="button" data-cmd-device="' + device.id + '" data-cmd="off">' +
          O.icon('x', 15) + ' Matikan</button>' +
          (isComputer
            ? '<button class="btn btn-outline btn-sm" type="button" data-cmd-device="' + device.id + '" data-cmd="sleep">' +
              O.icon('moon', 15) + ' Sleep</button>' +
              '<button class="btn btn-outline btn-sm" type="button" data-cmd-device="' + device.id + '" data-cmd="shutdown">' +
              O.icon('x', 15) + ' Shutdown</button>'
            : '') +
          '</div>'
        : (CFG.canControl && device.control_capability !== 'controllable'
          ? '<div class="hint mt-2">Perangkat ini hanya dapat dipantau.</div>'
          : '')) +
      '<div class="mt-2"><a class="btn btn-outline btn-sm" href="/perangkat/' + device.id + '">Buka detail perangkat</a></div>';

    if (state.editMode) {
      el.form.hidden = false;
      fillRoomOptions(device.room_id);
      el.posX.value = x === null ? '' : x;
      el.posY.value = y === null ? '' : y;
    } else {
      el.form.hidden = true;
    }
  }

  function fillRoomOptions(currentRoomId) {
    var current = findDevice(state.selectedId);
    var roomId = current ? current.room_id : currentRoomId;
    var opts = '';
    state.rooms.forEach(function (room) {
      opts += '<option value="' + room.id + '"' + (String(room.id) === String(roomId) ? ' selected' : '') + '>' +
        esc(room.name) + '</option>';
    });
    // Ruangan induk perangkat bisa berada di lantai lain
    if (current && !state.rooms.some(function (r) { return String(r.id) === String(current.room_id); })) {
      opts = '<option value="' + current.room_id + '" selected>' + esc(current.room_name || 'Ruangan perangkat') + '</option>' + opts;
    }
    el.posRoom.innerHTML = opts;
    el.posRoom.disabled = true; // perpindahan ruangan lewat edit perangkat
  }

  /* ------------------------------------------------ Pending / dirty ------- */

  function setPending(id, x, y) {
    if (x === null || y === null) {
      state.pending[id] = { position_x: null, position_y: null };
    } else {
      state.pending[id] = {
        position_x: Math.round(x * 100) / 100,
        position_y: Math.round(y * 100) / 100
      };
    }
    updateDirty();
    renderList();
    renderCounts();
  }

  function updateDirty() {
    state.dirty = Object.keys(state.pending).length > 0;
    var n = Object.keys(state.pending).length;
    el.dirtyStatus.textContent = n + ' perubahan belum disimpan';
    el.dirtyStatus.className = 'badge plain ' + (n > 0 ? 'badge-warn' : 'badge-muted');
    el.btnSave.disabled = n === 0;
    el.editorBar.classList.toggle('dirty', n > 0);
  }

  function saveAll() {
    var ids = Object.keys(state.pending);
    if (ids.length === 0) { return; }

    el.btnSave.disabled = true;
    var failures = [];

    var chain = Promise.resolve();
    ids.forEach(function (id) {
      chain = chain.then(function () {
        var p = state.pending[id];
        var device = findDevice(id);
        if (!device) { return; }
        return O.fetch('/api/devices/' + id + '/position', {
          method: 'POST',
          body: {
            room_id: device.room_id,
            position_x: p.position_x,
            position_y: p.position_y
          }
        }).catch(function (err) {
          failures.push(device.name + ': ' + err.message);
        });
      });
    });

    chain.then(function () {
      ids.forEach(function (id) { delete state.pending[id]; });
      updateDirty();

      if (failures.length > 0) {
        failures.forEach(function (f) { O.toast(f, 'error'); });
      } else {
        O.toast('Posisi perangkat tersimpan.', 'success');
      }

      return loadFloor(state.floorId).then(function () {
        if (state.selectedId) { select(state.selectedId); }
      });
    });
  }

  function revertAll() {
    state.pending = {};
    updateDirty();
    loadFloor(state.floorId).then(function () {
      if (state.selectedId) { select(state.selectedId); }
      O.toast('Perubahan dibatalkan.', 'info');
    });
  }

  /* ------------------------------------------------ Drag ------------------ */

  var drag = null;

  function onPointerDown(e) {
    if (!state.editMode) { return; }
    var dot = e.target.closest('.device-dot');
    if (!dot) { return; }

    e.preventDefault();
    var id = dot.dataset.deviceId;
    var box = dot.closest('.room-box');
    if (!box) { return; }

    dot.setPointerCapture(e.pointerId);
    drag = {
      id: id,
      dot: dot,
      box: box,
      startX: e.clientX,
      startY: e.clientY,
      moved: false,
      x: parseFloat(dot.style.left),
      y: parseFloat(dot.style.top)
    };
    dot.classList.add('dragging');
  }

  function onPointerMove(e) {
    if (!drag) { return; }
    var dx = e.clientX - drag.startX;
    var dy = e.clientY - drag.startY;
    if (!drag.moved && Math.abs(dx) + Math.abs(dy) < 5) { return; }
    drag.moved = true;

    var rect = drag.box.getBoundingClientRect();
    var nx = ((e.clientX - rect.left) / rect.width) * 100;
    var ny = ((e.clientY - rect.top) / rect.height) * 100;
    drag.x = clamp(nx, 0, 100);
    drag.y = clamp(ny, 0, 100);

    drag.dot.style.left = drag.x + '%';
    drag.dot.style.top = drag.y + '%';
  }

  function onPointerUp() {
    if (!drag) { return; }
    drag.dot.classList.remove('dragging');

    if (drag.moved) {
      setPending(drag.id, drag.x, drag.y);
      select(drag.id);
    } else {
      select(drag.id);
    }
    drag = null;
  }

  /* ------------------------------------------------ Form ------------------ */

  function onFormSubmit(e) {
    e.preventDefault();
    if (!state.selectedId) { return; }

    var x = parseFloat(el.posX.value);
    var y = parseFloat(el.posY.value);

    if (isNaN(x) || isNaN(y) || x < 0 || x > 100 || y < 0 || y > 100) {
      O.toast('Koordinat harus antara 0 dan 100.', 'error');
      return;
    }

    setPending(state.selectedId, x, y);
    var device = findDevice(state.selectedId);
    O.toast('Posisi ' + (device ? device.name : '') + ' diantrekan. Tekan Simpan untuk menulis.', 'info');

    // perbarui visual
    var dot = el.denah.querySelector('.device-dot[data-device-id="' + state.selectedId + '"]');
    if (dot) {
      dot.style.left = (Math.round(x * 100) / 100) + '%';
      dot.style.top = (Math.round(y * 100) / 100) + '%';
    } else {
      render();
      select(state.selectedId);
    }
  }

  function onUnplace() {
    if (!state.selectedId) { return; }
    setPending(state.selectedId, null, null);
    var dot = el.denah.querySelector('.device-dot[data-device-id="' + state.selectedId + '"]');
    if (dot) { dot.remove(); }
    el.posX.value = '';
    el.posY.value = '';
    var device = findDevice(state.selectedId);
    O.toast('Posisi ' + (device ? device.name : '') + ' dikosongkan dalam antrean.', 'info');
  }

  /* ------------------------------------------------ Events ---------------- */

  el.tabs.addEventListener('click', function (e) {
    var btn = e.target.closest('.floor-tab');
    if (!btn) { return; }
    el.tabs.querySelectorAll('.floor-tab').forEach(function (t) { t.classList.remove('active'); });
    btn.classList.add('active');
    loadFloor(parseInt(btn.dataset.floor, 10));
  });

  el.list.addEventListener('click', function (e) {
    var cmd = e.target.closest('[data-cmd]');
    if (cmd) {
      e.stopPropagation();
      sendCommand(cmd.getAttribute('data-cmd-device'), cmd.getAttribute('data-cmd'));
      return;
    }
    var item = e.target.closest('.side-item');
    if (!item) { return; }
    select(item.dataset.deviceId);
  });

  el.detailBody.addEventListener('click', function (e) {
    var cmd = e.target.closest('[data-cmd]');
    if (!cmd) { return; }
    sendCommand(cmd.getAttribute('data-cmd-device'), cmd.getAttribute('data-cmd'));
  });

  el.denah.addEventListener('pointerdown', onPointerDown);
  el.denah.addEventListener('pointermove', onPointerMove);
  el.denah.addEventListener('pointerup', onPointerUp);
  el.denah.addEventListener('pointercancel', onPointerUp);

  var btnEdit = document.getElementById('btn-edit-mode');
  if (btnEdit) {
    btnEdit.addEventListener('click', function () {
      if (!CFG.isAdmin) { return; }
      state.editMode = !state.editMode;
      el.editorBar.classList.toggle('show', state.editMode);
      btnEdit.classList.toggle('btn-soft', state.editMode);
      if (!state.editMode) {
        el.form.hidden = true;
      } else {
        O.toast('Mode edit aktif — seret perangkat dalam ruangan, atau ketuk untuk koordinat manual.', 'info');
      }
      if (state.selectedId) { select(state.selectedId); }
    });
  }

  var btnExit = document.getElementById('btn-exit-edit');
  if (btnExit) {
    btnExit.addEventListener('click', function () {
      if (state.dirty) {
        O.confirm({
          title: 'Keluar mode edit?',
          message: 'Ada perubahan yang belum disimpan. Keluar akan membatalkannya.',
          yesLabel: 'Batalkan & keluar'
        }).then(function (ok) {
          if (!ok) { return; }
          revertAll();
          state.editMode = false;
          el.editorBar.classList.remove('show');
          el.form.hidden = true;
          if (btnEdit) { btnEdit.classList.remove('btn-soft'); }
        });
      } else {
        state.editMode = false;
        el.editorBar.classList.remove('show');
        el.form.hidden = true;
        if (btnEdit) { btnEdit.classList.remove('btn-soft'); }
      }
    });
  }

  el.btnSave.addEventListener('click', saveAll);
  document.getElementById('btn-revert').addEventListener('click', revertAll);
  el.form.addEventListener('submit', onFormSubmit);
  document.getElementById('btn-unplace').addEventListener('click', onUnplace);

  /* ------------------------------------------------ Helpers --------------- */

  function clamp(v, min, max) { return Math.min(max, Math.max(min, v)); }
  function num(v) { return v === null || v === undefined ? 0 : parseFloat(v); }
  function esc(s) {
    return String(s === null || s === undefined ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function powerLabel(s) {
    return s === 'on' ? 'Menyala' : s === 'off' ? 'Mati' : s === 'sleep' ? 'Sleep' : 'Tidak diketahui';
  }
  function sensorMiniHtml(room) {
    var s = room.sensors || {};
    var parts = [];
    if (s.temperature) { parts.push(Math.round(s.temperature.value) + '°'); }
    if (s.humidity)    { parts.push(Math.round(s.humidity.value) + '%'); }
    if (s.light)       { parts.push(Math.round(s.light.value) + 'lx'); }
    if (parts.length === 0) { return ''; }
    return '<span class="room-sensor-mini">' + parts.join(' · ') + '</span>';
  }
  function connectionLabel(s) {
    return s === 'online' ? 'Online' : s === 'offline' ? 'Offline' : 'Tidak diketahui';
  }
  function deviceTypeName(t) {
    var map = {
      ac: 'AC', ac_central: 'AC Sentral', light: 'Lampu', computer: 'Komputer',
      projector: 'Proyektor', videotron: 'Videotron', audio: 'Perangkat Audio',
      exhaust_fan: 'Exhaust Fan', studio_lighting: 'Pencahayaan Studio', other: 'Lainnya'
    };
    return map[t] || t;
  }

  /* ------------------------- Polling ringan (tanpa reload) ---------------- */
  setInterval(function () {
    if (state.dirty || drag) { return; } // jangan ganggu saat edit
    O.fetch('/api/floors/' + state.floorId).then(function (res) {
      state.rooms = res.data.rooms;
      state.devices = res.data.devices;
      state.counts = res.data.counts;
      render();
      if (state.selectedId) { select(state.selectedId); }
    }).catch(function () { /* diamkan */ });
  }, 20000);

  loadFloor(state.floorId);
})();
