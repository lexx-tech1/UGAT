/**
 * AdminProfile.js — UGAT TrainTrack
 * Loads real admin data and handles Save/Password/Avatar.
 */

document.addEventListener('DOMContentLoaded', async function () {
    await loadAdminProfile();
});

/* =============================================================================
   LOAD PROFILE
   ============================================================================= */
async function loadAdminProfile() {
    try {
        const response = await fetch('../auth/get_admin_session.php');
        const data     = await response.json();
        if (!data.success) { window.location.href = '../auth/Login.html'; return; }
        fillAdminProfile(data.user);
    } catch (err) {
        console.error('Admin profile fetch error:', err);
    }
}

/* =============================================================================
   FILL FIELDS
   ============================================================================= */
function fillAdminProfile(u) {
    const fullName   = `${u.first_name || ''} ${u.last_name || ''}`.trim();
    const profilePic = u.profile_pic
        ? '/UGAT/' + u.profile_pic
        : `https://ui-avatars.com/api/?name=${encodeURIComponent(fullName)}&background=2d6a2d&color=fff&size=128`;

    // Sidebar
    const avatarImg = document.getElementById('profile-avatar-img');
    if (avatarImg) { avatarImg.src = profilePic; avatarImg.alt = fullName; }

    const displayName = document.getElementById('display-name');
    if (displayName) displayName.textContent = fullName;

    const displayEmail = document.getElementById('display-email');
    if (displayEmail) displayEmail.textContent = u.email || '';

    // Member since
    const sinceEl = document.querySelector('.profile-since');
    if (sinceEl && u.created_at) {
        const d = new Date(u.created_at);
        sinceEl.textContent = 'Member since ' + d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    }

    // Nav
    document.querySelectorAll('.user-avatar, .user-dropdown-avatar').forEach(img => {
        img.src = profilePic; img.alt = fullName;
    });
    const navName = document.querySelector('.user-dropdown-name');
    if (navName) navName.innerHTML = `${u.first_name || 'Admin'} <span class="user-caret">▾</span>`;
    const dropName = document.querySelector('.user-dropdown-fullname');
    if (dropName) dropName.textContent = fullName;

    // Form fields
    setVal('p-firstname', u.first_name || '');
    setVal('p-lastname',  u.last_name  || '');
    setVal('p-email',     u.email      || '');
    setVal('p-phone',     u.phone      || '');
}

/* =============================================================================
   SAVE PERSONAL INFO
   ============================================================================= */
async function savePersonalInfo() {
    const first = getVal('p-firstname');
    const last  = getVal('p-lastname');
    const email = getVal('p-email');

    if (!first || !last || !email) {
        showToast('Please fill in all required fields.');
        return;
    }

    const formData = new FormData();
    formData.append('first_name', first);
    formData.append('last_name',  last);
    formData.append('email',      email);
    formData.append('phone',      getVal('p-phone'));

    try {
        const response = await fetch('../auth/update_admin_profile.php', { method: 'POST', body: formData });
        const data     = await response.json();
        if (data.success) {
            showToast('✅ Personal information saved!');
            await loadAdminProfile(); // refresh displayed data
        } else {
            showToast(data.message || 'Save failed.');
        }
    } catch (err) {
        showToast('Could not connect to server.');
    }
}

/* =============================================================================
   AVATAR UPLOAD
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
        if (errEl) { errEl.textContent = 'Fill in all password fields.'; errEl.style.display = 'block'; } return;
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
            showToast('✅ Password updated successfully!');
            ['p-current-pw','p-new-pw','p-confirm-pw'].forEach(id => {
                const el = document.getElementById(id); if (el) el.value = '';
            });
            if (document.getElementById('pw-strength-fill'))
                document.getElementById('pw-strength-fill').style.width = '0';
            if (document.getElementById('pw-strength-label'))
                document.getElementById('pw-strength-label').textContent = '';
        } else {
            if (errEl) { errEl.textContent = data.message || 'Update failed.'; errEl.style.display = 'block'; }
        }
    } catch (err) {
        showToast('Could not connect to server.');
    }
}

/* =============================================================================
   PASSWORD STRENGTH
   ============================================================================= */
function checkPwStrength(val) {
    const fill  = document.getElementById('pw-strength-fill');
    const label = document.getElementById('pw-strength-label');
    if (!fill) return;
    let score = 0;
    if (val.length >= 8)          score++;
    if (/[A-Z]/.test(val))        score++;
    if (/[0-9]/.test(val))        score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const levels = [
        { pct:'25%', color:'#e74c3c', text:'Weak' },
        { pct:'50%', color:'#f4a523', text:'Fair' },
        { pct:'75%', color:'#8dc63f', text:'Good' },
        { pct:'100%',color:'#4B8423', text:'Strong' },
    ];
    const level = levels[score - 1] || { pct:'0%', color:'transparent', text:'' };
    fill.style.width      = level.pct;
    fill.style.background = level.color;
    if (label) label.textContent = level.text;
}

/* =============================================================================
   PASSWORD TOGGLE
   ============================================================================= */
function togglePw(id, btn) {
    const input = document.getElementById(id);
    if (!input) return;
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    if (btn) btn.textContent = isHidden ? '🙈' : '👁';
}

/* =============================================================================
   USER MENU
   ============================================================================= */
function toggleUserMenu(e) {
    e.stopPropagation();
    const d = document.getElementById('user-dropdown');
    const c = document.querySelector('.user-caret');
    if (!d) return;
    const open = d.classList.contains('open');
    d.classList.toggle('open', !open);
    if (c) c.classList.toggle('open', !open);
}

document.addEventListener('click', function () {
    const d = document.getElementById('user-dropdown');
    const c = document.querySelector('.user-caret');
    if (d) d.classList.remove('open');
    if (c) c.classList.remove('open');
});

/* =============================================================================
   HELPERS
   ============================================================================= */
function setVal(id, value) { const el = document.getElementById(id); if (el) el.value = value; }
function getVal(id)        { return document.getElementById(id)?.value.trim() || ''; }

/* =============================================================================
   TOAST
   ============================================================================= */
function showToast(msg) {
    const t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}