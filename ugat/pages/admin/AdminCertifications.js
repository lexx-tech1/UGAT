/**
 * AdminCertifications.js — UGAT TrainTrack
 * Fully connected to real database via get_certifications.php
 * and save_certifications.php.
 */

/* =============================================================================
   STATE
   ============================================================================= */
let ELIGIBLE = [];
let ISSUED   = [];
let pendingCertId = null;
let viewingCertId = null;

/* =============================================================================
   INIT
   ============================================================================= */
document.addEventListener('DOMContentLoaded', async function () {
    await Promise.all([loadStats(), loadEligible(), loadIssued()]);
});

/* =============================================================================
   DATA LOADERS
   ============================================================================= */
async function loadStats() {
    try {
        const r = await fetch('get_certifications.php?action=stats', { credentials: 'same-origin' });
        const d = await r.json();
        if (!d.success) return;
        const s = id => document.getElementById(id);
        if (s('stat-issued'))      s('stat-issued').textContent      = d.issued;
        if (s('stat-eligible'))    s('stat-eligible').textContent    = d.eligible;
        if (s('stat-in-progress')) s('stat-in-progress').textContent = d.in_progress;
        if (s('stat-inactive'))    s('stat-inactive').textContent    = d.inactive;
        const cnt = d.eligible;
        const el = s('eligible-count-txt');
        if (el) el.textContent = cnt + ' trainee' + (cnt !== 1 ? 's' : '') + ' awaiting certificate issuance';
    } catch(e) { console.error('loadStats:', e); }
}

async function loadEligible() {
    try {
        const r = await fetch('get_certifications.php?action=eligible', { credentials: 'same-origin' });
        const d = await r.json();
        if (!d.success) { showToast('Failed to load eligible trainees.'); return; }
        ELIGIBLE = d.eligible;
        renderEligible(ELIGIBLE);
        // Populate workshop filter for issued
        populateWorkshopFilter();
    } catch(e) { console.error('loadEligible:', e); showToast('Error loading eligible trainees.'); }
}

async function loadIssued(search = '', workshop = '', sort = 'newest') {
    try {
        const params = new URLSearchParams({ action: 'issued', search, workshop, sort });
        const r = await fetch('get_certifications.php?' + params.toString(), { credentials: 'same-origin' });
        const d = await r.json();
        if (!d.success) { showToast('Failed to load issued certificates.'); return; }
        ISSUED = d.issued;
        renderIssued(ISSUED);
    } catch(e) { console.error('loadIssued:', e); showToast('Error loading issued certificates.'); }
}

/* =============================================================================
   RENDER — Eligible Table
   ============================================================================= */
function renderEligible(list) {
    const tbody = document.getElementById('eligible-tbody');
    if (!tbody) return;

    if (!list.length) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--color-text-light)">
            No eligible trainees found.</td></tr>`;
        return;
    }

    tbody.innerHTML = list.map(t => `
        <tr id="elig-row-${t.id}">
            <td>
                <div class="trainee-cell">
                    <div class="mini-avatar-placeholder" style="width:32px;height:32px;border-radius:50%;background:#e0f0d0;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#4B8423;flex-shrink:0">
                        ${t.name.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <div class="trainee-name">${t.name}</div>
                        <div class="trainee-email">${t.email}</div>
                    </div>
                </div>
            </td>
            <td>
                <div>${t.workshop}</div>
                <div class="light-txt">${t.sessions_req} session${t.sessions_req !== 1 ? 's' : ''} required</div>
            </td>
            <td><span class="badge-sessions">${t.sessions_done} / ${t.sessions_req}</span></td>
            <td class="${t.rate >= 75 ? 'rate-green' : 'rate-orange'}">${t.rate}%</td>
            <td>${t.completed_on}</td>
            <td>
                <button class="btn-issue" onclick="openIssueConfirm(${t.id})">
                    Issue Certificate
                </button>
            </td>
        </tr>`).join('');
}

/* =============================================================================
   RENDER — Issued Table
   ============================================================================= */
function renderIssued(list) {
    const tbody = document.getElementById('issued-tbody');
    if (!tbody) return;

    if (!list.length) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--color-text-light)">
            No certificates issued yet.</td></tr>`;
        document.getElementById('issued-count-txt').textContent = 'Showing 0 of 0 issued certificates';
        return;
    }

    tbody.innerHTML = list.map(c => `
        <tr>
            <td>
                <div class="trainee-cell">
                    <div class="mini-avatar-placeholder" style="width:32px;height:32px;border-radius:50%;background:#e0f0d0;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#4B8423;flex-shrink:0">
                        ${c.name.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <div class="trainee-name">${c.name}</div>
                        <div class="trainee-email">${c.email}</div>
                    </div>
                </div>
            </td>
            <td>${c.workshop}</td>
            <td>
                <span style="font-family:monospace;font-size:var(--text-body-sm);font-weight:var(--weight-semibold);color:#4B8423">
                    ${c.cert_no}
                </span>
            </td>
            <td>${c.issued_on}</td>
            <td>
                <span class="badge ${c.sms_sent ? 'badge-issued' : 'badge-pending'}">
                    ${c.sms_sent ? 'Sent' : 'Pending'}
                </span>
            </td>
            <td style="display:flex;gap:0.4rem;align-items:center">
                <button class="btn-sm" onclick="openViewCert(${c.id})">View</button>
                <button class="btn-sm" onclick="openResendConfirm(${c.id})" title="Resend SMS">📱</button>
            </td>
        </tr>`).join('');

    document.getElementById('issued-count-txt').textContent =
        `Showing ${list.length} of ${list.length} issued certificates`;
}

/* =============================================================================
   POPULATE WORKSHOP FILTER
   ============================================================================= */
function populateWorkshopFilter() {
    const sel = document.getElementById('issued-filter-workshop');
    if (!sel) return;
    // Get unique workshops from issued
    const allWorkshops = [...new Set([...ELIGIBLE.map(e => e.workshop), ...ISSUED.map(i => i.workshop)])];
    sel.innerHTML = '<option value="">All Workshops</option>' +
        allWorkshops.map(w => `<option value="${w}">${w}</option>`).join('');
}

/* =============================================================================
   SEARCH & FILTER
   ============================================================================= */
function filterEligible() {
    const search = document.getElementById('elig-search')?.value.trim().toLowerCase() || '';
    const rate   = document.getElementById('elig-filter-rate')?.value || '';
    const list   = ELIGIBLE.filter(t => {
        const ms = !search || t.name.toLowerCase().includes(search) || t.workshop.toLowerCase().includes(search);
        const mr = !rate   || t.rate >= parseInt(rate);
        return ms && mr;
    });
    renderEligible(list);
}

function filterIssued() {
    const search   = document.getElementById('issued-search')?.value.trim() || '';
    const workshop = document.getElementById('issued-filter-workshop')?.value || '';
    const sort     = document.getElementById('issued-sort')?.value || 'newest';
    loadIssued(search, workshop, sort);
}

/* =============================================================================
   ISSUE CERTIFICATE — Confirm Modal
   ============================================================================= */
function openIssueConfirm(certId) {
    const t = ELIGIBLE.find(x => x.id === certId);
    if (!t) return;
    pendingCertId = certId;

    document.getElementById('confirm-details').innerHTML = `
        <div style="display:flex;align-items:center;gap:1rem">
            <div style="width:44px;height:44px;border-radius:50%;background:#e0f0d0;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;color:#4B8423;flex-shrink:0">
                ${t.name.charAt(0).toUpperCase()}
            </div>
            <div class="confirm-info">
                <span class="confirm-name">${t.name}</span>
                <span class="confirm-meta">${t.email}</span>
                <span class="confirm-meta">${t.workshop} · ${t.sessions_done}/${t.sessions_req} sessions</span>
                <span class="confirm-meta">Completed: ${t.completed_on}</span>
            </div>
            <span class="confirm-rate">${t.rate}%</span>
        </div>`;

    document.getElementById('confirm-sms-preview').innerHTML = `
        📱 <strong>SMS Preview:</strong><br>
        <span style="font-size:var(--text-body-sm)">"Congratulations, ${t.name}! Your certificate for
        <em>${t.workshop}</em> has been issued by UGAT Integrated Farm. Thank you for completing the program!"</span>`;

    document.getElementById('confirm-contact').value = t.contact || '';
    openModal('issue-confirm-modal');
}

async function confirmIssuance() {
    if (!pendingCertId) return;
    const t       = ELIGIBLE.find(x => x.id === pendingCertId);
    const contact = document.getElementById('confirm-contact').value.trim();

    try {
        const r = await fetch('save_certifications.php', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'issue_certificate', cert_id: pendingCertId, contact }),
        });
        const d = await r.json();
        if (d.success) {
            closeModal('issue-confirm-modal');
            showToast(`✅ Certificate ${d.cert_no} issued to ${t?.name}!`);
            pendingCertId = null;
            // Reload all data
            await Promise.all([loadStats(), loadEligible(), loadIssued()]);
        } else {
            showToast('❌ ' + d.message);
        }
    } catch(e) { showToast('Could not issue certificate.'); }
}

/* =============================================================================
   VIEW CERTIFICATE MODAL
   ============================================================================= */
function openViewCert(certId) {
    const c = ISSUED.find(x => x.id === certId);
    if (!c) return;
    viewingCertId = certId;

    document.getElementById('view-cert-sub').textContent =
        `${c.cert_no} · Issued ${c.issued_on}`;

    document.getElementById('cert-preview-body').innerHTML = `
        <div class="cert-org-name">🌱 UGAT Integrated Farm</div>
        <div class="cert-title-label">This is to certify that</div>
        <div class="cert-trainee-name">${c.name}</div>
        <div class="cert-award-text">has successfully completed the workshop</div>
        <div class="cert-workshop-name">${c.workshop}</div>
        <div class="cert-award-text" style="font-size:var(--text-caption)">
            with an attendance rate of <strong>${c.rate}%</strong>
            (${c.sessions_done} / ${c.sessions_req} sessions completed)
        </div>
        <div class="cert-footer-row">
            <div class="cert-footer-col">
                <div class="cert-footer-line"></div>
                <div class="cert-footer-label">Issued by</div>
                <div class="cert-footer-value">UGAT Integrated Farm</div>
            </div>
            <div class="cert-footer-col" style="align-items:center">
                <div style="font-size:var(--text-tiny);color:var(--color-text-mid)">${c.cert_no}</div>
                <div style="font-size:var(--text-tiny);color:var(--color-text-mid)">${c.issued_on}</div>
            </div>
            <div class="cert-footer-col">
                <div class="cert-footer-line"></div>
                <div class="cert-footer-label">Facilitator / Director</div>
                <div class="cert-footer-value">UGAT Program Officer</div>
            </div>
        </div>`;

    document.getElementById('cert-detail-table').innerHTML = `
        <tr><td>Trainee Name</td><td>${c.name}</td></tr>
        <tr><td>Email</td><td>${c.email}</td></tr>
        <tr><td>Contact</td><td>${c.contact || '—'}</td></tr>
        <tr><td>Workshop</td><td>${c.workshop}</td></tr>
        <tr><td>Sessions Completed</td><td>${c.sessions_done} / ${c.sessions_req}</td></tr>
        <tr><td>Attendance Rate</td><td>${c.rate}%</td></tr>
        <tr><td>Certificate No.</td><td style="font-family:monospace;color:#4B8423">${c.cert_no}</td></tr>
        <tr><td>Issued On</td><td>${c.issued_on}</td></tr>
        <tr><td>SMS Sent</td><td id="sms-status-cell">${c.sms_sent ? '✅ Yes' : '⏳ Pending'}</td></tr>`;

    openModal('view-cert-modal');
}

async function downloadCertificate() {
    const c = ISSUED.find(x => x.id === viewingCertId);
    if (!c) return;

    const el = document.getElementById('cert-preview-body');
    if (!el) return;

    showToast('📥 Generating PDF…');
    try {
        const canvas  = await html2canvas(el, { scale: 2, useCORS: true, backgroundColor: '#ffffff', logging: false });
        const imgData = canvas.toDataURL('image/png');
        const w = canvas.width / 2;
        const h = canvas.height / 2;
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF({ orientation: w > h ? 'landscape' : 'portrait', unit: 'px', format: [w, h] });
        pdf.addImage(imgData, 'PNG', 0, 0, w, h);
        const safeName = c.cert_no.replace(/[^a-zA-Z0-9]/g, '-');
        pdf.save(`certificate-${safeName}.pdf`);
        showToast('✅ Certificate downloaded!');
    } catch (e) {
        console.error('Certificate download error:', e);
        showToast('❌ Download failed. Please try again.');
    }
}

async function resendSMS() {
    const c = ISSUED.find(x => x.id === viewingCertId);
    if (!c) return;
    showToast(`📱 Sending SMS to ${c.contact || 'trainee'}…`);
    try {
        const r = await fetch('save_certifications.php', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'resend_sms', cert_id: viewingCertId, contact: c.contact }),
        });
        const d = await r.json();
        if (d.success) {
            c.sms_sent = true;
            const cell = document.getElementById('sms-status-cell');
            if (cell) cell.textContent = '✅ Yes';
            renderIssued(ISSUED);
            showToast(`✅ SMS sent to ${c.contact || 'trainee'}`);
        } else {
            showToast('❌ ' + d.message);
        }
    } catch(e) { showToast('Could not resend SMS.'); }
}

async function openResendConfirm(certId) {
    const c = ISSUED.find(x => x.id === certId);
    if (!c) return;
    viewingCertId = certId;
    showToast(`📱 Sending SMS to ${c.contact || 'trainee'}…`);
    try {
        const r = await fetch('save_certifications.php', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'resend_sms', cert_id: certId, contact: c.contact }),
        });
        const d = await r.json();
        if (d.success) {
            c.sms_sent = true;
            renderIssued(ISSUED);
            showToast(`✅ SMS sent to ${c.contact || 'trainee'}`);
        } else {
            showToast('❌ ' + d.message);
        }
    } catch(e) { showToast('Could not send SMS.'); }
}

/* =============================================================================
   EXPORT MODAL
   ============================================================================= */
function openExportModal() {
    const today = new Date().toISOString().split('T')[0];
    const toEl  = document.getElementById('exp-to');
    if (toEl) toEl.value = today;
    openModal('export-modal');
}

function doExport() {
    const format = document.getElementById('exp-format')?.value || 'csv';
    const scope  = document.getElementById('exp-scope')?.value  || 'all';
    const from   = document.getElementById('exp-from')?.value   || '';
    const to     = document.getElementById('exp-to')?.value     || '';
    closeModal('export-modal');
    showToast(`📥 Exporting certificates as ${format.toUpperCase()}…`);
    const params = new URLSearchParams({ scope, format });
    if (from) params.set('from', from);
    if (to)   params.set('to',   to);
    window.open('export_certificates.php?' + params.toString(), '_blank');
}

/* =============================================================================
   MODAL + TOAST HELPERS
   ============================================================================= */
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
function closeModalOutside(e, id) { if (e.target.id === id) closeModal(id); }

function showToast(msg) {
    const t = document.getElementById('toast'); if (!t) return;
    t.textContent = msg; t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}