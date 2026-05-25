/**
 * AdminTrainees.js — UGAT TrainTrack
 */

let TRAINEES = [
  { id: 1, name: 'Gwen Stacy',    email: 'gwenstacy@gmail.com', contact: '+63 912 345 6789', address: 'Daet, Cam. Norte', workshop: 'Organic Urban Gardening',  workshopDate: 'Apr 15, 2026', sessions: 2, totalSessions: 3, attendance: '67%',  status: 'Enrolled',  avatar: 'https://i.pravatar.cc/32?img=1' },
  { id: 2, name: 'Peter Parker',  email: 'spidey@gmail.com',    contact: '+63 917 234 5678', address: 'Daet, Cam. Norte', workshop: 'Seed Saving & Propagation', workshopDate: 'Apr 22, 2026', sessions: 3, totalSessions: 3, attendance: '100%', status: 'Certified', avatar: 'https://i.pravatar.cc/32?img=3' },
  { id: 3, name: 'Andie Anderson',email: 'luvandie@gmail.com',  contact: '+63 917 234 5678', address: 'Daet, Cam. Norte', workshop: 'Seed Saving & Propagation', workshopDate: 'Apr 22, 2026', sessions: 3, totalSessions: 3, attendance: '100%', status: 'Certified', avatar: 'https://i.pravatar.cc/32?img=9' },
  { id: 4, name: 'Tony Stark',    email: 'jarvisnfri@gmail.com',contact: '+63 917 234 5678', address: 'Daet, Cam. Norte', workshop: 'Organic Urban Gardening',  workshopDate: 'Apr 15, 2026', sessions: 1, totalSessions: 3, attendance: '33%',  status: 'Enrolled',  avatar: 'https://i.pravatar.cc/32?img=7' },
];

const ATTENDANCE_DATA = [
  { num: '01', name: 'Gwen Stacy',                 avatar: 'https://i.pravatar.cc/32?img=1',  contact: '+63 912 345 6789', prev: 'Present', prevClass: 'present-stat' },
  { num: '02', name: 'Peter Parker',                avatar: 'https://i.pravatar.cc/32?img=3',  contact: '+63 917 234 5678', prev: 'Present', prevClass: 'present-stat' },
  { num: '03', name: 'Andie Anderson',              avatar: 'https://i.pravatar.cc/32?img=9',  contact: '+63 917 234 5678', prev: 'Late',    prevClass: 'late-stat'    },
  { num: '04', name: 'Tony Stark',                  avatar: 'https://i.pravatar.cc/32?img=7',  contact: '+63 917 234 5678', prev: 'Absent',  prevClass: 'absent-stat'  },
  { num: '05', name: 'Peter Kavinsky',              avatar: 'https://i.pravatar.cc/32?img=11', contact: '+63 917 234 5678', prev: 'Present', prevClass: 'present-stat' },
  { num: '06', name: 'Rodrick Heffley',             avatar: 'https://i.pravatar.cc/32?img=13', contact: '+63 917 234 5678', prev: 'Absent',  prevClass: 'absent-stat'  },
  { num: '07', name: 'Beatrice Kristi Ilejay Laus', avatar: 'https://i.pravatar.cc/32?img=15', contact: '+63 917 234 5678', prev: 'Present', prevClass: 'present-stat' },
];

let currentAttendance = {};
let nextId = 5;


/* =============================================================================
   TABLE RENDER
   ============================================================================= */

function renderTable(list) {
  if (list === undefined) list = TRAINEES;
  var tbody = document.getElementById('trainees-tbody');
  if (!tbody) return;

  if (list.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--color-text-light)">No trainees found.</td></tr>';
    return;
  }

  tbody.innerHTML = list.map(function(t) {
    var badgeClass = { 'Enrolled': 'badge-upcoming', 'Certified': 'badge-issued', 'Pending': 'badge-pending' }[t.status] || 'badge-upcoming';
    var realIdx = TRAINEES.indexOf(t);
    return '<tr>' +
      '<td><div class="trainee-cell"><img src="' + t.avatar + '" alt="" class="mini-avatar" onerror="this.src=\'https://i.pravatar.cc/32?img=0\'"><div><div class="trainee-name">' + t.name + '</div><div class="trainee-email">' + t.email + '</div></div></div></td>' +
      '<td><div>' + t.contact + '</div><div class="light-txt">' + t.address + '</div></td>' +
      '<td><div>' + t.workshop + '</div><div class="light-txt">' + t.workshopDate + '</div></td>' +
      '<td><div>' + t.sessions + ' / ' + t.totalSessions + ' sessions</div><div class="light-txt">' + t.attendance + ' attendance</div></td>' +
      '<td><span class="badge ' + badgeClass + '">' + t.status + '</span></td>' +
      '<td><button class="icon-btn" title="Edit" onclick="openEditModal(' + realIdx + ')">✏️</button><button class="icon-btn" title="Delete" onclick="openDeleteModal(' + realIdx + ')">🗑️</button></td>' +
      '</tr>';
  }).join('');
}


/* =============================================================================
   SEARCH & FILTERS
   ============================================================================= */

var filters = { search: '', status: '', workshop: '' };

function applyFilters() {
  var list = TRAINEES.filter(function(t) {
    var matchSearch = !filters.search || t.name.toLowerCase().includes(filters.search) || t.email.toLowerCase().includes(filters.search) || t.workshop.toLowerCase().includes(filters.search);
    var matchStatus = !filters.status || t.status === filters.status;
    var matchWorkshop = !filters.workshop || t.workshop.toLowerCase().includes(filters.workshop.toLowerCase());
    return matchSearch && matchStatus && matchWorkshop;
  });
  renderTable(list);
}

function filterTable(query) { filters.search = query.toLowerCase().trim(); applyFilters(); }
function filterByStatus(status) { filters.status = status; applyFilters(); }
function filterByWorkshop(workshop) { filters.workshop = workshop; applyFilters(); }

function sortTable(mode) {
  if (mode === 'name') {
    TRAINEES.sort(function(a, b) { return a.name.localeCompare(b.name); });
  } else {
    TRAINEES.sort(function(a, b) { return b.id - a.id; });
  }
  applyFilters();
}


/* =============================================================================
   MODAL HELPERS
   ============================================================================= */

function openModal(id) {
  var modal = document.getElementById(id);
  if (!modal) return;
  modal.classList.add('open');
  if (id === 'attendance-modal') resetAttendanceModal();
}

function closeModal(id) {
  var modal = document.getElementById(id);
  if (modal) modal.classList.remove('open');
}

function closeModalOutside(e, id) {
  if (e.target.id === id) closeModal(id);
}


/* =============================================================================
   PHONE INPUT HANDLERS
   ============================================================================= */

function phoneKeydown(e, input) {
  // Allow navigation/control keys
  if ([8, 9, 37, 38, 39, 40, 46].includes(e.keyCode)) {
    // Backspace: don't delete past '+63 ' prefix (4 chars)
    if (e.keyCode === 8 && input.selectionStart <= 4) return false;
    return true;
  }

  // Only allow digit keys
  if (!/^\d$/.test(e.key)) return false;

  // Count all digits in current value
  // +63 = "63" (2 digits) + 10 user digits = 13 total → block at 13
  var allDigits = input.value.replace(/\D/g, '');
  if (allDigits.length >= 13) return false;

  return true;
}

function enforcePhonePrefix(input) {
  if (!input.value.startsWith('+63 ')) {
    input.value = '+63 ';
  }
}

function moveCursorToEnd(input) {
  var len = input.value.length;
  input.setSelectionRange(len, len);
}


/* =============================================================================
   ADD NEW TRAINEE MODAL
   ============================================================================= */

function submitNewTrainee() {
  var lastName  = document.getElementById('nt-lastname').value.trim();
  var firstName = document.getElementById('nt-firstname').value.trim();
  var contact   = document.getElementById('nt-contact').value.trim();
  var email     = document.getElementById('nt-email').value.trim();
  var address   = document.getElementById('nt-address').value.trim();
  var workshop  = document.getElementById('nt-workshop').value;
  var status    = document.getElementById('nt-status').value;
  var errEl     = document.getElementById('add-trainee-error');

  // 1. Required fields
  if (!lastName || !firstName || !contact || !email || !address || !workshop) {
    errEl.textContent   = 'Please fill in all required fields.';
    errEl.style.display = 'block';
    return;
  }

  // 2. Email format
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    errEl.textContent   = 'Please enter a valid email address.';
    errEl.style.display = 'block';
    return;
  }

  // 3. Philippine mobile number — strip spaces/dashes then validate
  var rawContact = contact.replace(/[\s-]/g, '');
  var phRegex = /^(\+639\d{9}|09\d{9})$/;

  if (!phRegex.test(rawContact)) {
    errEl.textContent   = 'Invalid phone number. Must be a valid Philippine mobile number (+63 9XX XXX XXXX).';
    errEl.style.display = 'block';
    return;
  }

  // Normalize to +63 format
  var normalizedContact = rawContact.startsWith('0') ? '+63' + rawContact.slice(1) : rawContact;

  errEl.style.display = 'none';

  // 4. Build record
  var newTrainee = {
    id:            nextId++,
    name:          firstName + ' ' + lastName,
    email:         email,
    contact:       normalizedContact,
    address:       address,
    workshop:      workshop,
    workshopDate:  'TBD',
    sessions:      0,
    totalSessions: 3,
    attendance:    '0%',
    status:        status,
    avatar:        'https://i.pravatar.cc/32?img=' + (nextId + 10),
  };

  TRAINEES.unshift(newTrainee);

  var statTotal = document.getElementById('stat-total');
  if (statTotal) statTotal.textContent = TRAINEES.length;

  renderTable();
  closeModal('add-trainee-modal');
  clearAddForm();
  showToast('✅ ' + newTrainee.name + ' added successfully!');
}

function clearAddForm() {
  ['nt-lastname','nt-firstname','nt-middlename','nt-contact','nt-email','nt-address','nt-workshop','nt-status'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.value = el.tagName === 'SELECT' ? (el.options[0] ? el.options[0].value : '') : '';
  });
  var errEl = document.getElementById('add-trainee-error');
  if (errEl) errEl.style.display = 'none';
}


/* =============================================================================
   EDIT TRAINEE MODAL
   ============================================================================= */

function openEditModal(idx) {
  var t = TRAINEES[idx];
  if (!t) return;

  document.getElementById('et-name').value      = t.name;
  document.getElementById('et-email').value     = t.email;
  document.getElementById('et-contact').value   = t.contact;
  document.getElementById('et-address').value   = t.address;
  document.getElementById('et-row-index').value = idx;

  var wSelect = document.getElementById('et-workshop');
  for (var i = 0; i < wSelect.options.length; i++) {
    var opt = wSelect.options[i];
    if (t.workshop.includes(opt.value) || opt.value.includes(t.workshop.split('&')[0].trim())) {
      opt.selected = true; break;
    }
  }

  var sSelect = document.getElementById('et-status');
  for (var j = 0; j < sSelect.options.length; j++) {
    if (sSelect.options[j].value === t.status) { sSelect.options[j].selected = true; break; }
  }

  openModal('edit-trainee-modal');
}

function saveEditTrainee() {
  var idx      = parseInt(document.getElementById('et-row-index').value);
  var name     = document.getElementById('et-name').value.trim();
  var email    = document.getElementById('et-email').value.trim();
  var contact  = document.getElementById('et-contact').value.trim();
  var address  = document.getElementById('et-address').value.trim();
  var workshop = document.getElementById('et-workshop').value;
  var status   = document.getElementById('et-status').value;

  if (!name || !email || !contact || !address) {
    showToast('Please fill in all required fields.');
    return;
  }

  TRAINEES[idx] = Object.assign({}, TRAINEES[idx], { name: name, email: email, contact: contact, address: address, workshop: workshop, status: status });

  renderTable();
  closeModal('edit-trainee-modal');
  showToast('✅ ' + name + "'s record updated.");
}


/* =============================================================================
   DELETE MODAL
   ============================================================================= */

function openDeleteModal(idx) {
  var t = TRAINEES[idx];
  if (!t) return;
  document.getElementById('delete-modal-msg').textContent = 'Are you sure you want to remove ' + t.name + ' from the system? This cannot be undone.';
  document.getElementById('delete-row-index').value = idx;
  openModal('delete-modal');
}

function confirmDelete() {
  var idx  = parseInt(document.getElementById('delete-row-index').value);
  var name = TRAINEES[idx] ? TRAINEES[idx].name : 'Trainee';
  TRAINEES.splice(idx, 1);
  var statTotal = document.getElementById('stat-total');
  if (statTotal) statTotal.textContent = TRAINEES.length;
  renderTable();
  closeModal('delete-modal');
  showToast('🗑️ ' + name + ' has been removed.');
}


/* =============================================================================
   ATTENDANCE MODAL
   ============================================================================= */

function resetAttendanceModal() {
  document.getElementById('att-step-select').style.display  = 'block';
  document.getElementById('att-step-sheet').style.display   = 'none';
  document.getElementById('att-modal-title').textContent    = 'Log Attendance';
  document.getElementById('att-modal-sub').textContent      = 'Select a workshop and session to begin';
  document.getElementById('att-select-error').style.display = 'none';
  currentAttendance = {};
}

function updateSessionOptions() {}

function loadAttendanceSheet() {
  var workshop = document.getElementById('att-workshop-sel').value;
  var session  = document.getElementById('att-session-sel').value;
  var errEl    = document.getElementById('att-select-error');

  if (!workshop || !session) { errEl.style.display = 'block'; return; }
  errEl.style.display = 'none';

  document.getElementById('att-modal-title').textContent = 'Attendance · ' + workshop + ' · Session ' + session;
  document.getElementById('att-modal-sub').textContent   = 'Mark each trainee as present, late, or absent';

  buildAttendanceTable();

  document.getElementById('att-step-select').style.display = 'none';
  document.getElementById('att-step-sheet').style.display  = 'block';

  updateAttendanceSummary();
}

function backToSelector() {
  document.getElementById('att-step-select').style.display = 'block';
  document.getElementById('att-step-sheet').style.display  = 'none';
  currentAttendance = {};
  updateAttendanceSummary();
}

function buildAttendanceTable() {
  var tbody = document.getElementById('att-tbody');
  if (!tbody) return;
  tbody.innerHTML = '';

  ATTENDANCE_DATA.forEach(function(t) {
    var tr = document.createElement('tr');
    tr.innerHTML =
      '<td>' + t.num + '</td>' +
      '<td><div class="trainee-cell"><img src="' + t.avatar + '" alt="' + t.name + '" class="mini-avatar"><span>' + t.name + '</span></div></td>' +
      '<td>' + t.contact + '</td>' +
      '<td><span class="att-stat ' + t.prevClass + '">' + t.prev + '</span></td>' +
      '<td><div class="att-btn-group">' +
        '<button class="att-btn" onclick="markAttendance(this,\'present\',\'' + t.name + '\')">Present</button>' +
        '<button class="att-btn" onclick="markAttendance(this,\'late\',\'' + t.name + '\')">Late</button>' +
        '<button class="att-btn" onclick="markAttendance(this,\'absent\',\'' + t.name + '\')">Absent</button>' +
      '</div></td>';
    tbody.appendChild(tr);
  });
}

function markAttendance(btn, status, name) {
  btn.closest('.att-btn-group').querySelectorAll('.att-btn').forEach(function(b) { b.className = 'att-btn'; });
  btn.classList.add('selected-' + status);
  currentAttendance[name] = status;
  updateAttendanceSummary();
}

function updateAttendanceSummary() {
  var counts = { present: 0, late: 0, absent: 0 };
  Object.values(currentAttendance).forEach(function(s) { counts[s]++; });
  document.getElementById('att-present').textContent = counts.present;
  document.getElementById('att-late').textContent    = counts.late;
  document.getElementById('att-absent').textContent  = counts.absent;
}

function saveAttendance() {
  var total = Object.keys(currentAttendance).length;
  if (total === 0) { showToast('Please mark at least one trainee before saving.'); return; }
  closeModal('attendance-modal');
  showToast('✅ Attendance saved for ' + total + ' trainee' + (total !== 1 ? 's' : '') + '!');
}


/* =============================================================================
   TOAST
   ============================================================================= */

function showToast(message) {
  var toast = document.getElementById('toast');
  if (!toast) return;
  toast.textContent = message;
  toast.classList.add('show');
  setTimeout(function() { toast.classList.remove('show'); }, 3000);
}


/* =============================================================================
   INIT
   ============================================================================= */

document.addEventListener('DOMContentLoaded', function() {
  renderTable();
});