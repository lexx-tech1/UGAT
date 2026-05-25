/**
 * AdminWorkshops.js — UGAT TrainTrack
 * Connected to real database via get_workshops.php and save_workshops.php
 */

/* =============================================================================
   SHARED UTILITIES
   ============================================================================= */
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
function closeModalOutside(e, id) { if (e.target.id === id) closeModal(id); }

function showToast(msg) {
  const t = document.getElementById('toast');
  if (!t) return;
  t.innerHTML = msg + ' <span onclick="hideToast()" style="cursor:pointer;margin-left:0.5rem;opacity:0.7">✕</span>';
  t.classList.add('show');

  // Clear any previous timer
  if (window._toastTimer) clearTimeout(window._toastTimer);

  // Auto-hide after 8 seconds instead of 3.2
  window._toastTimer = setTimeout(() => t.classList.remove('show'), 8000);
}

function hideToast() {
  const t = document.getElementById('toast');
  if (t) t.classList.remove('show');
  if (window._toastTimer) clearTimeout(window._toastTimer);
}

/* =============================================================================
   PHONE NUMBER AUTO-FORMAT — +63 9 prefix
   ============================================================================= */
function setupPhoneField(id) {
  const input = document.getElementById(id);
  if (!input) return;
  const PREFIX = '+63 9';

  input.addEventListener('focus', function () {
    if (!this.value.startsWith(PREFIX)) this.value = PREFIX;
    const len = this.value.length;
    setTimeout(() => this.setSelectionRange(len, len), 0);
  });

  input.addEventListener('input', function () {
    let val = this.value;
    if (!val.startsWith(PREFIX)) {
      val = PREFIX + val.replace(/[^0-9]/g, '').slice(0, 9);
    } else {
      val = PREFIX + val.slice(PREFIX.length).replace(/[^0-9]/g, '').slice(0, 9);
    }
    this.value = val;
  });

  input.addEventListener('keydown', function (e) {
    const sel = this.selectionStart;
    if ((e.key === 'Backspace' && sel <= PREFIX.length) ||
        (e.key === 'Delete'    && sel < PREFIX.length)) {
      e.preventDefault();
    }
  });

  input.addEventListener('blur', function () {
    if (this.value === PREFIX) this.value = '';
  });
}

/* =============================================================================
   WORKSHOP DROPDOWN FOR ADD TRAINEE MODAL
   ============================================================================= */
let ntWsOpen = false;

function toggleWSDropdown() {
  const list  = document.getElementById('nt-workshop-list');
  const arrow = document.getElementById('nt-ws-arrow');
  if (!list) return;
  ntWsOpen = !ntWsOpen;
  list.style.display    = ntWsOpen ? 'block' : 'none';
  arrow.style.transform = ntWsOpen ? 'rotate(180deg)' : '';

  if (ntWsOpen) {
    setTimeout(() => {
      document.addEventListener('click', closeWSDropdownOutside);
    }, 10);
  }
}

function closeWSDropdownOutside(e) {
  const trigger = document.getElementById('nt-ws-trigger');
  const list    = document.getElementById('nt-workshop-list');
  if (!trigger || !list) return;
  if (!trigger.contains(e.target) && !list.contains(e.target)) {
    list.style.display = 'none';
    document.getElementById('nt-ws-arrow').style.transform = '';
    ntWsOpen = false;
    document.removeEventListener('click', closeWSDropdownOutside);
  }
}

function toggleWSCheckbox(workshopId, workshopTitle, checkbox) {
  if (checkbox.checked) {
    addWSTag(workshopId, workshopTitle);
  } else {
    removeWSTag(workshopId);
  }
  updateWSTriggerText();
}

function addWSTag(id, title) {
  const tags = document.getElementById('nt-ws-tags');
  if (!tags) return;
  if (document.getElementById(`ws-tag-${id}`)) return;
  const tag = document.createElement('span');
  tag.className = 'ws-tag';
  tag.id        = `ws-tag-${id}`;
  tag.innerHTML = `${title} <button type="button" onclick="removeWSTag(${id})" title="Remove">✕</button>`;
  tags.appendChild(tag);
}

function removeWSTag(id) {
  document.getElementById(`ws-tag-${id}`)?.remove();
  const cb = document.querySelector(`.nt-workshop-cb[value="${id}"]`);
  if (cb) cb.checked = false;
  updateWSTriggerText();
}

function updateWSTriggerText() {
  const checked = document.querySelectorAll('.nt-workshop-cb:checked');
  const trigger = document.getElementById('nt-ws-trigger-text');
  if (!trigger) return;
  if (checked.length === 0) {
    trigger.textContent = 'Select workshops…';
    trigger.style.color = '#aaa';
  } else {
    trigger.textContent = `${checked.length} workshop(s) selected`;
    trigger.style.color = '#4B8423';
  }
}

/* =============================================================================
   CASCADING ADDRESS DROPDOWNS — ADD TRAINEE MODAL
   Flow: Region → Province → City/Municipality → Barangay
   The hidden #nt-address field is assembled by buildNTAddress() and
   read by submitNewTrainee() — no change needed there.
   ============================================================================= */

/**
 * Populate a <select> with { id, name } items.
 * Enables or disables the element based on the `enable` flag.
 */
function ntFillSelect(selectId, items, placeholder, enable = true) {
  const sel = document.getElementById(selectId);
  if (!sel) return;
  sel.innerHTML = `<option value="">${placeholder}</option>`;
  items.forEach(item => {
    const opt       = document.createElement('option');
    opt.value       = item.id;
    opt.textContent = item.name;
    sel.appendChild(opt);
  });
  sel.disabled      = !enable;
  sel.style.opacity = enable ? '1'            : '0.55';
  sel.style.cursor  = enable ? 'auto'         : 'not-allowed';
}

/**
 * Reset a <select> back to its empty/disabled placeholder state.
 */
function ntResetSelect(selectId, placeholder) {
  ntFillSelect(selectId, [], placeholder, false);
}

/**
 * Fetch address data from get_address.php.
 * Returns [] on network/parse error so callers stay clean.
 */
async function ntFetchAddress(type, parentId = null) {
  try {
    let qs = `?type=${type}`;
    if (parentId !== null) {
      const paramMap = { provinces: 'region_id', cities: 'province_id', barangays: 'city_id' };
      qs += `&${paramMap[type]}=${parentId}`;
    }
    const res = await fetch(`get_address.php${qs}`);
    const d   = await res.json();
    if (!d.success) throw new Error(d.message);
    return d[type] || [];
  } catch (err) {
    console.error(`ntFetchAddress(${type}) error:`, err);
    showToast(`⚠️ Could not load ${type}. Check DB connection.`);
    return [];
  }
}

/* ── Cascade change handlers ──────────────────────────────── */

async function onNTRegionChange() {
  const regionId = document.getElementById('nt-region')?.value;
  ntResetSelect('nt-province', 'Select province…');
  ntResetSelect('nt-city',     'Select city…');
  ntResetSelect('nt-barangay', 'Select barangay…');
  buildNTAddress();
  if (!regionId) return;
  const provinces = await ntFetchAddress('provinces', regionId);
  ntFillSelect('nt-province', provinces, 'Select province…', true);
}

async function onNTProvinceChange() {
  const provinceId = document.getElementById('nt-province')?.value;
  ntResetSelect('nt-city',     'Select city…');
  ntResetSelect('nt-barangay', 'Select barangay…');
  buildNTAddress();
  if (!provinceId) return;
  const cities = await ntFetchAddress('cities', provinceId);
  ntFillSelect('nt-city', cities, 'Select city / municipality…', true);
}

async function onNTCityChange() {
  const cityId = document.getElementById('nt-city')?.value;
  ntResetSelect('nt-barangay', 'Select barangay…');
  buildNTAddress();
  if (!cityId) return;
  const barangays = await ntFetchAddress('barangays', cityId);
  ntFillSelect('nt-barangay', barangays, 'Select barangay…', true);
}

function onNTBarangayChange() {
  buildNTAddress();
}

/**
 * Assembles the full address string into the hidden #nt-address field.
 * Format: "House/Street, Barangay, City, Province, Region"
 * submitNewTrainee() already reads #nt-address — nothing else to change.
 */
function buildNTAddress() {
  const street   = document.getElementById('nt-street')?.value.trim() || '';

  const brgy     = document.getElementById('nt-barangay');
  const city     = document.getElementById('nt-city');
  const province = document.getElementById('nt-province');
  const region   = document.getElementById('nt-region');

  // Read the display text of the selected option (not the id value)
  const brgyName     = brgy?.options[brgy?.selectedIndex]?.text     || '';
  const cityName     = city?.options[city?.selectedIndex]?.text     || '';
  const provinceName = province?.options[province?.selectedIndex]?.text || '';
  const regionName   = region?.options[region?.selectedIndex]?.text   || '';

  const PLACEHOLDERS = new Set([
    'Select region…', 'Select province…',
    'Select city…', 'Select city / municipality…', 'Select barangay…',
  ]);

  const parts = [street, brgyName, cityName, provinceName, regionName]
    .map(p => p.trim())
    .filter(p => p && !PLACEHOLDERS.has(p));

  const hidden = document.getElementById('nt-address');
  if (hidden) hidden.value = parts.join(', ');
}

/**
 * Returns true only when all 4 required address dropdowns are filled.
 * Called inside submitNewTrainee() before the DB request.
 */
function validateNTAddress() {
  return (
    !!document.getElementById('nt-region')?.value   &&
    !!document.getElementById('nt-province')?.value &&
    !!document.getElementById('nt-city')?.value     &&
    !!document.getElementById('nt-barangay')?.value
  );
}

/**
 * Resets all address fields to their initial empty/disabled state.
 * Called when the modal is closed so it's clean on next open.
 */
function resetNTAddressFields() {
  const regionSel = document.getElementById('nt-region');
  if (regionSel) {
    regionSel.value    = '';
    regionSel.disabled = false;   // region stays enabled
    regionSel.style.opacity = '1';
    regionSel.style.cursor  = 'auto';
  }
  ntResetSelect('nt-province', 'Select province…');
  ntResetSelect('nt-city',     'Select city…');
  ntResetSelect('nt-barangay', 'Select barangay…');

  const street = document.getElementById('nt-street');
  if (street) street.value = '';

  const hidden = document.getElementById('nt-address');
  if (hidden) hidden.value = '';
}

/**
 * Load all regions into #nt-region on page load.
 */
async function loadNTRegions() {
  const regions = await ntFetchAddress('regions');
  ntFillSelect('nt-region', regions, 'Select region…', true);
}

/* =============================================================================
   GLOBAL DATA
   ============================================================================= */
let WORKSHOPS_LIST  = [];
let TRAINEES        = [];
let ATTENDANCE_DATA = [];
let ELIGIBLE        = [];
let ISSUED          = [];

let activePMTab       = 'workshops';
let awCurrentStep     = 1;
let awSessionCount    = 0;
let wsAttendanceMarks = {};
let trAttMarks        = {};
let attDetailRecord   = null;
let pendingIssuanceId = null;
let viewingCertId     = null;
let _currentEnrollmentId   = null;
let _currentEnrollmentName = null;

/* =============================================================================
   TAB SWITCHING
   ============================================================================= */
function switchPMTab(tab, btn) {
  document.querySelectorAll('.pm-tab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.pm-tab-content').forEach(el => el.style.display = 'none');
  document.getElementById('tab-' + tab).style.display = '';
  activePMTab = tab;

  const subMap = {
    workshops:      'Create, edit, and monitor all UGAT workshops and their sessions',
    trainees:       'Manage all registered trainees and their enrollment status',
    attendance:     'View and manage attendance records across all workshops',
    certifications: 'Issue certificates to eligible trainees',
  };
  document.getElementById('pm-sub').textContent = subMap[tab];

  const ha = document.getElementById('pm-header-actions');
  ha.innerHTML = '';
  if (tab === 'workshops') {
    ha.innerHTML = `
      <button class="btn-secondary" onclick="openModal('edit-workshops-modal');populateEditWorkshopsTable()">Edit Workshops</button>
      <button class="btn-primary"   onclick="openModal('add-workshop-modal');resetAddWorkshopForm();awGoStep(1)">+ Add New Workshop</button>`;
  } else if (tab === 'trainees') {
    ha.innerHTML = `
      <button class="btn-secondary" onclick="openModal('tr-attendance-modal');resetTRAttModal()">Log Attendance</button>
      <button class="btn-primary"   onclick="openModal('add-trainee-modal')">+ Add New Trainee</button>`;
  } else if (tab === 'attendance') {
    ha.innerHTML = `<button class="btn-secondary" onclick="openModal('att-export-modal')">Export Report</button>`;
  } else if (tab === 'certifications') {
    ha.innerHTML = `<button class="btn-secondary" onclick="exportAllCertificates()">Export All</button>`;
    loadExportOptions();
  }

  const loaders = {
    workshops:      loadWorkshops,
    trainees:       loadTrainees,
    attendance:     loadAttendance,
    certifications: loadCertifications,
  };
  if (loaders[tab]) loaders[tab]();
}

/* =============================================================================
   TAB 1 — WORKSHOPS (from DB)
   ============================================================================= */
async function loadWorkshops() {
  try {
    const r = await fetch('get_workshops.php?action=workshops');
    const d = await r.json();
    if (!d.success) return;
    WORKSHOPS_LIST = d.workshops;
    renderWorkshopCards();
  } catch(e) { console.error('loadWorkshops error:', e); }
}

function renderWorkshopCards(filter = 'all') {
  const sections     = { upcoming:[], ongoing:[], completed:[] };
  const statusConfig = { upcoming:'badge-upcoming', ongoing:'badge-ongoing', completed:'badge-completed' };
  const statusLabel  = { upcoming:'Upcoming',       ongoing:'Ongoing',       completed:'Completed' };

  WORKSHOPS_LIST.forEach(w => { if (sections[w.status]) sections[w.status].push(w); });

  ['upcoming','ongoing','completed'].forEach(s => {
    const grid = document.getElementById(`wshop-${s}`);
    const hdr  = document.getElementById(`wshop-${s}-hdr`);
    const show = filter === 'all' || filter === s;
    if (grid) grid.style.display = (show && sections[s].length > 0) ? '' : 'none';
    if (hdr)  hdr.style.display  = (show && sections[s].length > 0) ? '' : 'none';
    if (!grid) return;

    grid.innerHTML = sections[s].map(w => {
      const filled = parseInt(w.filled_slots) || 0;
      const max    = parseInt(w.max_slots)    || 1;
      const pct    = Math.round((filled / max) * 100);
      return `
        <div class="wshop-card">
          <div class="wshop-cat">${w.category || ''}</div>
          <h4>${w.title}</h4>
          <p>📅 ${w.first_session_date || 'TBD'}</p>
          <p>📍 ${w.location || 'UGAT Demo Farm'}</p>
          <p>👤 ${w.facilitator || '—'}</p>
          <div class="wshop-slots"><span>Slots filled</span><span>${filled} / ${max}</span></div>
          <div class="progress-bar"><div class="progress-fill" style="width:${pct}%"></div></div>
          <p class="light-txt">${w.session_count || 0} sessions · ${max - filled} slots remaining</p>
          <div class="wshop-actions">
            <span class="badge ${statusConfig[w.status]}">${statusLabel[w.status]}</span>
            <button class="btn-sm" onclick="viewWorkshop(${w.id})">View</button>
            <button class="btn-sm" onclick="openWSAttendanceModal(${w.id}, '${w.title}')">Attendance</button>
          </div>
        </div>`;
    }).join('') || '<p style="color:#aaa;padding:1rem">No workshops.</p>';
  });

  document.getElementById('ws-stat-total').textContent     = WORKSHOPS_LIST.length;
  document.getElementById('ws-stat-upcoming').textContent  = sections.upcoming.length;
  document.getElementById('ws-stat-ongoing').textContent   = sections.ongoing.length;
  document.getElementById('ws-stat-completed').textContent = sections.completed.length;
}

function filterWorkshops(filter, btn) {
  document.querySelectorAll('.tab-row .tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  renderWorkshopCards(filter);
}

function viewWorkshop(id) { window.location.href = `WorkshopDetail.html?id=${id}`; }

/* ── Attendance modal from workshop card ─────────────────── */
async function openWSAttendanceModal(workshopId, workshopTitle) {
  document.getElementById('ws-att-title').textContent = `Attendance · ${workshopTitle}`;
  document.getElementById('ws-att-sub').textContent   = 'Loading sessions…';
  wsAttendanceMarks = {};
  openModal('ws-attendance-modal');

  try {
    const [sessR, traineeR] = await Promise.all([
      fetch(`get_workshops.php?action=sessions&workshop_id=${workshopId}`),
      fetch(`get_workshops.php?action=enrolled&workshop_id=${workshopId}`)
    ]);
    const sessD    = await sessR.json();
    const traineeD = await traineeR.json();
    const sessions = sessD.sessions    || [];
    const trainees = traineeD.trainees || [];

    const today = new Date().toISOString().slice(0, 10); // YYYY-MM-DD

    // Only allow sessions on today or in the past
    const eligible = sessions.filter(s => s.session_date && s.session_date <= today);

    if (!eligible.length) {
      const next = sessions.find(s => s.session_date > today);
      const nextLabel = next
        ? ` The next session is on <strong>${new Date(next.session_date + 'T00:00:00').toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'})}</strong>.`
        : '';
      document.getElementById('ws-att-sub').textContent = 'Attendance not yet available';
      document.getElementById('ws-att-tbody').innerHTML =
        `<tr><td colspan="5" style="text-align:center;padding:2rem;color:#888;font-size:var(--text-body-sm)">
          ⏳ Attendance can only be marked on or after the session date.${nextLabel}
        </td></tr>`;
      // Disable the Save button
      const saveBtn = document.querySelector('#ws-attendance-modal .btn-primary');
      if (saveBtn) { saveBtn.disabled = true; saveBtn.style.opacity = '0.4'; saveBtn.style.cursor = 'not-allowed'; }
      updateWSAttSummary();
      return;
    }
    // Re-enable Save button in case it was disabled from a previous open
    const saveBtn = document.querySelector('#ws-attendance-modal .btn-primary');
    if (saveBtn) { saveBtn.disabled = false; saveBtn.style.opacity = ''; saveBtn.style.cursor = ''; }

    // Pick the most recent eligible session
    const sess = eligible[eligible.length - 1];

    document.getElementById('ws-att-sub').textContent          = `Session ${sess.session_no} · ${sess.session_date || 'TBD'}`;
    document.getElementById('ws-att-tbody').dataset.sessionId  = sess.id;

    const tbody = document.getElementById('ws-att-tbody');
    tbody.innerHTML = trainees.map((t, i) => `
      <tr>
        <td>${i + 1}</td>
        <td>
          <div class="trainee-cell">
            <img src="${t.profile_pic ? '/UGAT/' + t.profile_pic : `https://ui-avatars.com/api/?name=${encodeURIComponent(t.name)}&size=32&background=4B8423&color=fff`}" class="mini-avatar">
            <span>${t.name}</span>
          </div>
        </td>
        <td>${t.phone || '—'}</td>
        <td>—</td>
        <td>
          <div class="att-btn-group">
            <button class="att-btn" onclick="markWSAtt(this,'present',${t.id})">Present</button>
            <button class="att-btn" onclick="markWSAtt(this,'late',${t.id})">Late</button>
            <button class="att-btn" onclick="markWSAtt(this,'absent',${t.id})">Absent</button>
          </div>
        </td>
      </tr>`).join('') || '<tr><td colspan="5" style="text-align:center;color:#aaa;padding:1rem">No enrolled trainees.</td></tr>';

    updateWSAttSummary();
  } catch(e) { document.getElementById('ws-att-sub').textContent = 'Error loading data.'; }
}

function markWSAtt(btn, status, userId) {
  btn.closest('.att-btn-group').querySelectorAll('.att-btn').forEach(b => b.className = 'att-btn');
  btn.classList.add('selected-' + status);
  wsAttendanceMarks[userId] = status;
  updateWSAttSummary();
}

function updateWSAttSummary() {
  const c = { present:0, late:0, absent:0 };
  Object.values(wsAttendanceMarks).forEach(s => c[s]++);
  document.getElementById('ws-att-present').textContent = c.present;
  document.getElementById('ws-att-late').textContent    = c.late;
  document.getElementById('ws-att-absent').textContent  = c.absent;
}

async function saveWSAttendance() {
  const total = Object.keys(wsAttendanceMarks).length;
  if (!total) { showToast('Mark at least one trainee.'); return; }

  const session_id = parseInt(document.getElementById('ws-att-tbody').dataset.sessionId);
  const records    = Object.entries(wsAttendanceMarks).map(([user_id, status]) => ({ user_id: parseInt(user_id), status }));

  try {
    const r = await fetch('save_workshops.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ action: 'save_attendance', session_id, records }),
    });
    const d = await r.json();
    closeModal('ws-attendance-modal');
    showToast(d.success ? `✅ ${d.message}` : `❌ ${d.message}`);
    if (d.newly_flagged > 0) {
      setTimeout(() => showToast(`🎓 ${d.newly_flagged} trainee(s) now eligible for certification!`), 1500);
    }
  } catch(e) { showToast('Could not save attendance.'); }
}

/* ── Add Workshop (3-step) ───────────────────────────────── */
function awGoStep(step) {
  if (step > awCurrentStep && !awValidateStep(awCurrentStep)) return;
  [1,2,3].forEach(n => {
    document.getElementById(`aw-page-${n}`).style.display = 'none';
    const dot = document.getElementById(`aw-step-dot-${n}`);
    dot.classList.remove('active','done');
    if (n < step) dot.classList.add('done');
    if (n === step) dot.classList.add('active');
  });
  document.querySelectorAll('.aw-step-line').forEach((line, i) => { line.classList.toggle('done', i + 1 < step); });
  document.getElementById(`aw-page-${step}`).style.display = 'block';
  awCurrentStep = step;
  if (step === 2 && awSessionCount === 0) addSessionRow();
}

function awValidateStep(step) {
  const err = document.getElementById(`aw-error-${step}`);
  if (step === 1) {
    if (!document.getElementById('aw-title').value.trim()       ||
        !document.getElementById('aw-category').value           ||
        !document.getElementById('aw-facilitator').value.trim() ||
        !document.getElementById('aw-max-slots').value) {
      err.textContent = 'Please fill in all required fields.'; err.style.display = 'block'; return false;
    }
  }
  if (step === 2) {
    const rows = document.querySelectorAll('#aw-sessions-list .session-row');
    if (!rows.length) { err.textContent = 'Add at least one session.'; err.style.display = 'block'; return false; }
    let ok = true;
    rows.forEach(r => { if (!r.querySelector('.sess-date').value) ok = false; });
    if (!ok) { err.textContent = 'Fill date for every session.'; err.style.display = 'block'; return false; }
  }
  if (step === 3) {
    if (!document.getElementById('aw-description').value.trim()) {
      err.textContent = 'Description is required.'; err.style.display = 'block'; return false;
    }
  }
  err.style.display = 'none'; return true;
}

function addSessionRow() {
  if (awSessionCount >= 5) { showToast('Maximum 5 sessions.'); return; }
  awSessionCount++;
  const n    = awSessionCount;
  const list = document.getElementById('aw-sessions-list');
  const div  = document.createElement('div');
  div.className = 'session-row'; div.id = `sess-row-${n}`;
  div.innerHTML = `
    <span class="session-row-num">S${n}</span>
    <input type="text" class="form-input sess-title" placeholder="Session title">
    <input type="date" class="form-input sess-date">
    <div style="display:flex;align-items:center;gap:0.35rem;flex:1;min-width:0">
      <input type="time" class="form-input sess-time-start" style="flex:1;min-width:0">
      <span style="font-size:0.75rem;color:#888;white-space:nowrap">to</span>
      <input type="time" class="form-input sess-time-end" style="flex:1;min-width:0">
    </div>
    <button class="session-row-remove" onclick="document.getElementById('sess-row-${n}').remove()" title="Remove">✕</button>`;
  list.appendChild(div);
}

async function submitNewWorkshop() {
  if (!awValidateStep(3)) return;

  const sessions = [];
  document.querySelectorAll('#aw-sessions-list .session-row').forEach(row => {
    const fmt = t => {
      if (!t) return '';
      const [h, m] = t.split(':');
      const hr = +h % 12 || 12;
      return `${hr}:${m} ${+h < 12 ? 'AM' : 'PM'}`;
    };
    const start = fmt(row.querySelector('.sess-time-start').value);
    const end   = fmt(row.querySelector('.sess-time-end').value);
    const time  = start && end ? `${start} – ${end}` : start || end || '';
    sessions.push({ date: row.querySelector('.sess-date').value, time });
  });

  const payload = {
    action:           'add_workshop',
    title:            document.getElementById('aw-title').value.trim(),
    category:         document.getElementById('aw-category').value,
    facilitator:      document.getElementById('aw-facilitator').value.trim(),
    location:         document.getElementById('aw-location').value.trim(),
    max_slots:        document.getElementById('aw-max-slots').value,
    status:           document.getElementById('aw-status').value,
    description:      document.getElementById('aw-description').value.trim(),
    outcomes:         document.getElementById('aw-outcomes').value.trim(),
    materials:        document.getElementById('aw-materials').value.trim(),
    cert_requirement: document.getElementById('aw-cert-req').value.trim(),
    sessions,
  };

  try {
    const r = await fetch('save_workshops.php', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const d = await r.json();
    if (d.success) {
      closeModal('add-workshop-modal');
      showToast(`✅ ${d.message}`);
      loadWorkshops();
    } else {
      document.getElementById('aw-error-3').textContent    = d.message;
      document.getElementById('aw-error-3').style.display = 'block';
    }
  } catch(e) { showToast('Could not add workshop.'); }
}

function resetAddWorkshopForm() {
  ['aw-title','aw-facilitator','aw-cert-req','aw-description','aw-outcomes','aw-materials'].forEach(id => {
    const el = document.getElementById(id); if (el) el.value = '';
  });
  document.getElementById('aw-location').value = 'UGAT Demo Farm, San Isidro, Daet, Camarines Norte';
  document.getElementById('aw-category').value = '';
  document.getElementById('aw-sessions-list').innerHTML = '';
  awSessionCount = 0; awCurrentStep = 1;
  [1,2,3].forEach(n => { const e = document.getElementById(`aw-error-${n}`); if (e) e.style.display = 'none'; });
}

function populateEditWorkshopsTable() {
  const tbody = document.getElementById('edit-workshops-tbody');
  if (!tbody) return;
  const bMap = { upcoming:'badge-upcoming', ongoing:'badge-ongoing', completed:'badge-completed' };
  const lMap = { upcoming:'Upcoming',       ongoing:'Ongoing',       completed:'Completed' };
  tbody.innerHTML = WORKSHOPS_LIST.map(w => `
    <tr>
      <td><strong>${w.title}</strong></td>
      <td>${w.category}</td>
      <td>${w.first_session_date || 'TBD'}</td>
      <td>${w.filled_slots || 0} / ${w.max_slots}</td>
      <td><span class="badge ${bMap[w.status]}">${lMap[w.status]}</span></td>
      <td><button class="btn-sm" onclick="openEditSingle(${w.id})">✏️ Edit</button></td>
    </tr>`).join('');
}

async function openEditSingle(id) {
  const w = WORKSHOPS_LIST.find(x => x.id == id); if (!w) return;
  document.getElementById('edit-single-title').textContent = `Edit: ${w.title}`;
  document.getElementById('es-title').value       = w.title;
  document.getElementById('es-facilitator').value = w.facilitator || '';
  document.getElementById('es-location').value    = w.location    || '';
  document.getElementById('es-slots').value       = w.max_slots;
  document.getElementById('es-description').value = w.description || '';
  document.getElementById('es-index').value       = w.id;
  for (const opt of document.getElementById('es-category').options) { if (opt.value === w.category) { opt.selected = true; break; } }
  closeModal('edit-workshops-modal');
  openModal('edit-single-modal');

  // Load existing sessions
  const container = document.getElementById('es-sessions-list');
  container.innerHTML = '<p style="color:#aaa;font-size:var(--text-caption)">Loading sessions…</p>';
  try {
    const r = await fetch(`get_workshops.php?action=sessions&workshop_id=${w.id}`);
    const d = await r.json();
    const sessions = d.sessions || [];
    if (!sessions.length) {
      container.innerHTML = '<p style="color:#aaa;font-size:var(--text-caption)">No sessions found.</p>';
      return;
    }
    const toTime12 = t => {
      if (!t) return '';
      const [h, m] = t.split(':');
      const hr = +h % 12 || 12;
      return `${String(hr).padStart(2,'0')}:${m}`;
    };
    container.innerHTML = sessions.map((s, i) => `
      <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap" data-session-id="${s.id}">
        <span style="font-size:0.75rem;font-weight:700;color:#4B8423;min-width:24px">S${i+1}</span>
        <input type="date" class="form-input es-sess-date" value="${s.session_date || ''}" style="flex:1;min-width:140px">
        <input type="time" class="form-input es-sess-time-start" value="${s.start_time ? s.start_time.slice(0,5) : ''}" style="flex:1;min-width:120px" title="Start time">
      </div>`).join('');
  } catch(e) {
    container.innerHTML = '<p style="color:#e53e3e;font-size:var(--text-caption)">Could not load sessions.</p>';
  }
}

async function saveEditedWorkshop() {
  const id    = document.getElementById('es-index').value;
  const title = document.getElementById('es-title').value.trim();
  if (!title) { showToast('Title is required.'); return; }

  const sessions = [];
  document.querySelectorAll('#es-sessions-list [data-session-id]').forEach(row => {
    sessions.push({
      id:   parseInt(row.dataset.sessionId),
      date: row.querySelector('.es-sess-date').value,
      time: row.querySelector('.es-sess-time-start').value,  // HH:MM for DB
    });
  });

  const payload = {
    action:      'edit_workshop',
    id:          parseInt(id),
    title,
    category:    document.getElementById('es-category').value,
    facilitator: document.getElementById('es-facilitator').value.trim(),
    location:    document.getElementById('es-location').value.trim(),
    max_slots:   document.getElementById('es-slots').value,
    description: document.getElementById('es-description').value.trim(),
    sessions,
  };

  try {
    const r = await fetch('save_workshops.php', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const d = await r.json();
    closeModal('edit-single-modal');
    showToast(d.success ? `✅ ${d.message}` : `❌ ${d.message}`);
    if (d.success) loadWorkshops();
  } catch(e) { showToast('Could not save changes.'); }
}

/* =============================================================================
   TAB 2 — TRAINEES (from DB)
   ============================================================================= */
async function loadTrainees() {
  try {
    const r = await fetch('get_workshops.php?action=trainees');
    const d = await r.json();
    if (!d.success) return;
 
    TRAINEES = d.trainees;
    renderTraineesTable();
 
    // ── Stat cards ──────────────────────────────────────────────────────────
    const total    = TRAINEES.length;
    const active   = TRAINEES.filter(t => (t.enrollment_status||'').toLowerCase() === 'enrolled').length;
    const certified= TRAINEES.filter(t => (t.enrollment_status||'').toLowerCase() === 'completed').length;
    const pending  = TRAINEES.filter(t => (t.enrollment_status||'').toLowerCase() === 'pending').length;
 
    document.getElementById('tr-stat-total').textContent     = total;
    document.getElementById('tr-stat-active').textContent    = active;
    document.getElementById('tr-stat-certified').textContent = certified;
    document.getElementById('tr-stat-pending').textContent   = pending;
 
    // ── Pending banner ──────────────────────────────────────────────────────
    const banner = document.getElementById('pending-enrollment-banner');
    if (banner) {
      if (pending > 0) {
        document.getElementById('pending-banner-count').textContent = pending;
        banner.style.display = 'flex';
      } else {
        banner.style.display = 'none';
      }
    }
 
    // ── Populate workshop filter dropdown ───────────────────────────────────
    const wsFilter = document.getElementById('tr-filter-workshop');
    if (wsFilter) {
      const seen = new Set();
      wsFilter.innerHTML = '<option value="">All Workshops</option>';
      TRAINEES.forEach(t => {
        if (t.workshop && !seen.has(t.workshop)) {
          seen.add(t.workshop);
          wsFilter.innerHTML += `<option value="${t.workshop}">${t.workshop}</option>`;
        }
      });
    }
 
  } catch (e) { console.error('loadTrainees error:', e); }
}
 
async function openEnrollmentDetail(enrollmentId, traineeName) {
  _currentEnrollmentId   = enrollmentId;
  _currentEnrollmentName = traineeName;
 
  document.getElementById('enroll-detail-sub').textContent = `Reviewing: ${traineeName}`;
 
  // Reset to loading state
  document.getElementById('enroll-detail-loading').style.display  = 'block';
  document.getElementById('enroll-detail-content').style.display  = 'none';
  document.getElementById('enroll-detail-actions').style.display  = 'none';
 
  openModal('enrollment-detail-modal');
 
  try {
    const r = await fetch(`get_enrollment_detail.php?enrollment_id=${enrollmentId}`);
    const d = await r.json();
 
    if (!d.success || !d.detail) {
      document.getElementById('enroll-detail-loading').textContent = '⚠️ Could not load enrollment details.';
      return;
    }
 
    const info = d.detail;
    const fmt  = v => v || '';
 
    // Workshop section
    document.getElementById('enroll-workshop-title').textContent = fmt(info.workshop_title);
    document.getElementById('enroll-workshop-meta').textContent  =
      [info.workshop_category, info.workshop_facilitator].filter(Boolean).join(' · ');
 
    // Personal
    document.getElementById('enroll-lastname').textContent        = fmt(info.last_name);
    document.getElementById('enroll-firstname').textContent       = fmt(info.first_name);
    document.getElementById('enroll-middlename').textContent      = fmt(info.middle_name);
    document.getElementById('enroll-gender').textContent          = fmt(info.gender);
    document.getElementById('enroll-civil').textContent           = fmt(info.civil_status);
    document.getElementById('enroll-nationality').textContent     = fmt(info.nationality);
    document.getElementById('enroll-pwd').textContent             = info.is_pwd == 1 ? 'Yes' : 'No';
 
    // Birthdate
    if (info.birthdate) {
      const bd = new Date(info.birthdate);
      document.getElementById('enroll-birthdate').textContent =
        bd.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    } else {
      document.getElementById('enroll-birthdate').textContent = '—';
    }
 
    // Contact
    document.getElementById('enroll-phone').textContent = fmt(info.phone);
    document.getElementById('enroll-email').textContent = fmt(info.email);
 
    // Address
    document.getElementById('enroll-region').textContent   = fmt(info.region);
    document.getElementById('enroll-province').textContent = fmt(info.province);
    document.getElementById('enroll-city').textContent     = fmt(info.city);
    document.getElementById('enroll-barangay').textContent = fmt(info.barangay);
    document.getElementById('enroll-address').textContent  = fmt(info.address);
 
    // Background
    document.getElementById('enroll-education').textContent      = fmt(info.education);
    document.getElementById('enroll-employment').textContent     = fmt(info.employment);
    document.getElementById('enroll-classification').textContent = fmt(info.learner_class);
 
    // Guardian (hide section if empty)
    const hasGuardian = info.guardian_name || info.guardian_addr;
    const guardianSec = document.getElementById('enroll-guardian-section');
    if (guardianSec) guardianSec.style.display = hasGuardian ? '' : 'none';
    document.getElementById('enroll-guardian-name').textContent = fmt(info.guardian_name);
    document.getElementById('enroll-guardian-addr').textContent = fmt(info.guardian_addr);
 
    // Submission date
    if (info.enrolled_at) {
      const sub = new Date(info.enrolled_at);
      document.getElementById('enroll-submitted-date').textContent =
        sub.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric',
                                       hour: '2-digit', minute: '2-digit' });
    }
 
    // Show/hide approve-reject buttons based on status
    const isPending = (info.enrollment_status || '').toLowerCase() === 'pending';
    document.getElementById('enroll-approve-reject-btns').style.display = isPending ? 'flex' : 'none';
 
    // Show content, hide loader
    document.getElementById('enroll-detail-loading').style.display  = 'none';
    document.getElementById('enroll-detail-content').style.display  = 'flex';
    document.getElementById('enroll-detail-actions').style.display  = 'flex';
 
  } catch (e) {
    document.getElementById('enroll-detail-loading').textContent = '⚠️ Error loading details.';
    console.error('openEnrollmentDetail error:', e);
  }
}

async function approveEnrollment() {
  if (!_currentEnrollmentId) return;
  await _doEnrollmentAction('approve', _currentEnrollmentId, _currentEnrollmentName);
}
 
async function rejectEnrollment() {
  if (!_currentEnrollmentId) return;
  await _doEnrollmentAction('reject', _currentEnrollmentId, _currentEnrollmentName);
}
 
async function quickApprove(enrollmentId, name) {
  if (!confirm(`Approve enrollment for ${name}?`)) return;
  await _doEnrollmentAction('approve', enrollmentId, name);
}
 
async function quickReject(enrollmentId, name) {
  if (!confirm(`Reject enrollment for ${name}? This cannot be undone.`)) return;
  await _doEnrollmentAction('reject', enrollmentId, name);
}

async function _doEnrollmentAction(action, enrollmentId, name) {
  // Disable buttons to prevent double-click
  ['enroll-approve-btn', 'enroll-reject-btn'].forEach(id => {
    const btn = document.getElementById(id);
    if (btn) btn.disabled = true;
  });
 
  try {
// AFTER (add credentials)
const r = await fetch('approve_enrollment.php', {
  method:      'POST',
  headers:     { 'Content-Type': 'application/json' },
  credentials: 'same-origin',
  body:        JSON.stringify({ action, enrollment_id: enrollmentId }),
});
    
    if (!r.ok) {
      throw new Error(`HTTP ${r.status}: ${r.statusText}`);
    }
    
    const d = await r.json();
 
    closeModal('enrollment-detail-modal');
 
    if (d.success) {
      const icon = action === 'approve' ? '✅' : '❌';
      showToast(`${icon} ${d.message}`);
      loadTrainees(); // Refresh table + banner + stat cards
    } else {
      showToast(`⚠️ ${d.message || 'Unable to complete action'}`);
    }
  } catch (e) {
    console.error('_doEnrollmentAction error:', e);
    const errorMsg = e.message || 'Unknown error';
    showToast(`⚠️ Error: ${errorMsg}. Please check your connection and try again.`);
  } finally {
    ['enroll-approve-btn', 'enroll-reject-btn'].forEach(id => {
      const btn = document.getElementById(id);
      if (btn) btn.disabled = false;
    });
  }
}

function renderTraineesTable(list) {
  if (!list) {
    const search   = document.getElementById('tr-search')?.value.trim().toLowerCase() || '';
    const status   = document.getElementById('tr-filter-status')?.value               || '';
    const workshop = document.getElementById('tr-filter-workshop')?.value             || '';
    const sort     = document.getElementById('tr-sort')?.value                        || 'newest';
 
    list = TRAINEES.filter(t => {
      const name = `${t.first_name || ''} ${t.last_name || ''}`.toLowerCase();
      const ms   = !search   || name.includes(search) || (t.email || '').toLowerCase().includes(search);
      const mst  = !status   || (t.enrollment_status || '').toLowerCase() === status.toLowerCase();
      const mw   = !workshop || (t.workshop || '') === workshop;
      return ms && mst && mw;
    });
 
    // Sort: pending first option
    if (sort === 'pending') {
      list = [...list].sort((a, b) => {
        const ap = (a.enrollment_status || '').toLowerCase() === 'pending' ? 0 : 1;
        const bp = (b.enrollment_status || '').toLowerCase() === 'pending' ? 0 : 1;
        return ap - bp;
      });
    } else if (sort === 'name') {
      list = [...list].sort((a, b) =>
        `${a.first_name} ${a.last_name}`.localeCompare(`${b.first_name} ${b.last_name}`)
      );
    }
  }
 
  const tbody = document.getElementById('trainees-tbody');
  if (!tbody) return;
 
  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--color-text-light)">No trainees found.</td></tr>`;
    return;
  }
 
  tbody.innerHTML = list.map(t => {
    const name     = `${t.first_name || ''} ${t.last_name || ''}`.trim();
    const pic      = t.profile_pic
      ? '/UGAT/' + t.profile_pic
      : `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&size=32&background=4B8423&color=fff`;
    const attended = parseInt(t.sessions_attended) || 0;
    const total    = parseInt(t.total_sessions)    || 0;
    const rate     = total ? Math.round((attended / total) * 100) + '%' : '—';
    const status   = (t.enrollment_status || '').toLowerCase();
    const eid      = t.enrollment_id; // needs to be returned by get_workshops.php
 
    // ── Badge ────────────────────────────────────────────────────────────────
// FIND and REPLACE the badgeHtml block inside renderTraineesTable

let badgeHtml;
if (status === 'pending') {
  badgeHtml = `<span style="display:inline-flex;align-items:center;gap:5px;
    background:#fef3c7;color:#92400e;border:1px solid #fcd34d;
    padding:4px 10px;border-radius:20px;font-size:0.75rem;font-weight:600;
    letter-spacing:0.02em">
    <span style="width:6px;height:6px;border-radius:50%;background:#f59e0b;display:inline-block"></span>
    Pending
  </span>`;
} else if (status === 'rejected') {
  badgeHtml = `<span style="display:inline-flex;align-items:center;gap:5px;
    background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;
    padding:4px 10px;border-radius:20px;font-size:0.75rem;font-weight:600;
    letter-spacing:0.02em">
    <span style="width:6px;height:6px;border-radius:50%;background:#ef4444;display:inline-block"></span>
    Rejected
  </span>`;
} else if (status === 'enrolled') {
  badgeHtml = `<span style="display:inline-flex;align-items:center;gap:5px;
    background:#dcfce7;color:#166534;border:1px solid #86efac;
    padding:4px 10px;border-radius:20px;font-size:0.75rem;font-weight:600;
    letter-spacing:0.02em">
    <span style="width:6px;height:6px;border-radius:50%;background:#22c55e;display:inline-block"></span>
    Enrolled
  </span>`;
} else if (status === 'completed') {
  badgeHtml = `<span style="display:inline-flex;align-items:center;gap:5px;
    background:#dbeafe;color:#1e40af;border:1px solid #93c5fd;
    padding:4px 10px;border-radius:20px;font-size:0.75rem;font-weight:600;
    letter-spacing:0.02em">
    <span style="width:6px;height:6px;border-radius:50%;background:#3b82f6;display:inline-block"></span>
    Completed
  </span>`;
} else if (status === 'dropped') {
  badgeHtml = `<span style="display:inline-flex;align-items:center;gap:5px;
    background:#f3f4f6;color:#4b5563;border:1px solid #d1d5db;
    padding:4px 10px;border-radius:20px;font-size:0.75rem;font-weight:600;
    letter-spacing:0.02em">
    <span style="width:6px;height:6px;border-radius:50%;background:#9ca3af;display:inline-block"></span>
    Dropped
  </span>`;
} else {
  badgeHtml = `<span style="display:inline-flex;align-items:center;gap:5px;
    background:#f3f4f6;color:#4b5563;border:1px solid #d1d5db;
    padding:4px 10px;border-radius:20px;font-size:0.75rem;font-weight:600">
    ${status}
  </span>`;
}
 
    // ── Actions ──────────────────────────────────────────────────────────────
    let actionsHtml;
    if (status === 'pending') {
      actionsHtml = `
        <div style="display:flex;gap:0.4rem;flex-wrap:wrap">
          <button
            title="View filled form"
            onclick="openEnrollmentDetail(${eid}, '${name.replace(/'/g, "\\'")}')"
            style="font-size:0.78rem;padding:4px 9px;background:#f0f4f0;
                   color:#333;border:1px solid #ddd;border-radius:6px;cursor:pointer;
                   font-weight:500">
            👁 View Form
          </button>
          <button
            title="Approve enrollment"
            onclick="quickApprove(${eid}, '${name.replace(/'/g, "\\'")}')"
            style="font-size:0.78rem;padding:4px 9px;background:#dcfce7;
                   color:#166534;border:1px solid #86efac;border-radius:6px;cursor:pointer;
                   font-weight:600">
            ✓
          </button>
          <button
            title="Reject enrollment"
            onclick="quickReject(${eid}, '${name.replace(/'/g, "\\'")}')"
            style="font-size:0.78rem;padding:4px 9px;background:#fee2e2;
                   color:#991b1b;border:1px solid #fca5a5;border-radius:6px;cursor:pointer;
                   font-weight:600">
            ✕
          </button>
        </div>`;
    } else {
      actionsHtml = `
        <button class="icon-btn" title="Delete"
                onclick="openDeleteTraineeModal(${t.id}, '${name.replace(/'/g, "\\'")}')">🗑️</button>`;
    }
 
    return `
      <tr style="${status === 'pending' ? 'background:#fffdf0;' : ''}">
        <td>
          <div class="trainee-cell">
            <img src="${pic}" alt="" class="mini-avatar">
            <div>
              <div class="trainee-name">${name}</div>
              <div class="trainee-email">${t.email || ''}</div>
            </div>
          </div>
        </td>
        <td>
          <div>${t.phone || '—'}</div>
          <div class="light-txt">${t.address || ''}</div>
        </td>
        <td><div>${t.workshop || '—'}</div></td>
        <td>
          <div>${attended} / ${total} sessions</div>
          <div class="light-txt">${rate} attendance</div>
        </td>
        <td>${badgeHtml}</td>
        <td>${actionsHtml}</td>
      </tr>`;
  }).join('');
}

(function addPendingSortOption() {
  const sel = document.getElementById('tr-sort');
  if (sel && !sel.querySelector('[value="pending"]')) {
    const opt = document.createElement('option');
    opt.value       = 'pending';
    opt.textContent = 'Sort: Pending First';
    sel.appendChild(opt);
  }
})();

function filterTrainees() { renderTraineesTable(); }

async function submitNewTrainee() {
  const first   = document.getElementById('nt-firstname').value.trim();
  const last    = document.getElementById('nt-lastname').value.trim();
  const middle  = document.getElementById('nt-middlename')?.value.trim() || '';
  const email   = document.getElementById('nt-email').value.trim();
  const phone   = document.getElementById('nt-contact').value.trim();
  const errEl   = document.getElementById('add-trainee-error');
  const regionName   = document.getElementById('nt-region')?.selectedOptions[0]?.text || '';
  const provinceName = document.getElementById('nt-province')?.selectedOptions[0]?.text || '';
  const cityName     = document.getElementById('nt-city')?.selectedOptions[0]?.text || '';
  const barangayName = document.getElementById('nt-barangay')?.selectedOptions[0]?.text || '';

  // Collect checked workshops
  const checked = [...document.querySelectorAll('.nt-workshop-cb:checked')].map(cb => parseInt(cb.value));

  // ── Basic field validation ──────────────────────────────
  if (!first || !last || !email) {
    errEl.textContent   = 'First name, last name, and email are required.';
    errEl.style.display = 'block';
    return;
  }

  // ── Address validation ──────────────────────────────────
  buildNTAddress(); // ensure hidden field is current before reading
  if (!validateNTAddress()) {
    errEl.textContent   = 'Please complete all address fields (Region → Province → City → Barangay).';
    errEl.style.display = 'block';
    return;
  }

  const address = document.getElementById('nt-address').value.trim();

  if (!checked.length) {
    errEl.textContent   = 'Please select at least one workshop.';
    errEl.style.display = 'block';
    return;
  }

  errEl.style.display = 'none';

  // ── Loading state ───────────────────────────────────────
  const btn = document.getElementById('nt-submit-btn');
  if (btn) { btn.disabled = true; btn.textContent = 'Adding…'; }

  try {
    const r = await fetch('save_workshops.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action:       'add_trainee',
        first_name:   first,
        last_name:    last,
        middle_name:  middle,
        email,
        phone,
        address,
        region_name:   regionName,
        province_name: provinceName,
        city_name:     cityName,
        barangay_name: barangayName,
        workshop_ids: checked,
      }),
    });
    const d = await r.json();

    if (d.success) {
      closeModal('add-trainee-modal');
      resetNTAddressFields();
      showToast(`✅ ${d.message}`);

      if (!d.sms_sent && d.sms_error) {
        setTimeout(() => showToast(`⚠️ SMS not sent: ${d.sms_error}. Temp password: ${d.tmp_password}`), 2000);
      } else if (!d.sms_sent && d.tmp_password) {
        setTimeout(() => showToast(`🔑 Temp password (no phone): ${d.tmp_password}`), 2000);
      }

      loadTrainees();
    } else {
      errEl.textContent   = d.message;
      errEl.style.display = 'block';
    }
  } catch(e) {
    errEl.textContent   = 'Could not add trainee. Check your connection.';
    errEl.style.display = 'block';
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = 'Add Trainee'; }
  }
}

function openDeleteTraineeModal(userId, name) {
  document.getElementById('delete-trainee-msg').textContent = `Remove ${name} from the system? This cannot be undone.`;
  document.getElementById('delete-trainee-idx').value       = userId;
  openModal('delete-trainee-modal');
}

async function confirmDeleteTrainee() {
  const userId = parseInt(document.getElementById('delete-trainee-idx').value);
  try {
    const r = await fetch('save_workshops.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete_trainee', user_id: userId }),
    });
    const d = await r.json();
    closeModal('delete-trainee-modal');
    showToast(d.success ? `🗑️ ${d.message}` : `❌ ${d.message}`);
    if (d.success) loadTrainees();
  } catch(e) { showToast('Could not delete trainee.'); }
}

/* ── Log Attendance from Trainees tab ────────────────────── */
function resetTRAttModal() {
  document.getElementById('tr-att-step-select').style.display = 'block';
  document.getElementById('tr-att-step-sheet').style.display  = 'none';
  trAttMarks = {};
}

async function loadTRAttendanceSheet() {
  const wsSelect   = document.getElementById('tr-att-workshop');
  const sessSelect = document.getElementById('tr-att-session');
  if (!wsSelect.value || !sessSelect.value) {
    document.getElementById('tr-att-select-error').style.display = 'block'; return;
  }
  document.getElementById('tr-att-select-error').style.display = 'none';

  const workshop = WORKSHOPS_LIST.find(w => w.title === wsSelect.value);
  if (!workshop) { showToast('Workshop not found.'); return; }

  const r  = await fetch(`get_workshops.php?action=sessions&workshop_id=${workshop.id}`);
  const d  = await r.json();
  const sess = (d.sessions || []).find(s => s.session_no == sessSelect.value);
  if (!sess) { showToast('Session not found.'); return; }

  const tr2 = await fetch(`get_workshops.php?action=enrolled&workshop_id=${workshop.id}`);
  const td2 = await tr2.json();
  const trainees = td2.trainees || [];

  document.getElementById('tr-att-title').textContent           = `Attendance · ${wsSelect.value} · Session ${sessSelect.value}`;
  document.getElementById('tr-att-tbody').dataset.sessionId     = sess.id;

  document.getElementById('tr-att-tbody').innerHTML = trainees.map((t, i) => `
    <tr>
      <td>${i+1}</td>
      <td>
        <div class="trainee-cell">
          <img src="${t.profile_pic ? '/UGAT/'+t.profile_pic : `https://ui-avatars.com/api/?name=${encodeURIComponent(t.name)}&size=32&background=4B8423&color=fff`}" class="mini-avatar">
          <span>${t.name}</span>
        </div>
      </td>
      <td>${t.phone || '—'}</td>
      <td>—</td>
      <td>
        <div class="att-btn-group">
          <button class="att-btn" onclick="markTRAtt(this,'present',${t.id})">Present</button>
          <button class="att-btn" onclick="markTRAtt(this,'late',${t.id})">Late</button>
          <button class="att-btn" onclick="markTRAtt(this,'absent',${t.id})">Absent</button>
        </div>
      </td>
    </tr>`).join('');

  document.getElementById('tr-att-step-select').style.display = 'none';
  document.getElementById('tr-att-step-sheet').style.display  = 'block';
  updateTRAttSummary();
}

function markTRAtt(btn, status, userId) {
  btn.closest('.att-btn-group').querySelectorAll('.att-btn').forEach(b => b.className = 'att-btn');
  btn.classList.add('selected-' + status);
  trAttMarks[userId] = status;
  updateTRAttSummary();
}

function updateTRAttSummary() {
  const c = { present:0, late:0, absent:0 };
  Object.values(trAttMarks).forEach(s => c[s]++);
  document.getElementById('tr-att-present').textContent = c.present;
  document.getElementById('tr-att-late').textContent    = c.late;
  document.getElementById('tr-att-absent').textContent  = c.absent;
}

function backToTRSelector() {
  document.getElementById('tr-att-step-select').style.display = 'block';
  document.getElementById('tr-att-step-sheet').style.display  = 'none';
  trAttMarks = {};
}

async function saveTRAttendance() {
  const total = Object.keys(trAttMarks).length;
  if (!total) { showToast('Mark at least one trainee.'); return; }
  const session_id = parseInt(document.getElementById('tr-att-tbody').dataset.sessionId);
  const records    = Object.entries(trAttMarks).map(([user_id, status]) => ({ user_id: parseInt(user_id), status }));

  try {
    const r = await fetch('save_workshops.php', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'save_attendance', session_id, records }),
    });
    const d = await r.json();
    closeModal('tr-attendance-modal');
    showToast(d.success ? `✅ ${d.message}` : `❌ ${d.message}`);
    if (d.newly_flagged > 0) {
      setTimeout(() => showToast(`🎓 ${d.newly_flagged} trainee(s) now eligible for certification!`), 1500);
    }
  } catch(e) { showToast('Could not save attendance.'); }
}

/* =============================================================================
   TAB 3 — ATTENDANCE (from DB)
   ============================================================================= */
async function loadAttendance() {
  try {
    const r = await fetch('get_workshops.php?action=attendance');
    const d = await r.json();
    if (!d.success) return;
    ATTENDANCE_DATA = d.attendance;
    renderAttTraineeView(ATTENDANCE_DATA);
    updateAttStatCards(ATTENDANCE_DATA);
  } catch(e) { console.error('loadAttendance error:', e); }
}

function applyAttFilters() {
  const search = document.getElementById('att-search')?.value.trim().toLowerCase() || '';
  const list   = ATTENDANCE_DATA.filter(r => {
    return !search ||
      (r.trainee_name||'').toLowerCase().includes(search) ||
      (r.workshop||'').toLowerCase().includes(search);
  });
  renderAttTraineeView(list);
  updateAttStatCards(list);
}

function switchAttView(mode, btn) {
  document.querySelectorAll('#tab-attendance .filter-btns .tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  ['trainee','workshop','session'].forEach(v => {
    const el = document.getElementById(`att-view-${v}`);
    if (el) el.style.display = v === mode ? '' : 'none';
  });
}

function filterAttByDate(range, btn) {
  ['today','yesterday','all'].forEach(d => { document.getElementById(`att-date-${d}`)?.classList.remove('active'); });
  btn.classList.add('active');
}

function updateAttStatCards(list) {
  let present=0, absent=0, late=0, total=0;
  list.forEach(r => {
    total   += parseInt(r.total_sessions) || 0;
    present += parseInt(r.present)        || 0;
    absent  += parseInt(r.absent)         || 0;
    late    += parseInt(r.late)           || 0;
  });
  const pct = total > 0 ? Math.round((present/total)*100) : 0;
  document.getElementById('att-stat-total').textContent   = total;
  document.getElementById('att-stat-present').textContent = present;
  document.getElementById('att-stat-pct').textContent     = `● ${pct}% overall rate`;
  document.getElementById('att-stat-absent').textContent  = absent;
  document.getElementById('att-stat-late').textContent    = late;
}

function renderAttTraineeView(list) {
  const tbody = document.getElementById('att-trainee-tbody');
  if (!tbody) return;

  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:2rem;color:#aaa">No attendance records yet.</td></tr>`;
    return;
  }

  tbody.innerHTML = list.map(r => {
    const name = r.trainee_name || '—';
    const pic  = r.profile_pic
      ? '/UGAT/' + r.profile_pic
      : `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&size=32&background=4B8423&color=fff`;
    const rate = parseInt(r.rate) || 0;
    return `
      <tr>
        <td>
          <div class="trainee-cell">
            <img src="${pic}" class="mini-avatar">
            <div>
              <div class="trainee-name">${name}</div>
              <div class="trainee-email">${r.email||''}</div>
            </div>
          </div>
        </td>
        <td>${r.workshop || '—'}</td>
        <td>—</td>
        <td>
          <div class="progress-bar"><div class="progress-fill" style="width:${rate}%"></div></div>
          <span class="light-txt">${r.present||0} / ${r.total_sessions||0}</span>
        </td>
        <td>
          <span style="font-weight:700;color:${rate>=75?'var(--color-primary)':rate>=50?'var(--color-warning)':'var(--color-danger)'}">
            ${rate}%
          </span>
        </td>
        <td>—</td>
        <td>—</td>
      </tr>`;
  }).join('');
}

function doAttExport() {
  const format = document.getElementById('att-exp-format')?.value || 'csv';
  const from   = document.getElementById('att-exp-from')?.value  || '';
  const to     = document.getElementById('att-exp-to')?.value    || '';
  closeModal('att-export-modal');
  showToast('📥 Preparing attendance ' + format.toUpperCase() + ' export…');
  const params = new URLSearchParams({
    sections: 'attendance',
    format,
    period: (from || to) ? 'custom' : 'all',
  });
  if (from) params.set('from', from);
  if (to)   params.set('to', to);
  window.open('export_reports.php?' + params.toString(), '_blank');
}

/* =============================================================================
   TAB 4 — CERTIFICATIONS (from DB)
   ============================================================================= */
async function loadCertifications() {
  try {
    // Retroactively flag any trainees who attended past sessions but weren't caught yet
    await fetch('save_workshops.php', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'recheck_eligibility' }),
    });
  } catch(e) { /* non-fatal */ }

  try {
    const r = await fetch('get_workshops.php?action=certifications');
    const d = await r.json();
    if (!d.success) return;
    ELIGIBLE = d.eligible;
    ISSUED   = d.issued;
    renderEligibleTable();
    renderIssuedTable();
    document.getElementById('cert-stat-issued').textContent    = ISSUED.length;
    document.getElementById('cert-stat-eligible').textContent  = ELIGIBLE.length;
    document.getElementById('cert-eligible-count').textContent = `${ELIGIBLE.length} trainee(s) awaiting issuance`;
  } catch(e) { console.error('loadCertifications error:', e); }
}

function filterEligible() { renderEligibleTable(); }
function filterIssued()   { renderIssuedTable(); }

function renderEligibleTable() {
  const search = document.getElementById('cert-elig-search')?.value.trim().toLowerCase() || '';
  const list   = ELIGIBLE.filter(t =>
    !search ||
    (t.trainee_name||'').toLowerCase().includes(search) ||
    (t.workshop||'').toLowerCase().includes(search)
  );
  const tbody = document.getElementById('cert-eligible-tbody');
  if (!tbody) return;

  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:2rem;color:#aaa">No eligible trainees.</td></tr>`;
    return;
  }

  tbody.innerHTML = list.map(t => {
    const name = t.trainee_name || '—';
    const pic  = t.profile_pic
      ? '/UGAT/' + t.profile_pic
      : `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&size=32&background=4B8423&color=fff`;
    const rate = parseInt(t.rate) || 0;
    return `
      <tr>
        <td>
          <div class="trainee-cell">
            <img src="${pic}" class="mini-avatar">
            <div>
              <div class="trainee-name">${name}</div>
              <div class="trainee-email">${t.email||''}</div>
            </div>
          </div>
        </td>
        <td>${t.workshop || '—'}</td>
        <td>${t.sessions_done || 0} / ${t.total_sessions || 0}</td>
        <td class="${rate>=75?'rate-green':'rate-orange'}">${rate}%</td>
        <td>${t.completed_on
          ? new Date(t.completed_on).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})
          : '—'}</td>
        <td>
          <button class="btn-issue"
                  onclick="openIssueConfirm('${t.user_id}','${t.workshop_id}','${name}','${t.workshop}',${rate},'${t.phone||''}')">
            Issue Certificate
          </button>
        </td>
      </tr>`;
  }).join('');
}

function renderIssuedTable() {
  const search = document.getElementById('cert-issued-search')?.value.trim().toLowerCase() || '';
  const list   = ISSUED.filter(c =>
    !search ||
    (c.trainee_name||'').toLowerCase().includes(search) ||
    (c.workshop||'').toLowerCase().includes(search)     ||
    (c.cert_no||'').toLowerCase().includes(search)
  );
  const tbody = document.getElementById('cert-issued-tbody');
  if (!tbody) return;

  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:2rem;color:#aaa">No certificates issued yet.</td></tr>`;
    return;
  }

  tbody.innerHTML = list.map(c => {
    const name = c.trainee_name || '—';
    const pic  = c.profile_pic
      ? '/UGAT/' + c.profile_pic
      : `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&size=32&background=4B8423&color=fff`;
    const date = c.issued_at
      ? new Date(c.issued_at).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})
      : '—';
    return `
      <tr>
        <td>
          <div class="trainee-cell">
            <img src="${pic}" class="mini-avatar">
            <div>
              <div class="trainee-name">${name}</div>
              <div class="trainee-email">${c.email||''}</div>
            </div>
          </div>
        </td>
        <td>${c.workshop || '—'}</td>
        <td><span style="font-family:monospace;color:#4B8423;font-weight:600">${c.cert_no || '—'}</span></td>
        <td>${date}</td>
        <td><span class="badge badge-issued">Issued</span></td>
        <td><button class="btn-sm" onclick="openViewCert(${c.id})">View</button></td>
      </tr>`;
  }).join('');

  document.getElementById('cert-issued-count').textContent =
    `Showing ${list.length} of ${ISSUED.length} issued certificates`;
}

function openIssueConfirm(userId, workshopId, name, workshop, rate, phone) {
  pendingIssuanceId = { user_id: parseInt(userId), workshop_id: parseInt(workshopId), name, workshop, rate, phone };
  document.getElementById('cert-confirm-details').innerHTML = `
    <div class="confirm-info">
      <span class="confirm-name">${name}</span>
      <span class="confirm-meta">${workshop}</span>
      <span class="confirm-meta">Attendance rate: ${rate}%</span>
    </div>`;
  document.getElementById('cert-sms-preview').innerHTML = `
    📱 <strong>SMS Preview:</strong><br>
    <span style="font-size:var(--text-body-sm)">
      "Congratulations, ${name}! Your certificate for ${workshop} has been issued by UGAT Integrated Farm."
    </span>`;
  document.getElementById('cert-confirm-contact').value = phone;
  openModal('cert-issue-modal');
}

async function confirmIssuance() {
  if (!pendingIssuanceId) return;
  try {
    const r = await fetch('save_workshops.php', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action:      'issue_certificate',
        user_id:     pendingIssuanceId.user_id,
        workshop_id: pendingIssuanceId.workshop_id,
      }),
    });
    const d = await r.json();
    closeModal('cert-issue-modal');
    showToast(d.success ? `✅ ${d.message}` : `❌ ${d.message}`);
    if (d.success) loadCertifications();
  } catch(e) { showToast('Could not issue certificate.'); }
}

function _buildCertHTML(c) {
  const dt = c.issued_at ? new Date(c.issued_at) : new Date();
  const day   = dt.getDate();
  const month = dt.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
  const issuedFmt = dt.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }).toUpperCase();
  const certNo = c.cert_no || '—';
  const name   = c.trainee_name || '—';
  const ws     = c.workshop || '—';

  const leafSVG = `<svg width="52" height="200" viewBox="0 0 52 200" xmlns="http://www.w3.org/2000/svg">
    <line x1="26" y1="5" x2="26" y2="195" stroke="#7ab050" stroke-width="1.3" opacity="0.55"/>
    <ellipse cx="26" cy="35"  rx="12" ry="23" fill="#a8d070" opacity="0.5" transform="rotate(-20 26 35)"/>
    <ellipse cx="26" cy="75"  rx="14" ry="25" fill="#90c060" opacity="0.45" transform="rotate(14 26 75)"/>
    <ellipse cx="26" cy="115" rx="12" ry="22" fill="#a8d070" opacity="0.42" transform="rotate(-14 26 115)"/>
    <ellipse cx="26" cy="152" rx="13" ry="21" fill="#90c060" opacity="0.34" transform="rotate(8 26 152)"/>
  </svg>`;

  const leafSVGR = `<svg width="52" height="200" viewBox="0 0 52 200" xmlns="http://www.w3.org/2000/svg">
    <line x1="26" y1="5" x2="26" y2="195" stroke="#7ab050" stroke-width="1.3" opacity="0.55"/>
    <ellipse cx="26" cy="35"  rx="12" ry="23" fill="#a8d070" opacity="0.5" transform="rotate(20 26 35)"/>
    <ellipse cx="26" cy="75"  rx="14" ry="25" fill="#90c060" opacity="0.45" transform="rotate(-14 26 75)"/>
    <ellipse cx="26" cy="115" rx="12" ry="22" fill="#a8d070" opacity="0.42" transform="rotate(14 26 115)"/>
    <ellipse cx="26" cy="152" rx="13" ry="21" fill="#90c060" opacity="0.34" transform="rotate(-8 26 152)"/>
  </svg>`;

  const sealSVG = `<svg width="86" height="86" viewBox="0 0 86 86" xmlns="http://www.w3.org/2000/svg">
    <circle cx="43" cy="43" r="41" fill="#1e4d0f"/>
    <circle cx="43" cy="43" r="36" fill="none" stroke="#8dc63f" stroke-width="1" stroke-dasharray="3,2"/>
    <defs>
      <path id="ct" d="M13,43 A30,30 0 0,1 73,43"/>
      <path id="cb" d="M18,57 A28,28 0 0,0 68,57"/>
    </defs>
    <text fill="#8dc63f" font-size="7.5" letter-spacing="1.8" font-family="Arial,sans-serif" font-weight="bold">
      <textPath href="#ct" startOffset="4%">UGAT TRAINTRACK</textPath>
    </text>
    <text fill="#8dc63f" font-size="7" letter-spacing="1.5" font-family="Arial,sans-serif" font-weight="bold">
      <textPath href="#cb" startOffset="8%">OFFICIAL SEAL</textPath>
    </text>
    <path d="M43 24 C52 29 56 39 43 53 C30 39 34 29 43 24Z" fill="#8dc63f"/>
    <line x1="43" y1="53" x2="43" y2="62" stroke="#8dc63f" stroke-width="1.8"/>
  </svg>`;

  const logoSVG = `<svg width="34" height="34" viewBox="0 0 34 34" xmlns="http://www.w3.org/2000/svg">
    <path d="M17 3 C24 8 28 17 17 28 C6 17 10 8 17 3Z" fill="#8dc63f"/>
    <line x1="17" y1="28" x2="17" y2="32" stroke="#8dc63f" stroke-width="1.8"/>
  </svg>`;

  const S = (s) => `style="${s}"`;

  return `<div ${S('background:#f4f9ee;border:3px solid #1e4d0f;outline:2px solid #4B8423;outline-offset:-9px;font-family:Georgia,"Times New Roman",serif;position:relative;overflow:hidden;')}>

    <!-- Header -->
    <div ${S('background:#1e4d0f;color:#fff;padding:10px 16px;display:flex;justify-content:space-between;align-items:center;border-bottom:3px solid #c9a227;')}>
      <div ${S('display:flex;align-items:center;gap:10px;')}>
        ${logoSVG}
        <div>
          <div ${S('font-size:15px;font-weight:700;letter-spacing:0.02em;line-height:1.25;')}>UGAT Integrated Farm</div>
          <div ${S('font-size:8px;letter-spacing:0.09em;opacity:0.82;margin-top:2px;font-family:Arial,sans-serif;')}>AGRICULTURAL TRAINING &amp; DEVELOPMENT &nbsp;&middot;&nbsp; SAN ISIDRO, DAET, CAMARINES NORTE</div>
        </div>
      </div>
      <div ${S('text-align:right;font-size:9px;letter-spacing:0.06em;line-height:2;font-family:Arial,sans-serif;font-weight:600;')}>
        ISSUED: ${issuedFmt}<br>CERT. NO.: ${certNo}
      </div>
    </div>

    <!-- Body -->
    <div ${S('display:flex;align-items:stretch;padding:18px 0;')}>
      <div ${S('width:60px;display:flex;align-items:center;justify-content:center;flex-shrink:0;opacity:0.72;')}>${leafSVG}</div>

      <div ${S('flex:1;text-align:center;padding:0 6px;')}>
        <p ${S('font-style:italic;color:#777;font-size:10.5px;margin-bottom:4px;')}>This is to certify that</p>
        <div ${S('margin-bottom:22px;')}>
          <div ${S('font-size:22px;font-weight:700;color:#1e4d0f;display:inline-block;padding-bottom:4px;border-bottom:2.5px solid #c9a227;letter-spacing:0.01em;')}>Certificate of Completion</div>
        </div>
        <div ${S('font-size:30px;font-weight:700;font-style:italic;color:#1e4d0f;line-height:1.15;margin-bottom:0;')}>${name}</div>
        <div ${S('width:55%;height:1.5px;background:#4B8423;margin:10px auto 14px;')}></div>
        <p ${S('font-size:10px;color:#444;margin-bottom:6px;line-height:1.55;')}>has successfully completed all requirements and demonstrated satisfactory competence in</p>
        <div ${S('font-size:17px;font-weight:700;color:#1e4d0f;margin-bottom:3px;')}>${ws}</div>
        <div ${S('font-size:7.5px;letter-spacing:0.22em;color:#888;margin-bottom:8px;font-family:Arial,sans-serif;')}>UGAT TRAINTRACK CERTIFIED PROGRAM</div>
        <div ${S('border-top:1.5px dashed #aaa;width:75%;margin:8px auto;')}></div>
        <p ${S('font-size:9.5px;font-style:italic;color:#555;margin-bottom:5px;line-height:1.55;')}>Awarded in recognition of dedicated participation, practical learning, and commitment to sustainable agriculture.</p>
        <p ${S('font-size:10px;color:#333;margin-bottom:12px;')}><em>Given this <strong>${day}</strong> day of <strong>${month}</strong> at <strong>San Isidro, Daet, Camarines Norte.</strong></em></p>

        <div ${S('display:flex;justify-content:center;margin:4px 0 14px;')}>${sealSVG}</div>

        <div ${S('display:flex;justify-content:space-between;padding:0 24px;')}>
          <div ${S('text-align:center;width:130px;')}>
            <div ${S('height:1px;background:#1e4d0f;margin-bottom:5px;')}></div>
            <div ${S('font-size:7.5px;letter-spacing:0.1em;color:#222;font-family:Arial,sans-serif;text-transform:uppercase;')}>Program Coordinator</div>
            <div ${S('font-size:8px;color:#666;font-style:italic;')}>UGAT Integrated Farm</div>
          </div>
          <div ${S('text-align:center;width:130px;')}>
            <div ${S('height:1px;background:#1e4d0f;margin-bottom:5px;')}></div>
            <div ${S('font-size:7.5px;letter-spacing:0.1em;color:#222;font-family:Arial,sans-serif;text-transform:uppercase;')}>Farm Director</div>
            <div ${S('font-size:8px;color:#666;font-style:italic;')}>UGAT Integrated Farm</div>
          </div>
        </div>
      </div>

      <div ${S('width:60px;display:flex;align-items:center;justify-content:center;flex-shrink:0;opacity:0.72;')}>${leafSVGR}</div>
    </div>

    <!-- Corner dots -->
    <div ${S('position:absolute;bottom:34px;left:0;right:0;display:flex;justify-content:space-between;padding:0 10px;pointer-events:none;')}>
      <div ${S('width:8px;height:8px;border-radius:50%;background:#333;')}></div>
      <div ${S('width:8px;height:8px;border-radius:50%;background:#333;')}></div>
    </div>

    <!-- Footer -->
    <div ${S('background:#1e4d0f;color:#fff;text-align:center;padding:7px 8px;font-family:Arial,sans-serif;')}>
      <div ${S('font-size:8.5px;letter-spacing:0.14em;font-weight:600;')}>UGAT TRAINTRACK SYSTEM &nbsp;&middot;&nbsp; AGRICULTURAL TRAINING MANAGEMENT</div>
      <div ${S('font-size:7px;opacity:0.72;margin-top:2px;')}>This certificate is an official document of UGAT Integrated Farm. For verification, contact the program office.</div>
    </div>
  </div>`;
}

function openViewCert(id) {
  const c = ISSUED.find(x => x.id == id); if (!c) return;
  viewingCertId = id;
  const certNo  = c.cert_no || '—';
  const issuedFmt = c.issued_at
    ? new Date(c.issued_at).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})
    : '—';
  document.getElementById('cert-view-sub').textContent = `${certNo} · Issued ${issuedFmt}`;
  document.getElementById('cert-preview-body').innerHTML = _buildCertHTML(c);
  openModal('cert-view-modal');
}

async function downloadCertificate(type) {
  const c = ISSUED.find(x => x.id == viewingCertId); if (!c) return;
  const certEl = document.querySelector('#cert-preview-body > div');
  if (!certEl) return;
  const filename = 'UGAT_Certificate_' + (c.trainee_name || 'Trainee').replace(/\s+/g, '_');
  await _downloadCertAs(certEl, type || 'pdf', filename);
}

async function _downloadCertAs(certEl, type, filename) {
  showToast('⏳ Generating ' + (type === 'pdf' ? 'PDF' : 'image') + '…');
  try {
    const canvas = await html2canvas(certEl, {
      scale: 2, useCORS: true, logging: false, backgroundColor: '#f4f9ee',
    });
    if (type === 'png') {
      canvas.toBlob(function(blob) {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = filename + '.png';
        document.body.appendChild(a); a.click();
        document.body.removeChild(a); URL.revokeObjectURL(url);
        showToast('✅ Image saved!');
      }, 'image/png');
    } else {
      const imgData = canvas.toDataURL('image/jpeg', 0.95);
      const { jsPDF } = window.jspdf;
      const w = certEl.offsetWidth, h = certEl.offsetHeight;
      const pdf = new jsPDF({ orientation: w > h ? 'l' : 'p', unit: 'px', format: [w, h] });
      pdf.addImage(imgData, 'JPEG', 0, 0, w, h);
      pdf.save(filename + '.pdf');
      showToast('✅ PDF downloaded!');
    }
  } catch(e) {
    console.error('Certificate download error:', e);
    showToast('❌ Could not generate file. Please try again.');
  }
}

function _openCertPrintWindow(c) {
  const html = `<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>Certificate – ${c.trainee_name || ''}</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.ugat-cert{background:#f4f9ee;border:3px solid #1e4d0f;outline:2px solid #4B8423;outline-offset:-9px;font-family:Georgia,'Times New Roman',serif;position:relative;overflow:hidden;width:780px}
.ugat-cert-header{background:#1e4d0f;color:#fff;padding:.7rem 1.1rem;display:flex;justify-content:space-between;align-items:center;border-bottom:3px solid #c9a227}
.ugat-cert-header-left{display:flex;align-items:center;gap:.6rem}
.ugat-cert-org{font-size:1rem;font-weight:700;letter-spacing:.02em;line-height:1.2}
.ugat-cert-org-sub{font-size:.55rem;letter-spacing:.09em;opacity:.8;margin-top:2px;font-family:Arial,sans-serif}
.ugat-cert-header-right{text-align:right;font-size:.6rem;letter-spacing:.06em;line-height:2;font-family:Arial,sans-serif;font-weight:600}
.ugat-cert-body{display:flex;align-items:stretch;padding:1.2rem 0}
.ugat-cert-leaves{width:56px;display:flex;align-items:center;justify-content:center;flex-shrink:0;opacity:.75}
.ugat-cert-content{flex:1;text-align:center;padding:0 .25rem}
.ugat-cert-certify{font-style:italic;color:#777;font-size:.72rem;margin-bottom:.2rem}
.ugat-cert-completion-title{font-size:1.5rem;font-weight:700;color:#1e4d0f;border-bottom:2px solid #c9a227;display:inline-block;padding-bottom:3px;margin-bottom:.6rem;letter-spacing:.01em}
.ugat-cert-name{font-size:2rem;font-weight:700;font-style:italic;color:#1e4d0f;margin-bottom:.2rem;line-height:1.15}
.ugat-cert-name-rule{width:55%;height:1.5px;background:#4B8423;margin:0 auto .65rem}
.ugat-cert-desc{font-size:.7rem;color:#444;margin-bottom:.4rem;line-height:1.5}
.ugat-cert-workshop-name{font-size:1.15rem;font-weight:700;color:#1e4d0f;margin-bottom:.15rem}
.ugat-cert-program-label{font-size:.5rem;letter-spacing:.2em;color:#888;margin-bottom:.5rem;font-family:Arial,sans-serif}
.ugat-cert-dashed{border-top:1.5px dashed #aaa;width:75%;margin:.5rem auto}
.ugat-cert-awarded{font-size:.65rem;font-style:italic;color:#555;margin-bottom:.35rem;line-height:1.5}
.ugat-cert-given{font-size:.68rem;color:#333;margin-bottom:.8rem}
.ugat-cert-seal-wrap{display:flex;justify-content:center;margin:.1rem 0 .9rem}
.ugat-cert-sigs{display:flex;justify-content:space-between;padding:0 1.5rem}
.ugat-cert-sig{text-align:center;width:130px}
.ugat-cert-sig-line{height:1px;background:#1e4d0f;margin-bottom:.3rem}
.ugat-cert-sig-role{font-size:.52rem;letter-spacing:.1em;color:#222;font-family:Arial,sans-serif;text-transform:uppercase}
.ugat-cert-sig-org{font-size:.55rem;color:#666;font-style:italic}
.ugat-cert-footer{background:#1e4d0f;color:#fff;text-align:center;padding:.45rem .5rem;font-family:Arial,sans-serif}
.ugat-cert-footer-top{font-size:.58rem;letter-spacing:.13em;font-weight:600}
.ugat-cert-footer-bot{font-size:.46rem;opacity:.72;margin-top:2px}
.ugat-cert-corners{position:absolute;bottom:2.15rem;left:0;right:0;display:flex;justify-content:space-between;padding:0 .65rem;pointer-events:none}
.ugat-cert-corner-dot{width:8px;height:8px;border-radius:50%;background:#333}
@media print{body{padding:0;min-height:unset}.ugat-cert{width:100%}.no-print{display:none!important}}
</style></head><body>
<div class="no-print" style="position:fixed;top:16px;right:16px;z-index:99;display:flex;gap:8px">
  <button onclick="window.print()" style="background:#1e4d0f;color:#fff;border:none;padding:8px 18px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600">Print / Save PDF</button>
  <button onclick="window.close()" style="background:#eee;color:#333;border:none;padding:8px 14px;border-radius:6px;cursor:pointer;font-size:13px">Close</button>
</div>
${_buildCertHTML(c)}
</body></html>`;
  const w = window.open('', '_blank', 'width=860,height=640');
  w.document.write(html);
  w.document.close();
}

function resendCertSMS() { showToast('📱 SMS resend feature coming soon.'); }

function exportAllCertificates() {
  showCertExportChoice('export_certificates.php');
}

function showCertExportChoice(url) {
  const existing = document.getElementById('cert-choice-modal');
  if (existing) existing.remove();

  const modal = document.createElement('div');
  modal.id = 'cert-choice-modal';
  modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9999;display:flex;align-items:center;justify-content:center;';
  modal.innerHTML = `
    <div style="background:#fff;border-radius:12px;padding:2rem;max-width:360px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.2)">
      <h3 style="font-size:1.05rem;font-weight:700;margin-bottom:0.25rem">Export Certificates</h3>
      <p style="font-size:0.83rem;color:#666;margin-bottom:1.5rem">Choose export format</p>
      <div style="display:flex;flex-direction:column;gap:0.75rem">
        <button onclick="doCertExportChoice('${url}','pdf')"
                style="background:#4B8423;color:#fff;border:none;padding:0.75rem 1rem;border-radius:8px;cursor:pointer;font-size:0.9rem;font-weight:600">
          ⬇ Download PDF
        </button>
        <button onclick="doCertExportChoice('${url}','csv')"
                style="background:#fff;color:#333;border:1.5px solid #ddd;padding:0.75rem 1rem;border-radius:8px;cursor:pointer;font-size:0.9rem;font-weight:600">
          ⬇ Download CSV
        </button>
      </div>
      <button onclick="document.getElementById('cert-choice-modal').remove()"
              style="margin-top:1rem;width:100%;background:none;border:none;color:#999;cursor:pointer;font-size:0.82rem;padding:0.5rem">
        Cancel
      </button>
    </div>`;
  modal.addEventListener('click', e => { if (e.target === modal) modal.remove(); });
  document.body.appendChild(modal);
}

function doCertExportChoice(url, format) {
  document.getElementById('cert-choice-modal')?.remove();
  const sep = url.includes('?') ? '&' : '?';
  const finalUrl = format === 'csv'
    ? url + sep + 'format=csv'
    : url + sep + 'format=pdf';
  showToast(format === 'csv' ? '📥 Downloading CSV...' : '📥 Opening PDF...');
  window.open(finalUrl, '_blank');
}

function toggleExportScopeFields() {
  const scope = document.getElementById('cert-exp-scope')?.value || 'all';
  document.getElementById('cert-exp-workshop-wrap').style.display = scope === 'workshop' ? '' : 'none';
  document.getElementById('cert-exp-trainee-wrap').style.display  = scope === 'trainee'  ? '' : 'none';
}

function doCertExport() {
  const scope    = document.getElementById('cert-exp-scope')?.value || 'all';
  const fromDate = document.getElementById('cert-exp-from')?.value  || '';
  const toDate   = document.getElementById('cert-exp-to')?.value    || '';

  if (scope === 'workshop' && !document.getElementById('cert-exp-workshop-id')?.value) {
    showToast('Please select a specific workshop.'); return;
  }

  let url = 'export_certificates.php?scope=' + encodeURIComponent(scope);
  if (fromDate) url += '&from=' + encodeURIComponent(fromDate);
  if (toDate)   url += '&to='   + encodeURIComponent(toDate);
  if (scope === 'workshop') url += '&workshop_id=' + (document.getElementById('cert-exp-workshop-id')?.value || '');
  if (scope === 'trainee') {
    const ids = [...document.querySelectorAll('.export-trainee-cb:checked')].map(cb => cb.value);
    if (ids.length) url += '&user_ids=' + encodeURIComponent(ids.join(','));
  }

  closeModal('cert-export-modal');
  showCertExportChoice(url);
}

let _exportTrainees = [];

async function loadExportOptions() {
  try {
    const r = await fetch('export_certificates.php?get_options=1');
    const d = await r.json();

    const wsSel = document.getElementById('cert-exp-workshop-id');
    if (wsSel) {
      wsSel.innerHTML = '<option value="">Select workshop</option>';
      (d.workshops || []).forEach(w => {
        wsSel.innerHTML += `<option value="${w.id}">${w.title}</option>`;
      });
    }

    _exportTrainees = d.trainees || [];
    renderExportTrainees(_exportTrainees);
  } catch(e) { console.error('loadExportOptions error:', e); }
}

function renderExportTrainees(list) {
  const container = document.getElementById('cert-exp-trainee-list');
  if (!container) return;

  if (!list.length) {
    container.innerHTML = '<p style="padding:0.5rem 1rem;color:#aaa;font-size:0.82rem">No trainees found.</p>';
    return;
  }

  container.innerHTML = list.map(t => `
    <label style="display:flex;align-items:center;gap:0.6rem;padding:0.45rem 0.85rem;cursor:pointer;border-bottom:1px solid #f0f0f0;font-size:0.83rem"
           onmouseover="this.style.background='#f5fbf2'" onmouseout="this.style.background=''">
      <input type="checkbox" class="export-trainee-cb" value="${t.id}"
             onchange="updateExportTraineeCount()"
             style="width:15px;height:15px;accent-color:#4B8423;cursor:pointer">
      <span>${t.name}</span>
      <span style="margin-left:auto;font-size:0.75rem;color:#999">${t.email || ''}</span>
    </label>`).join('');

  updateExportTraineeCount();
}

function filterExportTrainees() {
  const q        = document.getElementById('cert-exp-trainee-search')?.value.trim().toLowerCase() || '';
  const filtered = _exportTrainees.filter(t =>
    !q || t.name.toLowerCase().includes(q) || (t.email || '').toLowerCase().includes(q)
  );
  renderExportTrainees(filtered);
}

function updateExportTraineeCount() {
  const checked = document.querySelectorAll('.export-trainee-cb:checked').length;
  const countEl = document.getElementById('cert-exp-trainee-count');
  if (countEl) countEl.textContent = checked === 0
    ? '0 selected (exports all trainees)'
    : `${checked} trainee${checked > 1 ? 's' : ''} selected`;
}

/* =============================================================================
   INIT
   ============================================================================= */
document.addEventListener('DOMContentLoaded', function () {

  // ── Phone formatter ───────────────────────────────────────
  setupPhoneField('nt-contact');

  // ── Load regions for address dropdowns ───────────────────
  loadNTRegions();

  // ── Populate workshop dropdowns + checkboxes ─────────────
  fetch('get_workshops.php?action=workshops').then(r => r.json()).then(d => {
    if (!d.success) return;

    // Regular selects (attendance modals)
    ['tr-att-workshop','et-workshop'].forEach(id => {
      const sel = document.getElementById(id);
      if (!sel) return;
      d.workshops.forEach(w => {
        const opt = document.createElement('option');
        opt.value = w.title; opt.textContent = w.title;
        sel.appendChild(opt);
      });
    });

    // Checkbox list inside Add Trainee workshop dropdown
    const container = document.getElementById('nt-workshop-list');
    if (container && d.workshops.length) {
      container.innerHTML = d.workshops.map(w => `
        <label class="ws-checkbox-item" onclick="event.stopPropagation()">
          <input type="checkbox" class="nt-workshop-cb" value="${w.id}"
                 onchange="toggleWSCheckbox(${w.id}, '${w.title.replace(/'/g,"\\'")}', this)">
          <span class="ws-checkbox-label">
            <strong>${w.title}</strong>
            <span class="ws-checkbox-meta">${w.category || ''} · ${w.status}</span>
          </span>
        </label>`).join('');
    } else if (container) {
      container.innerHTML = '<p style="color:#aaa;padding:0.75rem 1rem;font-size:0.85rem">No workshops available.</p>';
    }
  });

  // ── Add Trainee modal — reset on backdrop click ───────────
  document.getElementById('add-trainee-modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
      // Close workshop dropdown if open
      document.getElementById('nt-workshop-list').style.display = 'none';
      ntWsOpen = false;
      // Reset address fields
      resetNTAddressFields();
    }
  });

  // ── Resolve initial tab from URL ─────────────────────────
  const urlTab = new URLSearchParams(window.location.search).get('tab');
  const tab    = (urlTab && ['workshops','trainees','attendance','certifications'].includes(urlTab))
    ? urlTab
    : 'workshops';
  const btn = document.querySelector(`[data-tab="${tab}"]`);
  if (btn) switchPMTab(tab, btn);
});