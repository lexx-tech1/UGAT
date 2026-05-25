/**
 * TraineeProfile.js — UGAT TrainTrack
 * Loads real trainee data into profile page and handles all Save actions.
 */

document.addEventListener('DOMContentLoaded', async function () {
    await loadProfile();
    await loadNotifPrefs();
});

window.addEventListener('beforeunload', function () {
    const smsOn   = document.getElementById('notif-sms')?.checked   ?? true;
    const emailOn = document.getElementById('notif-email')?.checked  ?? false;
    const email   = document.getElementById('notif-email-addr')?.value ?? '';
    const payload = JSON.stringify({ phone_enabled: smsOn, email_enabled: emailOn, email });
    navigator.sendBeacon('update_notification_preferences.php', new Blob([payload], { type: 'application/json' }));
});

/* =============================================================================
   LOAD PROFILE
   ============================================================================= */
async function loadProfile() {
    try {
        const response = await fetch('../auth/get_session.php?role=trainee&t=' + Date.now());
        const data     = await response.json();
        if (!data.success) return; // nav_session.js handles the redirect
        fillProfile(data.user);
    } catch (err) {
        console.error('Profile fetch error:', err);
    }
}

/* =============================================================================
   FILL ALL FIELDS
   ============================================================================= */
function fillProfile(u) {
    const fullName   = `${u.first_name || ''} ${u.last_name || ''}`.trim();
    const profilePic = u.profile_pic
        ? '/UGAT/' + u.profile_pic
        : `https://ui-avatars.com/api/?name=${encodeURIComponent(fullName)}&background=4B8423&color=fff&size=128`;

    // Sidebar
    const avatarImg = document.getElementById('profile-avatar-img');
    if (avatarImg) { avatarImg.src = profilePic; avatarImg.alt = fullName; }
    setText('display-name',  fullName);
    setText('display-email', u.email || '');

    const sinceEl = document.querySelector('.profile-since');
    if (sinceEl && (u.date_enrolled || u.created_at)) {
        const d = new Date(u.date_enrolled || u.created_at);
        sinceEl.textContent = 'Enrolled since ' + d.toLocaleDateString('en-US', { month:'long', year:'numeric' });
    }

    // Nav
    document.querySelectorAll('.user-avatar, .user-dropdown-avatar').forEach(img => {
        img.src = profilePic; img.alt = fullName;
    });
    const navName = document.querySelector('.user-dropdown-name');
    if (navName) navName.innerHTML = `${u.first_name || ''} <span class="user-caret">▾</span>`;
    const dropName = document.querySelector('.user-dropdown-fullname');
    if (dropName) dropName.textContent = fullName;

    // Personal Information
    setVal('p-firstname',   u.first_name   || '');
    setVal('p-lastname',    u.last_name    || '');
    setVal('p-middlename',  u.middle_name  || '');
    setVal('p-nationality', u.nationality  || 'Filipino');
    setVal('p-email',       u.email        || '');
    setVal('p-contact',     u.phone        || '');
    setVal('p-address',     u.address      || '');
    setVal('p-city',        u.city         || '');
    setVal('p-province',    u.province     || '');
    setVal('p-region',      u.region       || '');

    // Background Information
    if (u.birthdate)     setVal('p-dob',          u.birthdate);
    if (u.gender)        setSelectVal('p-sex',     capitalize(u.gender));
    if (u.civil_status)  setSelectVal('p-civil',   u.civil_status);
    setVal('p-bplace',   u.birthplace_city || '');
    setVal('p-bprov',    u.birthplace_prov || '');
    setVal('p-breg',     u.birthplace_reg  || '');
    if (u.education)     setSelectVal('p-edu',     u.education);
    if (u.employment)    setSelectVal('p-emp',     u.employment);
    if (u.learner_class) setSelectVal('p-class',   u.learner_class);
    setSelectVal('p-pwd', u.is_pwd ? 'Yes – PWD' : 'No');
    setVal('p-parent',    u.guardian_name  || '');
    setVal('p-parentaddr',u.guardian_addr  || '');
}

/* =============================================================================
   SAVE PERSONAL INFO — includes email
   ============================================================================= */
async function savePersonalInfo() {
    const formData = new FormData();
    formData.append('first_name',  getVal('p-firstname'));
    formData.append('last_name',   getVal('p-lastname'));
    formData.append('middle_name', getVal('p-middlename'));
    formData.append('nationality', getVal('p-nationality'));
    formData.append('email',       getVal('p-email'));
    formData.append('phone',       getVal('p-contact'));
    formData.append('address',     getVal('p-address'));
    formData.append('city',        getVal('p-city'));
    formData.append('province',    getVal('p-province'));
    formData.append('region',      getVal('p-region'));

    await postUpdate(formData, 'Personal information saved!');
}

/* =============================================================================
   SAVE BACKGROUND INFO
   ============================================================================= */
async function saveBackground() {
    const formData = new FormData();
    formData.append('gender',          getVal('p-sex').toLowerCase());
    formData.append('civil_status',    getVal('p-civil'));
    formData.append('birthdate',       getVal('p-dob'));
    formData.append('birthplace_city', getVal('p-bplace'));
    formData.append('birthplace_prov', getVal('p-bprov'));
    formData.append('birthplace_reg',  getVal('p-breg'));
    formData.append('education',       getVal('p-edu'));
    formData.append('employment',      getVal('p-emp'));
    formData.append('learner_class',   getVal('p-class'));
    formData.append('is_pwd',          getVal('p-pwd') === 'Yes – PWD' ? 1 : 0);
    formData.append('guardian_name',   getVal('p-parent'));
    formData.append('guardian_addr',   getVal('p-parentaddr'));

    await postUpdate(formData, 'Background information saved!');
}

/* =============================================================================
   SHARED POST HELPER
   ============================================================================= */
async function postUpdate(formData, successMsg) {
    try {
        const response = await fetch('../auth/update_profile.php', { method: 'POST', body: formData });
        const data     = await response.json();
        if (data.success) {
            showToast(successMsg);
            await loadProfile();
        } else {
            showToast(data.message || 'Save failed.');
        }
    } catch (err) {
        showToast('Could not connect to server.');
    }
}

/* =============================================================================
   AVATAR UPLOAD — sends to upload_avatar.php
   ============================================================================= */
async function previewAvatar(input) {
    if (!input.files || !input.files[0]) return;

    const file = input.files[0];

    // Preview immediately
    const reader = new FileReader();
    reader.onload = function (e) {
        const avatarImg = document.getElementById('profile-avatar-img');
        if (avatarImg) avatarImg.src = e.target.result;
        document.querySelectorAll('.user-avatar, .user-dropdown-avatar').forEach(img => {
            img.src = e.target.result;
        });
    };
    reader.readAsDataURL(file);

    // Upload to server
    const formData = new FormData();
    formData.append('avatar', file);

    try {
        const response = await fetch('../auth/upload_avatar.php', { method: 'POST', body: formData });
        const data     = await response.json();
        if (data.success) {
            showToast('Profile picture updated!');
            // Update all avatar images with the saved URL
            document.querySelectorAll('#profile-avatar-img, .user-avatar, .user-dropdown-avatar').forEach(img => {
                img.src = data.pic_url + '?t=' + Date.now();
            });
        } else {
            showToast(data.message || 'Avatar upload failed.');
        }
    } catch (err) {
        showToast('Could not upload image.');
    }
}

/* =============================================================================
   CHANGE PASSWORD
   ============================================================================= */
async function savePassword() {
    const current = document.getElementById('p-current-pw')?.value || '';
    const newPw   = document.getElementById('p-new-pw')?.value     || '';
    const confirm = document.getElementById('p-confirm-pw')?.value || '';
    const errEl   = document.getElementById('pw-error');

    if (errEl) errEl.style.display = 'none';

    if (!current || !newPw || !confirm) {
        if (errEl) { errEl.textContent = 'All password fields are required.'; errEl.style.display = 'block'; } return;
    }
    if (newPw.length < 8) {
        if (errEl) { errEl.textContent = 'New password must be at least 8 characters.'; errEl.style.display = 'block'; } return;
    }
    if (newPw !== confirm) {
        if (errEl) { errEl.textContent = 'Passwords do not match.'; errEl.style.display = 'block'; } return;
    }

    const formData = new FormData();
    formData.append('current_password', current);
    formData.append('new_password',     newPw);

    try {
        const response = await fetch('../auth/change_password.php', { method: 'POST', body: formData });
        const data     = await response.json();
        if (data.success) {
            showToast('Password updated successfully!');
            ['p-current-pw','p-new-pw','p-confirm-pw'].forEach(id => {
                const el = document.getElementById(id); if (el) el.value = '';
            });
        } else {
            if (errEl) { errEl.textContent = data.message || 'Update failed.'; errEl.style.display = 'block'; }
        }
    } catch (err) { showToast('Could not connect to server.'); }
}

/* =============================================================================
   PASSWORD STRENGTH
   ============================================================================= */
function checkPwStrength(value) {
    const fill  = document.getElementById('pw-strength-fill');
    const label = document.getElementById('pw-strength-label');
    if (!fill || !label) return;
    let score = 0;
    if (value.length >= 8)          score++;
    if (/[A-Z]/.test(value))        score++;
    if (/[0-9]/.test(value))        score++;
    if (/[^A-Za-z0-9]/.test(value)) score++;
    const levels = [
        { pct:'25%', color:'#e74c3c', text:'Weak' },
        { pct:'50%', color:'#e67e22', text:'Fair' },
        { pct:'75%', color:'#f1c40f', text:'Good' },
        { pct:'100%',color:'#4B8423', text:'Strong' },
    ];
    const level = levels[score - 1] || { pct:'0%', color:'transparent', text:'' };
    fill.style.width      = level.pct;
    fill.style.background = level.color;
    label.textContent     = level.text;
}


/* =============================================================================
   PASSWORD TOGGLE
   ============================================================================= */
function togglePw(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    if (btn) btn.textContent = isHidden ? '🙈' : '👁';
}

/* =============================================================================
   NOTIFICATION PREFERENCES
   ============================================================================= */
async function loadNotifPrefs() {
    try {
        const r = await fetch('get_notification_preferences.php', { credentials: 'same-origin' });
        const d = await r.json();
        if (!d.success) return;
        const p = d.data;

        const smsChk   = document.getElementById('notif-sms');
        const emailChk = document.getElementById('notif-email');
        const phoneEl  = document.getElementById('notif-phone-display');
        const emailEl  = document.getElementById('notif-email-addr');

        if (smsChk)   smsChk.checked   = p.phone_enabled;
        if (emailChk) emailChk.checked = p.email_enabled;
        if (phoneEl && p.phone)  phoneEl.textContent = 'Number: ' + p.phone;
        if (emailEl && p.email)  emailEl.value = p.email;

        toggleEmailSection();
        if (p.email_enabled) renderEmailStatus(p.email_verified, p.email);
    } catch(e) { console.error('loadNotifPrefs:', e); }
}

function toggleEmailSection() {
    const on  = document.getElementById('notif-email')?.checked;
    const sec = document.getElementById('notif-email-section');
    if (sec) sec.style.display = on ? 'block' : 'none';
}

function renderEmailStatus(verified, email) {
    const el = document.getElementById('notif-email-status');
    if (!el) return;
    if (verified) {
        el.innerHTML = '<span style="color:#4B8423;font-weight:600">✅ Verified: ' + (email || '') + '</span>';
    } else if (email) {
        el.innerHTML = '<span style="color:#e67e22;font-weight:600">⚠ Not verified yet — send a code to verify this address.</span>';
    } else {
        el.innerHTML = '';
    }
}

async function saveNotifPrefs() {
    const smsOn   = document.getElementById('notif-sms')?.checked   ?? true;
    const emailOn = document.getElementById('notif-email')?.checked  ?? false;
    const email   = getVal('notif-email-addr');

    try {
        const r = await fetch('update_notification_preferences.php', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone_enabled: smsOn, email_enabled: emailOn, email }),
            keepalive: true,
        });
        const d = await r.json();
        showToast(d.success ? '✅ Preferences saved!' : '❌ ' + (d.message || 'Save failed.'));
    } catch(e) { showToast('Could not save preferences.'); }
}

async function sendEmailVerif() {
    const email = getVal('notif-email-addr');
    if (!email) { showToast('Enter an email address first.'); return; }

    const btn = document.getElementById('notif-send-code-btn');
    if (btn) { btn.disabled = true; btn.textContent = 'Sending…'; }

    try {
        const fd = new FormData();
        fd.append('email', email);
        const r = await fetch('send_verification_email.php', { method: 'POST', credentials: 'same-origin', body: fd });
        const d = await r.json();

        if (d.success) {
            showToast('✅ Code sent! Check your inbox.');
            const codeRow = document.getElementById('notif-code-row');
            if (codeRow) codeRow.style.display = 'block';
            renderEmailStatus(false, email);
        } else {
            showToast('❌ ' + (d.message || 'Could not send code.'));
        }
    } catch(e) { showToast('Could not send verification email.'); }

    if (btn) { btn.disabled = false; btn.textContent = 'Send Code'; }
}

async function verifyEmailCode() {
    const email = getVal('notif-email-addr');
    const code  = getVal('notif-code-input');
    if (!code || code.length !== 6) { showToast('Enter the 6-digit code.'); return; }

    try {
        const fd = new FormData();
        fd.append('email', email);
        fd.append('code',  code);
        const r = await fetch('verify_email_code.php', { method: 'POST', credentials: 'same-origin', body: fd });
        const d = await r.json();

        if (d.success) {
            showToast('✅ Email verified!');
            const codeRow = document.getElementById('notif-code-row');
            if (codeRow) codeRow.style.display = 'none';
            renderEmailStatus(true, email);
        } else {
            showToast('❌ ' + (d.message || 'Invalid code.'));
        }
    } catch(e) { showToast('Could not verify code.'); }
}

/* =============================================================================
   HELPERS
   ============================================================================= */
function setVal(id, value)  { const el = document.getElementById(id); if (el) el.value = value; }
function getVal(id)         { return document.getElementById(id)?.value.trim() || ''; }
function setText(id, value) { const el = document.getElementById(id); if (el) el.textContent = value; }
function setSelectVal(id, value) {
    const el = document.getElementById(id);
    if (!el || !value) return;
    for (let i = 0; i < el.options.length; i++) {
        if (el.options[i].text.toLowerCase() === value.toLowerCase()) { el.selectedIndex = i; break; }
    }
}
function capitalize(str) { return str ? str.charAt(0).toUpperCase() + str.slice(1) : ''; }

/* =============================================================================
   TOAST
   ============================================================================= */
function showToast(message) {
    const toast = document.getElementById('toast');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}