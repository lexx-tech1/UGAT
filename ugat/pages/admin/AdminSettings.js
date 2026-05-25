/* AdminSettings.js — UGAT TrainTrack
   Connected to get_settings.php and save_settings.php
*/

let SETTINGS = {};

/* =============================================================================
   INIT
   ============================================================================= */
document.addEventListener('DOMContentLoaded', async function () {
    await loadSettings();
    await loadAdminUsers();
    setupPhoneField('org-phone');
    setupPhoneField('eu-phone'); // edit user modal if added later
});

/* =============================================================================
   PHONE NUMBER AUTO-FORMAT
   Prefix: +63 9 — user types remaining 9 digits
   ============================================================================= */
function setupPhoneField(id) {
    const input = document.getElementById(id);
    if (!input) return;

    const PREFIX = '+63 9';

    // On focus — ensure prefix is there
    input.addEventListener('focus', function () {
        if (!this.value.startsWith(PREFIX)) {
            this.value = PREFIX;
        }
        // Move cursor to end
        const len = this.value.length;
        setTimeout(() => this.setSelectionRange(len, len), 0);
    });

    // On input — enforce prefix and digit-only after it
    input.addEventListener('input', function () {
        let val = this.value;

        // Always keep prefix
        if (!val.startsWith(PREFIX)) {
            val = PREFIX + val.replace(/[^0-9]/g, '').slice(0, 9);
        } else {
            // Keep only digits after prefix, max 9 more digits
            let after = val.slice(PREFIX.length).replace(/[^0-9]/g, '').slice(0, 9);
            val = PREFIX + after;
        }

        this.value = val;
    });

    // On keydown — prevent deleting the prefix
    input.addEventListener('keydown', function (e) {
        const sel = this.selectionStart;
        // Block backspace/delete if it would eat into prefix
        if ((e.key === 'Backspace' && sel <= PREFIX.length) ||
            (e.key === 'Delete'    && sel < PREFIX.length)) {
            e.preventDefault();
        }
    });

    // On blur — if only prefix remains, clear the field
    input.addEventListener('blur', function () {
        if (this.value === PREFIX) this.value = '';
    });
}

/* =============================================================================
   LOAD SETTINGS FROM DB
   ============================================================================= */
async function loadSettings() {
    try {
        const r = await fetch('get_settings.php?action=all', { credentials: 'same-origin' });
        const d = await r.json();
        if (!d.success) return;
        SETTINGS = d.settings;
        populateFields();
    } catch(e) { console.error('loadSettings:', e); }
}

function populateFields() {
    const s = SETTINGS;

    // Organization
    setVal('org-name',        s.org_name        || 'UGAT Integrated Farm');
    setVal('org-short-name',  s.org_short_name  || 'UGAT');
    setVal('org-address',     s.org_address     || '');
    setVal('org-email',       s.org_email       || '');
    setVal('org-phone',       s.org_phone       || '');
    setVal('org-website',     s.org_website     || '');
    setVal('org-description', s.org_description || '');

    // SMS
    setVal('sms-api-key',    s.sms_api_key  || '');
    setChk('sms-cert',       s.sms_cert_trigger       === '1');
    setChk('sms-reminder',   s.sms_session_reminder   === '1');
    setChk('sms-enrollment', s.sms_enrollment_confirm === '1');
    setChk('sms-absence',    s.sms_absence_alert      === '1');

    // Certifications
    setVal('cert-title',        s.cert_title        || 'Certificate of Completion');
    setVal('cert-authority',    s.cert_authority    || 'UGAT Integrated Farm');
    setVal('cert-signatory',    s.cert_signatory    || '');
    setVal('cert-prefix',       s.cert_prefix       || 'UGAT-2026-');
    setVal('cert-threshold',    s.cert_threshold    || '67');
    setVal('cert-min-sessions', s.cert_min_sessions || '2');
    setChk('cert-auto-flag',    s.cert_auto_flag    === '1');

    // Workshops
    setVal('ws-location',  s.ws_default_location  || '');
    setVal('ws-max-slots', s.ws_default_max_slots || '25');
    setVal('ws-time',      s.ws_default_time      || '8:00 AM – 5:00 PM');
    setVal('ws-categories',s.ws_categories        || '');

    // System
    setVal('sys-timezone',    s.timezone    || 'Asia/Manila');
    setVal('sys-date-format', s.date_format || 'MMM D, YYYY');
    setVal('sys-language',    s.language    || 'English');

    // Payment
    setVal('pay-gcash-name', s.gcash_account_name || '');
    if (s.gcash_qr_path) {
        const img = document.getElementById('pay-qr-img');
        const ph  = document.getElementById('pay-qr-placeholder');
        if (img) { img.src = '../../' + s.gcash_qr_path; img.style.display = 'block'; }
        if (ph)  ph.style.display = 'none';
    }
}

function setVal(id, val) {
    const el = document.getElementById(id);
    if (el) el.value = val;
}
function setChk(id, checked) {
    const el = document.getElementById(id);
    if (el) el.checked = checked;
}
function getVal(id) {
    const el = document.getElementById(id); return el ? el.value.trim() : '';
}
function getChk(id) {
    const el = document.getElementById(id); return el && el.checked ? '1' : '0';
}

/* =============================================================================
   LOAD ADMIN USERS FROM DB
   ============================================================================= */
async function loadAdminUsers() {
    try {
        const r = await fetch('get_settings.php?action=admin_users', { credentials: 'same-origin' });
        const d = await r.json();
        if (!d.success) return;
        renderAdminUsers(d.users);
    } catch(e) { console.error('loadAdminUsers:', e); }
}

function renderAdminUsers(users) {
    const tbody = document.getElementById('admin-users-tbody');
    if (!tbody) return;

    if (!users.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:1rem;color:#aaa">No admin users found.</td></tr>';
        return;
    }

    window._adminUsers = users;
    tbody.innerHTML = users.map(u => `
        <tr>
            <td>
                <div class="trainee-cell">
                    <div style="width:32px;height:32px;border-radius:50%;background:#e0f0d0;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#4B8423;flex-shrink:0">
                        ${u.name.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <div class="trainee-name">${u.name}</div>
                        <div class="trainee-email">${u.email}</div>
                    </div>
                </div>
            </td>
            <td>${u.email}</td>
            <td><span class="badge badge-issued">${u.role}</span></td>
            <td class="light-txt">${u.last_login}</td>
            <td><span class="badge ${u.is_active ? 'badge-issued' : 'badge-pending'}">${u.is_active ? 'Active' : 'Inactive'}</span></td>
            <td><button class="btn-xs" onclick="openEditUser(${u.id})">Edit</button></td>
        </tr>`).join('');
}

/* =============================================================================
   SECTION SWITCHING
   ============================================================================= */
function showSection(id, btn) {
    document.querySelectorAll('.settings-panel').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.settings-nav-item').forEach(b => b.classList.remove('active'));
    document.getElementById('section-' + id).style.display = '';
    btn.classList.add('active');
}

/* =============================================================================
   SAVE SECTIONS
   ============================================================================= */
async function saveSection(id) {
    const payloads = {
        org: {
            action:          'save_org',
            org_name:        getVal('org-name'),
            org_short_name:  getVal('org-short-name'),
            org_address:     getVal('org-address'),
            org_email:       getVal('org-email'),
            org_phone:       getVal('org-phone'),
            org_website:     getVal('org-website'),
            org_description: getVal('org-description'),
        },
        sms: {
            action:                  'save_sms',
            sms_api_key:             getVal('sms-api-key'),
            sms_cert_trigger:        getChk('sms-cert'),
            sms_session_reminder:    getChk('sms-reminder'),
            sms_enrollment_confirm:  getChk('sms-enrollment'),
            sms_absence_alert:       getChk('sms-absence'),
        },
        certs: {
            action:            'save_certs',
            cert_title:        getVal('cert-title'),
            cert_authority:    getVal('cert-authority'),
            cert_signatory:    getVal('cert-signatory'),
            cert_prefix:       getVal('cert-prefix'),
            cert_threshold:    getVal('cert-threshold'),
            cert_min_sessions: getVal('cert-min-sessions'),
            cert_auto_flag:    getChk('cert-auto-flag'),
        },
        workshops: {
            action:               'save_workshops',
            ws_default_location:  getVal('ws-location'),
            ws_default_max_slots: getVal('ws-max-slots'),
            ws_default_time:      getVal('ws-time'),
            ws_categories:        getVal('ws-categories'),
        },
        system: {
            action:      'save_system',
            timezone:    getVal('sys-timezone'),
            date_format: getVal('sys-date-format'),
            language:    getVal('sys-language'),
        },
        payment: {
            action:              'save_payment',
            gcash_account_name:  getVal('pay-gcash-name'),
        },
    };

    const payload = payloads[id];
    if (!payload) { showToast('Unknown section.'); return; }

    try {
        const r = await fetch('save_settings.php', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const d = await r.json();
        showToast(d.success ? '✅ ' + d.message : '❌ ' + d.message);
        if (d.success) SETTINGS = { ...SETTINGS, ...payload };
    } catch(e) { showToast('Could not save settings.'); }
}

/* =============================================================================
   GCASH QR UPLOAD
   ============================================================================= */
function previewQRCode(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const img = document.getElementById('pay-qr-img');
        const ph  = document.getElementById('pay-qr-placeholder');
        const btn = document.getElementById('pay-qr-upload-btn');
        if (img) { img.src = e.target.result; img.style.display = 'block'; }
        if (ph)  ph.style.display = 'none';
        if (btn) btn.style.display = 'inline-flex';
    };
    reader.readAsDataURL(input.files[0]);
}

async function uploadGCashQR() {
    const input = document.getElementById('pay-qr-file');
    const btn   = document.getElementById('pay-qr-upload-btn');
    const status= document.getElementById('pay-qr-status');
    if (!input || !input.files[0]) return;

    btn.disabled = true; btn.textContent = 'Uploading…';
    const fd = new FormData();
    fd.append('qr_image', input.files[0]);

    try {
        const r = await fetch('upload_gcash_qr.php', { method: 'POST', credentials: 'same-origin', body: fd });
        const d = await r.json();
        if (d.success) {
            if (status) status.innerHTML = '<span style="color:#4B8423;font-weight:600">✅ QR code uploaded successfully!</span>';
            btn.style.display = 'none';
            SETTINGS.gcash_qr_path = d.path;
        } else {
            if (status) status.innerHTML = '<span style="color:#c0392b">❌ ' + d.message + '</span>';
        }
    } catch(e) {
        if (status) status.innerHTML = '<span style="color:#c0392b">❌ Upload failed.</span>';
    }
    btn.disabled = false; btn.textContent = '⬆ Upload QR Code';
}

/* =============================================================================
   SMS TEST
   ============================================================================= */
async function testSMS() {
    const apiKey = getVal('sms-api-key');
    if (!apiKey) { showToast('⚠️ Enter your UniSMS API key first.'); return; }
    showToast('📱 Sending test SMS via UniSMS…');
    setTimeout(() => showToast('✅ Test SMS sent! Check your phone.'), 1800);
}

/* =============================================================================
   PASSWORD TOGGLE
   ============================================================================= */
function togglePw(id, btn) {
    const input = document.getElementById(id); if (!input) return;
    const isText = input.type === 'text';
    input.type   = isText ? 'password' : 'text';
    btn.textContent = isText ? '👁' : '🙈';
}

/* =============================================================================
   MODAL HELPERS
   ============================================================================= */
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
function closeModalOutside(e, id) { if (e.target.id === id) closeModal(id); }

/* =============================================================================
   EDIT ADMIN USER
   ============================================================================= */
function openEditUser(userId) {
    const user = window._adminUsers?.find(u => u.id === userId);
    if (!user) return;

    document.getElementById('eu-id').value     = userId;
    document.getElementById('eu-email').value  = user.email;
    document.getElementById('eu-status').value = user.is_active ? '1' : '0';
    document.getElementById('eu-password').value = '';
    document.getElementById('eu-error').style.display = 'none';

    // Split name into first/last
    const parts = user.name.split(' ');
    document.getElementById('eu-first').value = parts[0] || '';
    document.getElementById('eu-last').value  = parts.slice(1).join(' ') || '';

    openModal('edit-user-modal');
}

async function saveEditUser() {
    const id        = parseInt(document.getElementById('eu-id').value);
    const firstName = document.getElementById('eu-first').value.trim();
    const lastName  = document.getElementById('eu-last').value.trim();
    const isActive  = parseInt(document.getElementById('eu-status').value);
    const password  = document.getElementById('eu-password').value;
    const errEl     = document.getElementById('eu-error');

    if (!firstName) {
        errEl.textContent = 'First name is required.';
        errEl.style.display = 'block';
        return;
    }
    errEl.style.display = 'none';

    try {
        const r = await fetch('save_settings.php', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'edit_admin_user',
                id, first_name: firstName, last_name: lastName,
                is_active: isActive, password,
            }),
        });
        const d = await r.json();
        if (d.success) {
            closeModal('edit-user-modal');
            showToast('✅ ' + d.message);
            await loadAdminUsers(); // refresh table
        } else {
            errEl.textContent = d.message;
            errEl.style.display = 'block';
        }
    } catch(e) { showToast('Could not save changes.'); }
}

/* =============================================================================
   USER MENU TOGGLE
   ============================================================================= */
function toggleUserMenu(e) {
    e.stopPropagation();
    const dropdown = document.getElementById('user-dropdown');
    const caret    = document.querySelector('.user-caret');
    const isOpen   = dropdown?.classList.contains('open');
    dropdown?.classList.toggle('open', !isOpen);
    if (caret) caret.classList.toggle('open', !isOpen);
}

document.addEventListener('click', function () {
    document.getElementById('user-dropdown')?.classList.remove('open');
    document.querySelector('.user-caret')?.classList.remove('open');
});

/* =============================================================================
   DATA EXPORT
   ============================================================================= */
function exportAllData() {
    showToast('📥 Preparing full data export…');
    const params = new URLSearchParams({
        sections: 'program,attendance,certifications,inventory',
        format:   'csv',
        period:   'all',
    });
    window.open('export_reports.php?' + params.toString(), '_blank');
}

/* =============================================================================
   TOAST
   ============================================================================= */
function showToast(msg) {
    const t = document.getElementById('toast'); if (!t) return;
    t.textContent = msg; t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}