/**
 * TraineeDashboard.js — UGAT TrainTrack
 */

document.addEventListener('DOMContentLoaded', loadDashboard);

async function loadDashboard() {
    try {
        const res  = await fetch('get_trainee_dashboard.php?t=' + Date.now());
        const data = await res.json();
        if (!data.success) { window.location.href = '../auth/Login.html'; return; }
        renderWorkshops(data.workshops     || []);
        renderCertificates(data.certificates || []);
        renderNotifications(data.notifications || []);
    } catch (err) {
        console.error('Dashboard load error:', err);
    }
}

/* =============================================================================
   WORKSHOPS
   ============================================================================= */
function renderWorkshops(workshops) {
    const container = document.querySelector('.workshop-cards');
    if (!container) return;

    if (!workshops.length) {
        container.innerHTML = `
            <div style="grid-template-columns:1fr 1fr;display:grid;gap:1.5rem">
                <div style="background:#fff;border-radius:12px;border:1.5px solid #e5e7eb;
                    padding:1.25rem;text-align:center;color:#aaa">
                    <p style="font-size:0.85rem">No approved enrollments yet.</p>
                </div>
                <div style="background:#fffbeb;border-radius:12px;border:1.5px solid #fcd34d;
                    padding:1.25rem;text-align:center;color:#b45309">
                    <p style="font-size:0.85rem">No pending enrollments.</p>
                </div>
            </div>`;
        return;
    }

    const enrolled = workshops.filter(w => w.enrollment_status === 'enrolled' || w.enrollment_status === 'completed');
    const pending  = workshops.filter(w => w.enrollment_status === 'pending');
    const rejected = workshops.filter(w => w.enrollment_status === 'rejected');

    const imgMap = {
        'Urban Farming':   'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&q=80',
        'Urban Gardening': 'https://images.unsplash.com/photo-1523348837708-15d4a09cfac2?w=400&q=80',
        'Mushroom Farming':'https://images.unsplash.com/photo-1504545102780-26774c1bb073?w=400&q=80',
        'Crop Growing':    'https://images.unsplash.com/photo-1464226184884-fa280b87c399?w=400&q=80',
        'default':         'https://images.unsplash.com/photo-1464226184884-fa280b87c399?w=400&q=80',
    };

    function buildCard(w) {
        const wsStatus = (w.workshop_status || 'upcoming').toLowerCase();
        const pct      = w.progress_pct || 0;
        const imgSrc   = imgMap[w.category] || imgMap.default;
        const dateStr  = w.first_session_date
            ? new Date(w.first_session_date).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' })
            : 'TBD';
        let badgeHtml;
        if (wsStatus === 'ongoing')        badgeHtml = `<span class="badge badge-ongoing">Ongoing</span>`;
        else if (wsStatus === 'completed') badgeHtml = `<span class="badge badge-completed">Completed</span>`;
        else                               badgeHtml = `<span class="badge badge-upcoming">Upcoming</span>`;

        return `
        <div class="workshop-card" onclick="window.location.href='TraineeWorkshops.html'"
             style="cursor:pointer;background:#fff;border-radius:10px;
                    border:1px solid #e5e7eb;overflow:hidden;transition:box-shadow 0.2s">
            <img src="${imgSrc}" alt="${escHtml(w.title)}" class="wcard-img"
                 onerror="this.src='${imgMap.default}'">
            <div class="wcard-body">
                <p class="wcard-cat">${escHtml(w.category || '')}</p>
                <h4 class="wcard-title">${escHtml(w.title)}</h4>
                <p class="wcard-date">📅 ${dateStr}</p>
                <div class="wcard-progress-row">
                    <div class="progress-bar"><div class="progress-fill" style="width:${pct}%"></div></div>
                    ${badgeHtml}
                </div>
                <p class="wcard-session">Session ${w.attended_sessions} of ${w.total_sessions}</p>
            </div>
        </div>`;
    }

    function buildPendingCard(w) {
        const imgSrc  = imgMap[w.category] || imgMap.default;
        const dateStr = w.first_session_date
            ? new Date(w.first_session_date).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' })
            : 'TBD';
        return `
        <div class="workshop-card"
             style="cursor:default;background:#fff;border-radius:10px;
                    border:1.5px dashed #fcd34d;overflow:hidden;position:relative;opacity:0.9">
            <div style="position:absolute;top:8px;left:8px;z-index:2;
                background:#78350f;color:#fef3c7;font-size:0.68rem;font-weight:700;
                padding:3px 10px;border-radius:10px;letter-spacing:0.05em;border:1px solid #92400e">
                AWAITING APPROVAL
            </div>
            <img src="${imgSrc}" alt="${escHtml(w.title)}" class="wcard-img">
            <div class="wcard-body">
                <p class="wcard-cat">${escHtml(w.category || '')}</p>
                <h4 class="wcard-title">${escHtml(w.title)}</h4>
                <p class="wcard-date">📅 ${dateStr}</p>
                <div class="wcard-progress-row">
                    <div class="progress-bar"><div class="progress-fill" style="width:0%"></div></div>
                    <span style="display:inline-flex;align-items:center;gap:4px;background:#92400e;
                        color:#fef3c7;border:1px solid #78350f;padding:3px 10px;
                        border-radius:20px;font-size:0.72rem;font-weight:600">
                        <span style="width:5px;height:5px;border-radius:50%;background:#fcd34d;display:inline-block"></span>
                        Pending approval
                    </span>
                </div>
                <p class="wcard-session" style="color:#b45309">Awaiting admin review</p>
            </div>
        </div>`;
    }

    function buildRejectedCard(w) {
        const imgSrc  = imgMap[w.category] || imgMap.default;
        const dateStr = w.first_session_date
            ? new Date(w.first_session_date).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' })
            : 'TBD';
        return `
        <div class="workshop-card"
             style="cursor:default;background:#fff;border-radius:10px;
                    border:1.5px dashed #fca5a5;overflow:hidden;opacity:0.6;filter:grayscale(30%)">
            <img src="${imgSrc}" alt="${escHtml(w.title)}" class="wcard-img">
            <div class="wcard-body">
                <p class="wcard-cat">${escHtml(w.category || '')}</p>
                <h4 class="wcard-title">${escHtml(w.title)}</h4>
                <p class="wcard-date">📅 ${dateStr}</p>
                <div class="wcard-progress-row">
                    <div class="progress-bar"><div class="progress-fill" style="width:0%;background:#fca5a5"></div></div>
                    <span style="display:inline-flex;align-items:center;gap:4px;background:#991b1b;
                        color:#fee2e2;border:1px solid #7f1d1d;padding:3px 10px;
                        border-radius:20px;font-size:0.72rem;font-weight:600">
                        <span style="width:5px;height:5px;border-radius:50%;background:#fca5a5;display:inline-block"></span>
                        Rejected
                    </span>
                </div>
                <p class="wcard-session" style="color:#b91c1c">Enrollment not approved</p>
            </div>
        </div>`;
    }

    // ── Layout: Enrolled + Pending side by side like bottom panels ──
    let html = `<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem">`;

    html += `
    <div style="background:#fff;border-radius:12px;border:1.5px solid #e5e7eb;padding:1.25rem">
        <h4 style="font-size:0.85rem;font-weight:700;color:var(--color-primary);margin:0 0 0.75rem">
             Enrolled Workshops
        </h4>
        ${enrolled.length
            ? `<div style="display:flex;flex-direction:column;gap:0.75rem">
                ${enrolled.map(w => buildCard(w)).join('')}
               </div>`
            : `<p style="color:#9ca3af;font-size:0.85rem;padding:0.5rem 0">
                No approved enrollments yet.
               </p>`
        }
    </div>`;

    html += `
    <div style="background:#fffbeb;border-radius:12px;border:1.5px solid #fcd34d;padding:1.25rem">
        <h4 style="font-size:0.85rem;font-weight:700;color:#92400e;margin:0 0 0.2rem">
             Pending Approval
        </h4>
        <p style="font-size:0.75rem;color:#b45309;margin:0 0 0.75rem">
            Waiting for admin to review your enrollment.
        </p>
        ${pending.length
            ? `<div style="display:flex;flex-direction:column;gap:0.75rem">
                ${pending.map(w => buildPendingCard(w)).join('')}
               </div>`
            : `<p style="color:#9ca3af;font-size:0.85rem;padding:0.5rem 0">
                No pending enrollments.
               </p>`
        }
    </div>`;

    html += `</div>`;

    if (rejected.length) {
        html += `
        <div style="background:#fff5f5;border-radius:12px;border:1.5px solid #fca5a5;
                    padding:1.25rem;margin-bottom:1.5rem">
            <h4 style="font-size:0.85rem;font-weight:700;color:#991b1b;margin:0 0 0.2rem">
                ✕ Rejected Enrollments
            </h4>
            <p style="font-size:0.75rem;color:#b91c1c;margin:0 0 0.75rem">
                Contact UGAT staff for more information.
            </p>
            <div style="display:flex;flex-wrap:wrap;gap:1rem">
                ${rejected.map(w => buildRejectedCard(w)).join('')}
            </div>
        </div>`;
    }

    container.innerHTML = html;
}
/* =============================================================================
   CERTIFICATES
   ============================================================================= */
function renderCertificates(certs) {
    const list = document.querySelector('.cert-list');
    if (!list) return;

    if (!certs.length) {
        list.innerHTML = `<p style="padding:1rem;color:#aaa;font-size:.85rem">
            No certificates yet. Complete a workshop to earn one!</p>`;
        return;
    }

    const iconMap  = { issued:'⭐', eligible:'○', pending:'○', locked:'🔒' };
    const badgeMap = { issued:'badge-issued', eligible:'badge-pending', pending:'badge-pending', locked:'badge-locked' };

    list.innerHTML = certs.map(c => {
        const status = c.status || 'locked';
        const sub    = status === 'issued' && c.issued_at
            ? 'Issued ' + new Date(c.issued_at).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' })
            : 'Complete sessions to unlock';
        return `
        <div class="cert-item">
            <div class="cert-icon ${status}">${iconMap[status] || '🔒'}</div>
            <div class="cert-info">
                <p class="cert-title">${escHtml(c.workshop_title)}</p>
                <p class="cert-sub">${sub}</p>
            </div>
            <span class="badge ${badgeMap[status] || 'badge-locked'}">${capitalize(status)}</span>
        </div>`;
    }).join('');
}

/* =============================================================================
   NOTIFICATIONS — color-coded by status
   ============================================================================= */
function renderNotifications(notifications) {
    const list = document.querySelector('.notif-list');
    if (!list) return;

    if (!notifications.length) {
        list.innerHTML = `<p style="padding:1rem;color:#aaa;font-size:.85rem">No notifications yet.</p>`;
        return;
    }

    // Dot color → CSS color
    const dotColors = {
        green:  '#22c55e',
        yellow: '#f59e0b',
        red:    '#ef4444',
        blue:   '#3b82f6',
    };

    list.innerHTML = notifications.map(n => {
        const dateStr   = n.date
            ? new Date(n.date).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' })
            : '';
const typeLabel = n.type === 'certificate' ? 'Certification'
    : n.status === 'pending'  ? 'Pending Enrollment'
    : n.status === 'enrolled' ? 'Enrollment Approved'
    : n.status === 'rejected' ? 'Enrollment Rejected'
    : 'Enrollment';        const dotColor  = dotColors[n.dot] || dotColors.green;

        // Extra label for pending/rejected
        let statusTag = '';
        if (n.status === 'pending') {
            statusTag = `<span style="background:#fef3c7;color:#92400e;font-size:0.7rem;
                font-weight:600;padding:1px 6px;border-radius:8px;margin-left:4px">Pending</span>`;
        } else if (n.status === 'rejected') {
            statusTag = `<span style="background:#fee2e2;color:#991b1b;font-size:0.7rem;
                font-weight:600;padding:1px 6px;border-radius:8px;margin-left:4px">Rejected</span>`;
        }

        return `
        <div class="notif-item">
            <div class="notif-dot" style="background:${dotColor};width:8px;height:8px;
                border-radius:50%;flex-shrink:0;margin-top:4px"></div>
            <div class="notif-info">
                <p class="notif-text">${escHtml(n.message)}${statusTag}</p>
                <p class="notif-sub">${dateStr} · ${typeLabel}</p>
            </div>
        </div>`;
    }).join('');
}

/* =============================================================================
   UTILITIES
   ============================================================================= */
function escHtml(str) {
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}