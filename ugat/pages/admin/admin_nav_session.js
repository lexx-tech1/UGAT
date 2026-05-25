/**
 * admin_nav_session.js — UGAT TrainTrack
 * Loads the logged-in admin's real data into the nav on every admin page.
 * Include this on ALL admin HTML pages before the closing </body> tag.
 */

document.addEventListener('DOMContentLoaded', async function () {
    try {
const response = await fetch('../auth/get_session.php?role=admin&t=' + Date.now());
        const data     = await response.json();

        if (!data.success) {
            window.location.href = '../auth/Login.html';
            return;
        }

        const u          = data.user;
        const firstName  = u.first_name || 'Admin';
        const lastName   = u.last_name  || '';
        const fullName   = `${firstName} ${lastName}`.trim();
        const profilePic = u.profile_pic
            ? '/UGAT/' + u.profile_pic
            : `https://ui-avatars.com/api/?name=${encodeURIComponent(fullName)}&background=2d6a2d&color=fff&size=128`;

        // Nav small avatar
        document.querySelectorAll('.user-avatar').forEach(img => {
            img.src = profilePic;
            img.alt = fullName;
        });

        // Dropdown avatar
        document.querySelectorAll('.user-dropdown-avatar').forEach(img => {
            img.src = profilePic;
            img.alt = fullName;
        });

        // Nav name (first name only)
        const nameTrigger = document.querySelector('.user-dropdown-name');
        if (nameTrigger) {
            nameTrigger.innerHTML = `${firstName} <span class="user-caret">▾</span>`;
        }

        // Dropdown full name
        const dropdownFullname = document.querySelector('.user-dropdown-fullname');
        if (dropdownFullname) dropdownFullname.textContent = fullName;

        // Dropdown email
        const dropdownEmail = document.querySelector('.user-dropdown-email');
        if (dropdownEmail) dropdownEmail.textContent = u.email || '';

    } catch (err) {
        console.error('Admin nav session error:', err);
    }
});


/* =============================================================================
   USER MENU TOGGLE — shared across all admin pages
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