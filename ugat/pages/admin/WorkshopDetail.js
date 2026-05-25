/**
 * WorkshopDetail.js — UGAT TrainTrack
 * Reads ?id= from the URL, fetches from get_workshop_detail.php,
 * then renders every section of the page.
 * Attendance modal and edit modal save to save_workshops.php.
 */

let WORKSHOP      = null;
let SESSIONS      = [];
let TRAINEES      = [];
let ENROLLED      = [];
let attMarks      = {};
let activeSession = null;

/* =============================================================================
   INIT
   ============================================================================= */
document.addEventListener('DOMContentLoaded', async function () {
  const workshopId = parseInt(new URLSearchParams(window.location.search).get('id'));
  if (!workshopId) { document.getElementById('detail-title').textContent = 'Workshop not found.'; return; }

  try {
    const r = await fetch(`get_workshop_detail.php?id=${workshopId}`);
    const d = await r.json();
    if (!d.success) { document.getElementById('detail-title').textContent = d.message || 'Workshop not found.'; return; }

    WORKSHOP = d.workshop;
    SESSIONS = d.sessions;
    TRAINEES = d.trainees;
    document.title = `${WORKSHOP.title} — UGAT TrainTrack`;

    renderHeader(); renderStats(); renderAbout();
    renderSessions(); renderTrainees(); renderSidebar();
  } catch (e) {
    console.error('WorkshopDetail load error:', e);
    document.getElementById('detail-title').textContent = 'Error loading workshop.';
  }
});

/* =============================================================================
   RENDER — HEADER
   ============================================================================= */
function renderHeader() {
  const w  = WORKSHOP;
  const st = statusConfig(w.status);
  document.getElementById('detail-badge').innerHTML = `
    <span class="badge" style="background:#f0f7ec;color:#3a7d1e;border:1px solid #c8e6c9">${esc(w.category||'')}</span>
    <span class="badge ${st.cls}">${st.label}</span>`;
  document.getElementById('detail-title').textContent    = w.title;
  document.getElementById('detail-category').textContent = `UGAT Integrated Farm · ${w.category||''}`;
  document.getElementById('detail-actions').innerHTML = `
    <button class="btn-secondary" onclick="openAttendanceModal()">Log Attendance</button>
    <button class="btn-primary"   onclick="openEditWorkshopModal()">Edit Workshop</button>`;
}

/* =============================================================================
   RENDER — STAT CARDS
   ============================================================================= */
function renderStats() {
  const w        = WORKSHOP;
  const filled   = parseInt(w.filled_slots)||0;
  const maxSlots = parseInt(w.max_slots)||0;
  const slotsLeft = maxSlots - filled;
  const totalAttended = TRAINEES.reduce((s,t)=>s+(parseInt(t.sessions_attended)||0),0);
  const totalPossible = TRAINEES.reduce((s,t)=>s+(parseInt(t.total_sessions)||0),0);
  const avgRate = totalPossible>0 ? Math.round((totalAttended/totalPossible)*100)+'%' : '—';
  const completedCount = SESSIONS.filter(s=>s.computed_status==='done').length;
  document.getElementById('detail-stats').innerHTML = `
    <div class="stat-card highlight">
      <div class="stat-num">${filled}</div>
      <div class="stat-label">Enrolled Trainees</div>
      <div class="stat-sub">of ${maxSlots} max slots</div>
    </div>
    <div class="stat-card">
      <div class="stat-num">${SESSIONS.length}</div>
      <div class="stat-label">Total Sessions</div>
      <div class="stat-sub">${completedCount} completed</div>
    </div>
    <div class="stat-card">
      <div class="stat-num">${avgRate}</div>
      <div class="stat-label">Avg. Attendance</div>
      <div class="stat-sub">Across all sessions</div>
    </div>
    <div class="stat-card ${slotsLeft<=5?'yellow':''}">
      <div class="stat-num">${slotsLeft}</div>
      <div class="stat-label">Slots Remaining</div>
      <div class="stat-sub">${slotsLeft===0?'● Workshop full':'● Still open'}</div>
    </div>`;
}

/* =============================================================================
   RENDER — ABOUT
   ============================================================================= */
function renderAbout() {
  const w = WORKSHOP;
  document.getElementById('detail-description').textContent = w.description||'No description provided.';

  const outcomes = splitLines(w.outcomes);
  const outWrap  = document.getElementById('detail-outcomes-wrap');
  if (outcomes.length) {
    document.getElementById('detail-outcomes').innerHTML = outcomes.map(o=>`<li>${esc(o)}</li>`).join('');
    outWrap.style.display = '';
  } else { outWrap.style.display = 'none'; }

  const materials = splitLines(w.materials);
  const matWrap   = document.getElementById('detail-materials-wrap');
  if (materials.length) {
    document.getElementById('detail-materials').innerHTML = materials.map(m=>`<li>${esc(m)}</li>`).join('');
    matWrap.style.display = '';
  } else { matWrap.style.display = 'none'; }
}

/* =============================================================================
   RENDER — SESSION TIMELINE
   ============================================================================= */
function renderSessions() {
  const container = document.getElementById('detail-sessions');
  if (!SESSIONS.length) { container.innerHTML='<p style="color:#aaa">No sessions scheduled yet.</p>'; return; }

  container.innerHTML = SESSIONS.map(s => {
    const status    = s.computed_status||'upcoming';
    const dotClass  = status==='done'?'done':status==='current'?'current':'upcoming';
    const cardClass = status==='done'?'done-card':status==='current'?'current-card':'upcoming-card';
    const statusBadge = status==='done'
      ?`<span class="badge badge-completed">Completed</span>`
      :status==='current'
        ?`<span class="badge badge-ongoing">In Progress</span>`
        :`<span class="badge badge-upcoming">Upcoming</span>`;
    const attRow = (status==='done'&&parseInt(s.total_marked)>0)?`
      <div class="session-att-summary">
        <span class="session-att-pill sap-present">✓ Present: ${s.present_count||0}</span>
        <span class="session-att-pill sap-late">⏱ Late: ${s.late_count||0}</span>
        <span class="session-att-pill sap-absent">✗ Absent: ${s.absent_count||0}</span>
      </div>`:'';
    const sessionTitle = s.title?`Session ${s.session_no}: ${esc(s.title)}`:`Session ${s.session_no}`;
    const timeDisplay  = s.start_time&&s.end_time
      ?`${formatTime(s.start_time)} – ${formatTime(s.end_time)}`
      :s.start_time?formatTime(s.start_time):'—';
    return `
      <div class="session-timeline-item">
        <div class="session-dot-col"><div class="session-dot ${dotClass}"></div></div>
        <div class="session-card ${cardClass}">
          <div class="session-card-header">
            <span class="session-card-title">${sessionTitle}</span>${statusBadge}
          </div>
          <div class="session-card-meta">
            <span>📅 ${formatDate(s.session_date)} &nbsp;·&nbsp; ${timeDisplay}</span>
            <span>📍 ${esc(WORKSHOP.location||'UGAT Demo Farm')}</span>
          </div>${attRow}
        </div>
      </div>`;
  }).join('');
}

/* =============================================================================
   RENDER — TRAINEES TABLE
   ============================================================================= */
function renderTrainees() {
  document.getElementById('detail-trainee-count').textContent = `${TRAINEES.length} enrolled`;
  if (!TRAINEES.length) {
    document.getElementById('detail-trainees-tbody').innerHTML =
      `<tr><td colspan="5" style="text-align:center;padding:2rem;color:#aaa">No trainees enrolled yet.</td></tr>`;
    return;
  }
  document.getElementById('detail-trainees-tbody').innerHTML = TRAINEES.map(t => {
    const status   = t.enrollment_status||'enrolled';
    const badgeCls = status==='certified'?'badge-issued':status==='dropped'?'badge-pending':'badge-upcoming';
    const attended = parseInt(t.sessions_attended)||0;
    const total    = parseInt(t.total_sessions)||0;
    const rate     = total>0?`${t.attendance_rate??0}%`:'—';
    const pic      = t.profile_pic?`/UGAT/${t.profile_pic}`
      :`https://ui-avatars.com/api/?name=${encodeURIComponent(t.name)}&size=32&background=4B8423&color=fff`;
    return `
      <tr>
        <td><div class="trainee-cell">
          <img src="${pic}" alt="" class="mini-avatar"
               onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(t.name)}&size=32&background=4B8423&color=fff'">
          <div><div class="trainee-name">${esc(t.name)}</div><div class="trainee-email">${esc(t.email||'')}</div></div>
        </div></td>
        <td>${esc(t.phone||'—')}</td>
        <td>${rate}</td>
        <td>${attended} / ${total}</td>
        <td><span class="badge ${badgeCls}">${capitalize(status)}</span></td>
      </tr>`;
  }).join('');
}

/* =============================================================================
   RENDER — SIDEBAR
   ============================================================================= */
function renderSidebar() {
  const w = WORKSHOP;
  const currentSess = SESSIONS.find(s=>s.computed_status==='current');
  const nextSess    = SESSIONS.find(s=>s.computed_status==='upcoming');
  const displayDate = currentSess
    ?`${formatDate(currentSess.session_date)} (Session ${currentSess.session_no} in progress)`
    :nextSess?`${formatDate(nextSess.session_date)} (Next: Session ${nextSess.session_no})`
    :SESSIONS[0]?formatDate(SESSIONS[0].session_date):'—';
  const firstTime = SESSIONS[0]
    ?((SESSIONS[0].start_time&&SESSIONS[0].end_time)
      ?`${formatTime(SESSIONS[0].start_time)} – ${formatTime(SESSIONS[0].end_time)}`
      :formatTime(SESSIONS[0].start_time))
    :'—';

  document.getElementById('detail-info-list').innerHTML = `
    <li><span class="sidebar-info-label">Date / Schedule</span><span class="sidebar-info-value">${displayDate}</span></li>
    <li><span class="sidebar-info-label">Time</span><span class="sidebar-info-value">${firstTime}</span></li>
    <li><span class="sidebar-info-label">Location</span><span class="sidebar-info-value">${esc(w.location||'—')}</span></li>
    <li><span class="sidebar-info-label">Facilitator</span><span class="sidebar-info-value">${esc(w.facilitator||'—')}</span></li>
    <li><span class="sidebar-info-label">Total Sessions</span><span class="sidebar-info-value">${SESSIONS.length} session${SESSIONS.length!==1?'s':''}</span></li>
    <li><span class="sidebar-info-label">Category</span><span class="sidebar-info-value">${esc(w.category||'—')}</span></li>`;

  const filled   = parseInt(w.filled_slots)||0;
  const maxSlots = parseInt(w.max_slots)||0;
  const pct      = maxSlots>0?Math.round((filled/maxSlots)*100):0;
  const barColor = pct>=90?'var(--color-danger)':pct>=70?'var(--color-warning)':'var(--color-primary)';
  document.getElementById('detail-slots-wrap').innerHTML = `
    <div class="slots-bar-wrap">
      <div class="slots-bar-row"><span>Slots filled</span><span>${filled} / ${maxSlots}</span></div>
      <div class="progress-bar" style="height:8px">
        <div class="progress-fill" style="width:${pct}%;background:${barColor}"></div>
      </div>
      <p class="slots-remaining">${maxSlots-filled} slot${(maxSlots-filled)!==1?'s':''} remaining</p>
    </div>`;

  document.getElementById('detail-cert-req').textContent = w.cert_req||'No certification requirement set.';
  document.getElementById('detail-quick-actions').innerHTML = `
    <button class="btn-primary" onclick="openAttendanceModal()">Log Attendance</button>
    <button class="btn-outline" onclick="window.location.href='AdminWorkshops.html?tab=trainees'">View Trainees</button>
    <button class="btn-outline" onclick="showToast('Export feature coming soon.')">Export Report</button>`;
}

/* =============================================================================
   ATTENDANCE MODAL
   ============================================================================= */
async function openAttendanceModal() {
  attMarks = {};
  activeSession =
    SESSIONS.find(s=>s.computed_status==='current')||
    SESSIONS.find(s=>s.computed_status==='upcoming')||
    SESSIONS[0];

  const sessionOptions = SESSIONS.map(s=>
    `<option value="${s.id}" ${s.id==activeSession?.id?'selected':''}>
       Session ${s.session_no} · ${formatDate(s.session_date)}
       ${s.computed_status==='done'?' (Completed)':s.computed_status==='current'?' (Today)':''}
     </option>`).join('');

  document.getElementById('att-modal-title').textContent = `Attendance Sheet · ${esc(WORKSHOP.title)}`;
  document.getElementById('att-modal-sub').innerHTML = `
    <select id="att-session-select" class="filter-select"
            style="margin-top:0.4rem;font-size:0.85rem"
            onchange="onAttSessionChange(this.value)">
      ${sessionOptions}
    </select>`;

  updateAttendanceSummary();
  openModal('attendance-modal');
  await loadAttendanceSheet();
}

async function onAttSessionChange(sessionId) {
  activeSession = SESSIONS.find(s=>s.id==sessionId)||activeSession;
  attMarks = {};
  updateAttendanceSummary();
  await loadAttendanceSheet();
}

async function loadAttendanceSheet() {
  const tbody = document.getElementById('att-tbody');
  tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:1.5rem;color:#aaa">Loading…</td></tr>`;
  try {
    const r = await fetch(`get_workshops.php?action=enrolled&workshop_id=${WORKSHOP.id}`);
    const d = await r.json();
    ENROLLED = d.trainees||[];
    if (!ENROLLED.length) {
      tbody.innerHTML=`<tr><td colspan="5" style="text-align:center;padding:1.5rem;color:#aaa">No trainees enrolled.</td></tr>`;
      return;
    }
    tbody.innerHTML = ENROLLED.map((t,i)=>{
      const pic=t.profile_pic?`/UGAT/${t.profile_pic}`
        :`https://ui-avatars.com/api/?name=${encodeURIComponent(t.name)}&size=32&background=4B8423&color=fff`;
      return `
        <tr>
          <td>${i+1}</td>
          <td><div class="trainee-cell"><img src="${pic}" alt="" class="mini-avatar"
               onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(t.name)}&size=32&background=4B8423&color=fff'">
            <span>${esc(t.name)}</span></div></td>
          <td>${esc(t.phone||'—')}</td>
          <td>—</td>
          <td><div class="att-btn-group">
            <button class="att-btn" onclick="markAtt(this,'present',${t.id})">Present</button>
            <button class="att-btn" onclick="markAtt(this,'late',${t.id})">Late</button>
            <button class="att-btn" onclick="markAtt(this,'absent',${t.id})">Absent</button>
          </div></td>
        </tr>`;
    }).join('');
  } catch(e) {
    tbody.innerHTML=`<tr><td colspan="5" style="text-align:center;padding:1.5rem;color:red">Error loading trainees.</td></tr>`;
  }
}

function markAtt(btn,status,userId) {
  btn.closest('.att-btn-group').querySelectorAll('.att-btn').forEach(b=>b.className='att-btn');
  btn.classList.add('selected-'+status);
  attMarks[userId]=status;
  updateAttendanceSummary();
}

function updateAttendanceSummary() {
  const c={present:0,late:0,absent:0};
  Object.values(attMarks).forEach(s=>{if(c[s]!==undefined)c[s]++;});
  document.getElementById('att-present').textContent=c.present;
  document.getElementById('att-late').textContent=c.late;
  document.getElementById('att-absent').textContent=c.absent;
}

async function saveAttendance() {
  const total=Object.keys(attMarks).length;
  if(!total){showToast('⚠️ Mark at least one trainee before saving.');return;}
  if(!activeSession){showToast('⚠️ No session selected.');return;}
  const records=Object.entries(attMarks).map(([user_id,status])=>({user_id:parseInt(user_id),status}));
  try {
    const r=await fetch('save_workshops.php',{
      method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({action:'save_attendance',session_id:activeSession.id,records}),
    });
    const d=await r.json();
    closeModal('attendance-modal');
    showToast(d.success?`✅ ${d.message}`:`❌ ${d.message}`);
    if(d.success) await refreshPageData();
  } catch(e){showToast('Could not save attendance.');}
}

/* =============================================================================
   EDIT WORKSHOP MODAL — includes outcomes + materials
   ============================================================================= */
function openEditWorkshopModal() {
  const w=WORKSHOP;
  document.getElementById('ew-modal-title').textContent=`Edit: ${w.title}`;
  document.getElementById('ew-title').value       = w.title;
  document.getElementById('ew-facilitator').value = w.facilitator||'';
  document.getElementById('ew-location').value    = w.location||'';
  document.getElementById('ew-max-slots').value   = w.max_slots||'';
  document.getElementById('ew-cert-req').value    = w.cert_req||'';
  document.getElementById('ew-description').value = w.description||'';
  document.getElementById('ew-outcomes').value    = w.outcomes||'';    // ← new
  document.getElementById('ew-materials').value   = w.materials||'';   // ← new

  const catSel=document.getElementById('ew-category');
  for(const opt of catSel.options){if(opt.value===w.category){opt.selected=true;break;}}
  const stSel=document.getElementById('ew-status');
  for(const opt of stSel.options){if(opt.value===w.status){opt.selected=true;break;}}

  document.getElementById('ew-sessions-list').innerHTML=SESSIONS.map(s=>`
    <div style="display:grid;grid-template-columns:auto 1fr 1fr 1fr;gap:0.6rem;align-items:center;
                background:var(--color-primary-pale);border:1.5px solid var(--color-primary-mid);
                border-radius:var(--radius-sm);padding:0.65rem 1rem;"
         data-session-id="${s.id}">
      <span style="font-weight:700;color:#4B8423;font-size:var(--text-body-sm)">S${s.session_no}</span>
      <input type="text" class="form-input ew-sess-title" value="${esc(s.title||'')}"
             placeholder="Session title" style="padding:0.45rem 0.75rem;font-size:var(--text-body-sm)">
      <input type="date" class="form-input ew-sess-date"  value="${s.session_date||''}"
             style="padding:0.45rem 0.75rem;font-size:var(--text-body-sm)">
      <input type="text" class="form-input ew-sess-time"  value="${esc(s.start_time||'')}"
             placeholder="e.g. 8:00 AM – 5:00 PM" style="padding:0.45rem 0.75rem;font-size:var(--text-body-sm)">
    </div>`).join('');

  document.getElementById('ew-error').style.display='none';
  openModal('edit-workshop-modal');
}

async function saveWorkshopEdits() {
  const errEl=document.getElementById('ew-error');
  const title       = document.getElementById('ew-title').value.trim();
  const category    = document.getElementById('ew-category').value;
  const facilitator = document.getElementById('ew-facilitator').value.trim();
  const location    = document.getElementById('ew-location').value.trim();
  const max_slots   = parseInt(document.getElementById('ew-max-slots').value);
  const status      = document.getElementById('ew-status').value;
  const cert_req    = document.getElementById('ew-cert-req').value.trim();
  const description = document.getElementById('ew-description').value.trim();
  const outcomes    = document.getElementById('ew-outcomes').value.trim();    // ← new
  const materials   = document.getElementById('ew-materials').value.trim();   // ← new

  if(!title||!category||!facilitator||!location||!max_slots){
    errEl.textContent='Please fill in all required fields.';errEl.style.display='block';return;
  }
  errEl.style.display='none';

  const sessionRows=document.querySelectorAll('#ew-sessions-list [data-session-id]');
  const sessions=Array.from(sessionRows).map(row=>({
    id:    parseInt(row.dataset.sessionId),
    title: row.querySelector('.ew-sess-title')?.value.trim()||'',
    date:  row.querySelector('.ew-sess-date')?.value||'',
    time:  row.querySelector('.ew-sess-time')?.value.trim()||'',
  }));

  try {
    const r=await fetch('save_workshops.php',{
      method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({
        action:'edit_workshop',id:WORKSHOP.id,
        title,category,facilitator,location,max_slots,status,
        cert_req,description,outcomes,materials,sessions,  // ← outcomes+materials included
      }),
    });
    const d=await r.json();
    if(!d.success){errEl.textContent=d.message||'Save failed.';errEl.style.display='block';return;}
    closeModal('edit-workshop-modal');
    showToast(`✅ "${title}" updated successfully!`);
    await refreshPageData();
  } catch(e){showToast('Could not save changes.');}
}

/* =============================================================================
   SHARED PAGE REFRESH
   ============================================================================= */
async function refreshPageData() {
  try {
    const fr=await fetch(`get_workshop_detail.php?id=${WORKSHOP.id}`);
    const fd=await fr.json();
    if(fd.success){
      WORKSHOP=fd.workshop; SESSIONS=fd.sessions; TRAINEES=fd.trainees;
      document.title=`${WORKSHOP.title} — UGAT TrainTrack`;
      renderHeader();renderStats();renderAbout();renderSessions();renderTrainees();renderSidebar();
    }
  } catch(e){console.error('refreshPageData error:',e);}
}

/* =============================================================================
   MODAL + TOAST
   ============================================================================= */
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
function closeModalOutside(e,id){ if(e.target.id===id) closeModal(id); }

function showToast(msg) {
  const t=document.getElementById('toast');
  if(!t)return;
  t.textContent=msg;t.classList.add('show');
  setTimeout(()=>t.classList.remove('show'),3000);
}

/* =============================================================================
   UTILS
   ============================================================================= */
function statusConfig(s){
  return({upcoming:{label:'Upcoming',cls:'badge-upcoming'},ongoing:{label:'Ongoing',cls:'badge-ongoing'},completed:{label:'Completed',cls:'badge-completed'}})[s]||{label:'Upcoming',cls:'badge-upcoming'};
}
function esc(str){
  if(!str)return'';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function capitalize(str){return str?str.charAt(0).toUpperCase()+str.slice(1):'';}
function formatDate(d){
  if(!d||d==='0000-00-00')return'TBD';
  try{return new Date(d).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});}
  catch{return d;}
}
function formatTime(t){
  if(!t)return'—';
  try{const[h,m]=t.split(':');const d=new Date();d.setHours(parseInt(h),parseInt(m),0);return d.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'});}
  catch{return t;}
}
function splitLines(text){
  if(!text)return[];
  return text.split(/\r?\n/).map(l=>l.trim()).filter(Boolean);
}