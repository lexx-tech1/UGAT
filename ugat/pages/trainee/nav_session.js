/**
 * nav_session.js — UGAT TrainTrack
 * Loads the logged-in user's real data into the nav on every trainee page.
 * Include this on ALL trainee HTML pages before the closing </body> tag.
 */

document.addEventListener('DOMContentLoaded', async function () {
    try {
const response = await fetch('../auth/get_session.php?role=trainee&t=' + Date.now());
        const data     = await response.json();

        if (!data.success) {
            window.location.href = '../auth/Login.html';
            return;
        }

        const u          = data.user;
        const firstName  = u.first_name || '';
        const lastName   = u.last_name  || '';
        const fullName   = `${firstName} ${lastName}`.trim();
        const profilePic = u.profile_pic
            ? '/UGAT/' + u.profile_pic
            : `https://ui-avatars.com/api/?name=${encodeURIComponent(fullName)}&background=4B8423&color=fff&size=128`;

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

        // Welcome banner (if exists)
        const welcomeTitle = document.querySelector('.welcome-title');
        if (welcomeTitle) {
            welcomeTitle.textContent = `Ready for your next workshop, ${firstName}?`;
        }

        // ── Fix logout link on every page that includes this script ──
        document.querySelectorAll('.user-dropdown-logout').forEach(link => {
            link.href = '../auth/logout.php';
        });

    } catch (err) {
        console.error('Nav session error:', err);
    }
});


/* =============================================================================
   USER MENU TOGGLE — shared across all pages
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