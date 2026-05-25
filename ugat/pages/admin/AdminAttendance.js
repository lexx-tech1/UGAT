/**
 * AdminAttendance.js — UGAT TrainTrack
 * Fully connected to get_attendance.php and save_attendance.php
 * Auto-flags eligible trainees after every attendance save.
 */

/* =============================================================================
   STATE
   ============================================================================= */
let ATTENDANCE_RECORDS = [];
let WORKSHOP_SUMMARY   = [];
let SESSION_SUMMARY    = [];
let currentView        = 'trainee';
let detailRecord       = null; // { user_id, workshop_id, trainee, sessions... }
const filters = { search: '', status: '', workshop: '', sessions: '', date: 'all', sort: 'newest' };

/* =============================================================================
   INIT
   ============================================================================= */
document.addEventListener('DOMContentLoaded', async function () {
    await Promise.all([loadRecords(), loadWorkshops(), loadSessions(), loadStats()]);
    populateWorkshopFilter();
});

/* =============================================================================
   LOADERS
   ============================================================================= */
async function loadStats() {
    try {
        const r = await fetch('get_attendance.php?action=stats', { credentials: 'same-origin' });
        const d = await r.json();
        if (!d.success) return;
        const kpi = d.kpi;
        const total   = (int(kpi.total));
        const present = int(kpi.present);
        const late    = int(kpi.late);
        const absent  = int(kpi.absent);
        const pct     = total > 0 ? Math.round(((present + late) / total) * 100) : 0;
        setText('stat-total',       total);
        setText('stat-present',     present);
        setText('stat-present-pct', `● ${pct}% overall rate`);
        setText('stat-absent',      absent);
        setText('stat-late',        late);
    } catch(e) { console.error('loadStats:', e); }
}

async function loadRecords() {
    try {
        const r = await fetch('get_attendance.php?action=records', { credentials: 'same-origin' });
        const d = await r.json();
        if (!d.success) { showToast('Failed to load attendance records.'); return; }
        ATTENDANCE_RECORDS = d.records;
        renderTraineeView(ATTENDANCE_RECORDS);
    } catch(e) { console.error('loadRecords:', e); showToast('Error loading attendance.'); }
}

async function loadWorkshops() {
    try {
        const r = await fetch('get_attendance.php?action=workshops', { credentials: 'same-origin' });
        const d = await r.json();
        if (!d.success) return;
        WORKSHOP_SUMMARY = d.workshops;
        renderWorkshopView(WORKSHOP_SUMMARY);
    } catch(e) { console.error('loadWorkshops:', e); }
}

async function loadSessions() {
    try {
        const r = await fetch('get_attendance.php?action=sessions', { credentials: 'same-origin' });
        const d = await r.json();
        if (!d.success) return;
        SESSION_SUMMARY = d.sessions;
        renderSessionView(SESSION_SUMMARY);
    } catch(e) { console.error('loadSessions:', e); }
}

async function loadSessionDetail(user_id, workshop_id) {
    const r = await fetch(`get_attendance.php?action=session_detail&user_id=${user_id}&workshop_id=${workshop_id}`, { credentials: 'same-origin' });
    const d = await r.json();
    if (!d.success) throw new Error(d.message);
    return d.sessions;
}

/* =============================================================================
   HELPERS
   ============================================================================= */
const int = v => parseInt(v) || 0;
function setText(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; }

/* =============================================================================
   POPULATE WORKSHOP FILTER
   ============================================================================= */
function populateWorkshopFilter() {
    const sel = document.getElementById('att-filter-workshop');
    if (!sel) return;
    const workshops = [...new Set(ATTENDANCE_RECORDS.map(r => r.workshop))];
    sel.innerHTML = '<option value="">All Workshops</option>' +
        workshops.map(w => `<option value="${w}">${w}</option>`).join('');
}

/* =============================================================================
   VIEW SWITCH
   ============================================================================= */
function switchView(mode, btn) {
    document.querySelectorAll('.filter-btns .tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('view-trainee').style.display  = mode === 'trainee'  ? '' : 'none';
    document.getElementById('view-workshop').style.display = mode === 'workshop' ? '' : 'none';
    document.getElementById('view-session').style.display  = mode === 'session'  ? '' : 'none';
    const headings = {
        trainee:  'All trainee attendance records',
        workshop: 'Attendance summary by workshop',
        session:  'Attendance summary by session',
    };
    setText('att-table-heading', headings[mode]);
    currentView = mode;
}

/* =============================================================================
   DATE FILTER
   ============================================================================= */
function filterByDate(range, btn) {
    ['today','yesterday','all'].forEach(d => {
        document.getElementById(`date-btn-${d}`)?.classList.remove('active');
    });
    btn.classList.add('active');
    filters.date = range;
    applyFilters();
}

/* =============================================================================
   FILTER + SORT
   ============================================================================= */
function applyFilters() {
    filters.search   = document.getElementById('att-search')?.value.trim().toLowerCase() || '';
    filters.status   = document.getElementById('att-filter-status')?.value   || '';
    filters.workshop = document.getElementById('att-filter-workshop')?.value || '';
    filters.sort     = document.getElementById('att-sort')?.value            || 'newest';

    let list = ATTENDANCE_RECORDS.filter(r => {
        const ms = !filters.search   || r.trainee.toLowerCase().includes(filters.search)
                                     || r.workshop.toLowerCase().includes(filters.search);
        const mst = !filters.status   || r.status === filters.status;
        const mw  = !filters.workshop || r.workshop === filters.workshop;
        return ms && mst && mw;
    });

    if (filters.sort === 'name')      list = [...list].sort((a,b) => a.trainee.localeCompare(b.trainee));
    if (filters.sort === 'rate-desc') list = [...list].sort((a,b) => b.rate - a.rate);
    if (filters.sort === 'rate-asc')  list = [...list].sort((a,b) => a.rate - b.rate);

    renderTraineeView(list);
}

/* =============================================================================
   RENDER — By Trainee
   ============================================================================= */
function buildDots(done, present, late, absent) {
    // Build dots from actual counts since we don't have per-session detail in the list
    const dots = [];
    for (let i = 0; i < present; i++) dots.push('<span class="dot-P" title="Present">P</span>');
    for (let i = 0; i < late;    i++) dots.push('<span class="dot-L" title="Late">L</span>');
    for (let i = 0; i < absent;  i++) dots.push('<span class="dot-A" title="Absent">A</span>');
    return dots.join('');
}

function renderTraineeView(list) {
    const tbody = document.getElementById('att-trainee-tbody');
    if (!tbody) return;

    if (!list.length) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--color-text-light)">
            No records match your filters.</td></tr>`;
        return;
    }

    const badgeMap = { 'Completed':'badge-completed', 'Ongoing':'badge-upcoming', 'Incomplete':'badge-pending' };
    const rateColor = r => r >= 75 ? 'var(--color-primary)' : r >= 50 ? 'var(--color-warning)' : 'var(--color-danger)';

    tbody.innerHTML = list.map(r => `
        <tr>
            <td>
                <div class="trainee-cell">
                    <div style="width:32px;height:32px;border-radius:50%;background:#e0f0d0;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#4B8423;flex-shrink:0">
                        ${r.trainee.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <div class="trainee-name">${r.trainee}</div>
                        <div class="trainee-email">${r.email}</div>
                    </div>
                </div>
            </td>
            <td>
                <div>${r.workshop}</div>
                <div class="light-txt">${r.date_range || ''}</div>
            </td>
            <td>
                <div class="session-dots">${buildDots(r.done, r.present, r.late, r.absent)}</div>
            </td>
            <td>
                <div class="progress-bar" style="margin-bottom:0.2rem">
                    <div class="progress-fill" style="width:${r.total > 0 ? Math.round((r.done/r.total)*100) : 0}%"></div>
                </div>
                <span class="light-txt">${r.done} / ${r.total}</span>
            </td>
            <td>
                <span style="font-weight:700;color:${rateColor(r.rate)}">${r.rate}%</span>
            </td>
            <td><span class="badge ${badgeMap[r.status] || 'badge-upcoming'}">${r.status}</span></td>
            <td>
                <button class="btn-sm" onclick="openDetailModal(${r.user_id}, ${r.workshop_id})">View</button>
            </td>
        </tr>`).join('');
}

/* =============================================================================
   RENDER — By Workshop
   ============================================================================= */
function renderWorkshopView(list) {
    const tbody = document.getElementById('att-workshop-tbody');
    if (!tbody) return;

    if (!list || !list.length) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:1rem;color:#aaa">No data.</td></tr>`;
        return;
    }

    tbody.innerHTML = list.map(w => `
        <tr>
            <td><strong>${w.name}</strong></td>
            <td>${w.sessions} sessions</td>
            <td>${w.enrolled} trainees</td>
            <td><span style="font-weight:700;color:var(--color-primary)">${w.avg_rate}</span></td>
            <td><span class="att-stat present-stat">${w.present}</span></td>
            <td><span class="att-stat absent-stat">${w.absent}</span></td>
            <td><span class="att-stat late-stat">${w.late}</span></td>
        </tr>`).join('');
}

/* =============================================================================
   RENDER — By Session
   ============================================================================= */
function renderSessionView(list) {
    const tbody = document.getElementById('att-session-tbody');
    if (!tbody) return;

    if (!list || !list.length) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:1rem;color:#aaa">No sessions recorded.</td></tr>`;
        return;
    }

    tbody.innerHTML = list.map(s => `
        <tr>
            <td>${s.workshop}</td>
            <td><strong>${s.session}</strong></td>
            <td>${s.date}</td>
            <td><span class="att-stat present-stat">${s.present}</span></td>
            <td><span class="att-stat absent-stat">${s.absent}</span></td>
            <td><span class="att-stat late-stat">${s.late}</span></td>
            <td><span style="font-weight:700;color:${parseInt(s.rate)>=75?'var(--color-primary)':'var(--color-warning)'}">${s.rate}</span></td>
        </tr>`).join('');
}

/* =============================================================================
   DETAIL MODAL — View + Edit per-session marks
   ============================================================================= */
async function openDetailModal(user_id, workshop_id) {
    const rec = ATTENDANCE_RECORDS.find(r => r.user_id === user_id && r.workshop_id === workshop_id);
    if (!rec) return;

    try {
        const sessions = await loadSessionDetail(user_id, workshop_id);
        detailRecord   = { ...rec, sessions };

        setText('att-detail-title', `${rec.trainee} — ${rec.workshop}`);
        setText('att-detail-sub',   `${rec.date_range} · ${rec.total} sessions · ${rec.rate}% rate`);

        document.getElementById('att-detail-summary').innerHTML = `
            <div style="width:40px;height:40px;border-radius:50%;background:#e0f0d0;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;color:#4B8423">
                ${rec.trainee.charAt(0).toUpperCase()}
            </div>
            <div class="att-detail-info">
                <span class="att-detail-name">${rec.trainee}</span>
                <span class="att-detail-meta">${rec.email} · ${rec.workshop}</span>
            </div>
            <span class="att-stat present-stat">Present: ${rec.present}</span>
            <span class="att-stat late-stat">Late: ${rec.late}</span>
            <span class="att-stat absent-stat">Absent: ${rec.absent}</span>`;

        renderDetailTable(sessions);
        openModal('att-detail-modal');
    } catch(e) {
        showToast('Could not load session detail.');
    }
}

function renderDetailTable(sessions) {
    const tbody = document.getElementById('att-detail-tbody');
    if (!tbody) return;

    tbody.innerHTML = sessions.map((s, i) => {
        const dotCls = s.status === 'present' ? 'dot-P' : s.status === 'absent' ? 'dot-A' : s.status === 'late' ? 'dot-L' : 'dot-dash';
        const dotLbl = s.status === 'present' ? 'P' : s.status === 'absent' ? 'A' : s.status === 'late' ? 'L' : '–';
        const isUpcoming = s.status === 'upcoming';

        return `<tr>
            <td><strong>Session ${s.num}</strong></td>
            <td>${s.date}</td>
            <td>
                <div style="display:flex;align-items:center;gap:0.5rem">
                    <span class="${dotCls}" style="width:22px;height:22px;font-size:0.65rem" id="dot-${i}">${dotLbl}</span>
                    <span style="font-size:var(--text-body-sm);text-transform:capitalize" id="status-lbl-${i}">${s.status}</span>
                </div>
            </td>
            <td>
                <input type="text" class="att-notes-input" data-idx="${i}" value="${s.notes || ''}" placeholder="Add a note…">
            </td>
            <td>
                ${!isUpcoming
                    ? `<select class="att-status-select" data-idx="${i}" onchange="updateDetailStatus(this)">
                           <option value="present" ${s.status==='present'?'selected':''}>Present</option>
                           <option value="late"    ${s.status==='late'   ?'selected':''}>Late</option>
                           <option value="absent"  ${s.status==='absent' ?'selected':''}>Absent</option>
                       </select>`
                    : `<span class="light-txt">Upcoming</span>`}
            </td>
        </tr>`;
    }).join('');
}

function updateDetailStatus(select) {
    const idx    = parseInt(select.dataset.idx);
    const newVal = select.value;
    const dotCls = newVal === 'present' ? 'dot-P' : newVal === 'absent' ? 'dot-A' : 'dot-L';
    const dotLbl = newVal === 'present' ? 'P'     : newVal === 'absent' ? 'A'     : 'L';
    const dot    = document.getElementById(`dot-${idx}`);
    const lbl    = document.getElementById(`status-lbl-${idx}`);
    if (dot) { dot.className = dotCls; dot.style.cssText = 'width:22px;height:22px;font-size:0.65rem'; dot.textContent = dotLbl; }
    if (lbl) lbl.textContent = newVal;
    if (detailRecord?.sessions?.[idx]) detailRecord.sessions[idx].status = newVal;
}

/* =============================================================================
   SAVE ATTENDANCE EDITS → PHP with auto-eligibility check
   ============================================================================= */
async function saveAttendanceEdits() {
    if (!detailRecord) return;

    const selects = document.querySelectorAll('#att-detail-tbody .att-status-select');
    const inputs  = document.querySelectorAll('#att-detail-tbody .att-notes-input');

    // Build records array
    const records = [];
    selects.forEach(sel => {
        const idx = parseInt(sel.dataset.idx);
        const s   = detailRecord.sessions[idx];
        if (!s || s.status === 'upcoming') return;
        const notes = inputs[idx]?.value.trim() || '';
        records.push({
            trainee_id: detailRecord.user_id,
            session_id: s.session_id,
            status:     sel.value,
            notes,
        });
    });

    if (!records.length) { showToast('No changes to save.'); return; }

    // Find one session_id to use for bulk_save (they all belong to same workshop)
    const session_id = records[0].session_id;

    try {
        const r = await fetch('save_attendance.php', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'bulk_save',
                session_id,
                records,
            }),
        });
        const d = await r.json();

        if (d.success) {
            closeModal('att-detail-modal');
            showToast(`✅ ${d.message}`);
            // If trainees were flagged, extra celebration toast
            if (d.newly_flagged > 0) {
                setTimeout(() => showToast(`🎓 ${d.newly_flagged} trainee(s) now eligible for certification!`), 1500);
            }
            // Reload all data
            await Promise.all([loadRecords(), loadWorkshops(), loadSessions(), loadStats()]);
            populateWorkshopFilter();
        } else {
            showToast('❌ ' + d.message);
        }
    } catch(e) {
        showToast('Could not save attendance.');
    }
}

/* =============================================================================
   EXPORT
   ============================================================================= */
function exportReport() {
    const today = new Date().toISOString().split('T')[0];
    const toEl  = document.getElementById('exp-date-to');
    if (toEl) toEl.value = today;
    openModal('export-modal');
}

function doExport() {
    const format = document.getElementById('exp-format')?.value || 'csv';
    const from   = document.getElementById('exp-date-from')?.value || '';
    const to     = document.getElementById('exp-date-to')?.value   || '';
    closeModal('export-modal');
    const params = new URLSearchParams({ sections: 'attendance', format, period: 'custom', from, to });
    window.open('export_reports.php?' + params.toString(), '_blank');
}

/* =============================================================================
   MODAL HELPERS + TOAST
   ============================================================================= */
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
function closeModalOutside(e, id) { if (e.target.id === id) closeModal(id); }

function showToast(msg) {
    const t = document.getElementById('toast'); if (!t) return;
    t.textContent = msg; t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3500);
}