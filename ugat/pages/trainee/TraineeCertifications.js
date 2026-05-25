/* TraineeCertifications.js */

var MY_CERTS    = [];
var TRAINEE_NAME = '';
var ACTIVE_FILTER = 'all';

document.addEventListener('DOMContentLoaded', function() {
    loadSession();
    loadCertifications();
});

/* ── Session / nav ─────────────────────────────────────────── */
async function loadSession() {
    try {
        var r = await fetch('../auth/get_session.php');
        var d = await r.json();
        if (!d.success) { window.location.href = '../auth/Login.html'; return; }
        var u = d.user;
        var fullName = ((u.first_name || '') + ' ' + (u.last_name || '')).trim();
        var pic = u.profile_pic
            ? '/UGAT/' + u.profile_pic
            : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(fullName) + '&background=4B8423&color=fff&size=64';

        document.querySelectorAll('#nav-avatar, #dropdown-avatar').forEach(function(el) { el.src = pic; });
        var navName = document.getElementById('nav-name');
        if (navName) navName.innerHTML = (u.first_name || '') + ' <span class="user-caret">▾</span>';
        var dropName = document.getElementById('dropdown-name');
        if (dropName) dropName.textContent = fullName;
    } catch(e) { console.error('Session error:', e); }
}

/* ── Load certs ────────────────────────────────────────────── */
async function loadCertifications() {
    try {
        var r = await fetch('get_trainee_certifications.php');
        var d = await r.json();
        if (!d.success) { renderEmpty('Could not load certifications.'); return; }
        MY_CERTS     = d.certificates || [];
        TRAINEE_NAME = d.trainee_name || 'Trainee';
        renderCerts();
    } catch(e) {
        renderEmpty('Could not connect to server.');
    }
}

/* ── Filter ────────────────────────────────────────────────── */
function setFilter(filter, btn) {
    ACTIVE_FILTER = filter;
    document.querySelectorAll('[id^="filter-"]').forEach(function(b) { b.classList.remove('active'); });
    if (btn) btn.classList.add('active');
    renderCerts();
}

/* ── Render cert cards ─────────────────────────────────────── */
function renderCerts() {
    var grid = document.getElementById('certs-grid');
    if (!grid) return;

    var data = ACTIVE_FILTER === 'all'
        ? MY_CERTS
        : MY_CERTS.filter(function(c) { return c.status === ACTIVE_FILTER; });

    if (!data.length) {
        renderEmpty(ACTIVE_FILTER === 'all'
            ? 'No certifications yet. Complete a workshop to earn one!'
            : 'No ' + ACTIVE_FILTER + ' certificates.');
        return;
    }

    var iconMap  = { issued:'⭐', eligible:'○', pending:'○', locked:'🔒' };
    var badgeMap = { issued:'badge-issued', eligible:'badge-pending', pending:'badge-pending', locked:'badge-locked' };
    var labelMap = { issued:'Issued', eligible:'Eligible', pending:'Pending', locked:'Locked' };

    grid.innerHTML = data.map(function(c) {
        var status   = c.status || 'locked';
        var icon     = iconMap[status]  || '🔒';
        var badge    = badgeMap[status] || 'badge-locked';
        var label    = labelMap[status] || status;
        var isIssued = status === 'issued';
        var isElig   = status === 'eligible';

        var dateStr = '';
        if (isIssued && c.issued_at) {
            dateStr = 'Issued ' + new Date(c.issued_at).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' });
        } else if (isElig) {
            dateStr = 'Eligible — awaiting issuance';
        } else {
            dateStr = 'Complete sessions to unlock';
        }

        var certNoHtml = isIssued && c.cert_no
            ? '<span class="cert-no">' + escHtml(c.cert_no) + '</span>'
            : '';

        var actionBtn = isIssued
            ? '<button class="btn-primary" style="font-size:0.78rem;padding:0.4rem 1rem" onclick="viewCert(' + c.id + ')">View Certificate</button>'
            : '<span style="font-size:0.78rem;color:var(--color-text-light)">' + (isElig ? 'Awaiting issuance' : 'Not yet eligible') + '</span>';

        return '<div class="cert-card ' + status + '">' +
            '<div class="cert-card-top">' +
                '<div class="cert-icon-lg ' + status + '">' + icon + '</div>' +
                '<div style="flex:1">' +
                    '<div class="cert-title">' + escHtml(c.workshop_title) + '</div>' +
                    '<div class="cert-meta">' + escHtml(dateStr) + '</div>' +
                '</div>' +
                '<span class="badge ' + badge + '">' + label + '</span>' +
            '</div>' +
            '<div class="cert-card-footer">' +
                certNoHtml +
                actionBtn +
            '</div>' +
        '</div>';
    }).join('');
}

function renderEmpty(msg) {
    var grid = document.getElementById('certs-grid');
    if (grid) grid.innerHTML =
        '<div class="empty-state" style="grid-column:1/-1">' +
            '<div class="empty-state-icon">🏆</div>' +
            '<p>' + escHtml(msg) + '</p>' +
        '</div>';
}

/* ── Certificate viewer (same design as TraineeWorkshops) ──── */
function viewCert(id) {
    var c = MY_CERTS.find(function(x) { return x.id === id; });
    if (!c) return;

    var name   = TRAINEE_NAME;
    var certNo = c.cert_no || '—';
    var ws     = c.workshop_title || '—';
    var dt     = c.issued_at ? new Date(c.issued_at) : new Date();
    var day       = dt.getDate();
    var monthYear = dt.toLocaleDateString('en-US', { month:'long', year:'numeric' });
    var issuedFmt = dt.toLocaleDateString('en-US', { month:'long', day:'numeric', year:'numeric' }).toUpperCase();

    var leafSVG = '<svg width="52" height="200" viewBox="0 0 52 200" xmlns="http://www.w3.org/2000/svg">' +
        '<line x1="26" y1="5" x2="26" y2="195" stroke="#7ab050" stroke-width="1.3" opacity="0.55"/>' +
        '<ellipse cx="26" cy="35"  rx="12" ry="23" fill="#a8d070" opacity="0.5"  transform="rotate(-20 26 35)"/>' +
        '<ellipse cx="26" cy="75"  rx="14" ry="25" fill="#90c060" opacity="0.45" transform="rotate(14 26 75)"/>' +
        '<ellipse cx="26" cy="115" rx="12" ry="22" fill="#a8d070" opacity="0.42" transform="rotate(-14 26 115)"/>' +
        '<ellipse cx="26" cy="152" rx="13" ry="21" fill="#90c060" opacity="0.34" transform="rotate(8 26 152)"/>' +
        '</svg>';

    var leafSVGR = '<svg width="52" height="200" viewBox="0 0 52 200" xmlns="http://www.w3.org/2000/svg">' +
        '<line x1="26" y1="5" x2="26" y2="195" stroke="#7ab050" stroke-width="1.3" opacity="0.55"/>' +
        '<ellipse cx="26" cy="35"  rx="12" ry="23" fill="#a8d070" opacity="0.5"  transform="rotate(20 26 35)"/>' +
        '<ellipse cx="26" cy="75"  rx="14" ry="25" fill="#90c060" opacity="0.45" transform="rotate(-14 26 75)"/>' +
        '<ellipse cx="26" cy="115" rx="12" ry="22" fill="#a8d070" opacity="0.42" transform="rotate(14 26 115)"/>' +
        '<ellipse cx="26" cy="152" rx="13" ry="21" fill="#90c060" opacity="0.34" transform="rotate(-8 26 152)"/>' +
        '</svg>';

    var sealSVG = '<svg width="86" height="86" viewBox="0 0 86 86" xmlns="http://www.w3.org/2000/svg">' +
        '<circle cx="43" cy="43" r="41" fill="#1e4d0f"/>' +
        '<circle cx="43" cy="43" r="36" fill="none" stroke="#8dc63f" stroke-width="1" stroke-dasharray="3,2"/>' +
        '<defs><path id="ct" d="M13,43 A30,30 0 0,1 73,43"/><path id="cb" d="M18,57 A28,28 0 0,0 68,57"/></defs>' +
        '<text fill="#8dc63f" font-size="7.5" letter-spacing="1.8" font-family="Arial,sans-serif" font-weight="bold"><textPath href="#ct" startOffset="4%">UGAT TRAINTRACK</textPath></text>' +
        '<text fill="#8dc63f" font-size="7" letter-spacing="1.5" font-family="Arial,sans-serif" font-weight="bold"><textPath href="#cb" startOffset="8%">OFFICIAL SEAL</textPath></text>' +
        '<path d="M43 24 C52 29 56 39 43 53 C30 39 34 29 43 24Z" fill="#8dc63f"/>' +
        '<line x1="43" y1="53" x2="43" y2="62" stroke="#8dc63f" stroke-width="1.8"/>' +
        '</svg>';

    var logoSVG = '<svg width="34" height="34" viewBox="0 0 34 34" xmlns="http://www.w3.org/2000/svg">' +
        '<path d="M17 3 C24 8 28 17 17 28 C6 17 10 8 17 3Z" fill="#8dc63f"/>' +
        '<line x1="17" y1="28" x2="17" y2="32" stroke="#8dc63f" stroke-width="1.8"/>' +
        '</svg>';

    var certHTML =
        '<div style="background:#f4f9ee;border:3px solid #1e4d0f;outline:2px solid #4B8423;outline-offset:-9px;font-family:Georgia,\'Times New Roman\',serif;position:relative;overflow:hidden;">' +
            '<div style="background:#1e4d0f;color:#fff;padding:10px 16px;display:flex;justify-content:space-between;align-items:center;border-bottom:3px solid #c9a227;">' +
                '<div style="display:flex;align-items:center;gap:10px;">' +
                    logoSVG +
                    '<div>' +
                        '<div style="font-size:15px;font-weight:700;letter-spacing:0.02em;line-height:1.25;">UGAT Integrated Farm</div>' +
                        '<div style="font-size:8px;letter-spacing:0.09em;opacity:0.82;margin-top:2px;font-family:Arial,sans-serif;">AGRICULTURAL TRAINING &amp; DEVELOPMENT &nbsp;&middot;&nbsp; SAN ISIDRO, DAET, CAMARINES NORTE</div>' +
                    '</div>' +
                '</div>' +
                '<div style="text-align:right;font-size:9px;letter-spacing:0.06em;line-height:2;font-family:Arial,sans-serif;font-weight:600;">' +
                    'ISSUED: ' + issuedFmt + '<br>CERT. NO.: ' + certNo +
                '</div>' +
            '</div>' +
            '<div style="display:flex;align-items:stretch;padding:18px 0;">' +
                '<div style="width:60px;display:flex;align-items:center;justify-content:center;flex-shrink:0;opacity:0.72;">' + leafSVG + '</div>' +
                '<div style="flex:1;text-align:center;padding:0 6px;">' +
                    '<p style="font-style:italic;color:#777;font-size:10.5px;margin-bottom:4px;">This is to certify that</p>' +
                    '<div style="margin-bottom:22px;"><div style="font-size:22px;font-weight:700;color:#1e4d0f;display:inline-block;padding-bottom:4px;border-bottom:2.5px solid #c9a227;letter-spacing:0.01em;">Certificate of Completion</div></div>' +
                    '<div style="font-size:30px;font-weight:700;font-style:italic;color:#1e4d0f;line-height:1.15;">' + escHtml(name) + '</div>' +
                    '<div style="width:55%;height:1.5px;background:#4B8423;margin:10px auto 14px;"></div>' +
                    '<p style="font-size:10px;color:#444;margin-bottom:6px;line-height:1.55;">has successfully completed all requirements and demonstrated satisfactory competence in</p>' +
                    '<div style="font-size:17px;font-weight:700;color:#1e4d0f;margin-bottom:3px;">' + escHtml(ws) + '</div>' +
                    '<div style="font-size:7.5px;letter-spacing:0.22em;color:#888;margin-bottom:8px;font-family:Arial,sans-serif;">UGAT TRAINTRACK CERTIFIED PROGRAM</div>' +
                    '<div style="border-top:1.5px dashed #aaa;width:75%;margin:8px auto;"></div>' +
                    '<p style="font-size:9.5px;font-style:italic;color:#555;margin-bottom:5px;line-height:1.55;">Awarded in recognition of dedicated participation, practical learning, and commitment to sustainable agriculture.</p>' +
                    '<p style="font-size:10px;color:#333;margin-bottom:12px;"><em>Given this <strong>' + day + '</strong> day of <strong>' + monthYear + '</strong> at <strong>San Isidro, Daet, Camarines Norte.</strong></em></p>' +
                    '<div style="display:flex;justify-content:center;margin:4px 0 14px;">' + sealSVG + '</div>' +
                    '<div style="display:flex;justify-content:space-between;padding:0 24px;">' +
                        '<div style="text-align:center;width:130px;"><div style="height:1px;background:#1e4d0f;margin-bottom:5px;"></div><div style="font-size:7.5px;letter-spacing:0.1em;color:#222;font-family:Arial,sans-serif;text-transform:uppercase;">Program Coordinator</div><div style="font-size:8px;color:#666;font-style:italic;">UGAT Integrated Farm</div></div>' +
                        '<div style="text-align:center;width:130px;"><div style="height:1px;background:#1e4d0f;margin-bottom:5px;"></div><div style="font-size:7.5px;letter-spacing:0.1em;color:#222;font-family:Arial,sans-serif;text-transform:uppercase;">Farm Director</div><div style="font-size:8px;color:#666;font-style:italic;">UGAT Integrated Farm</div></div>' +
                    '</div>' +
                '</div>' +
                '<div style="width:60px;display:flex;align-items:center;justify-content:center;flex-shrink:0;opacity:0.72;">' + leafSVGR + '</div>' +
            '</div>' +
            '<div style="position:absolute;bottom:34px;left:0;right:0;display:flex;justify-content:space-between;padding:0 10px;pointer-events:none;">' +
                '<div style="width:8px;height:8px;border-radius:50%;background:#333;"></div>' +
                '<div style="width:8px;height:8px;border-radius:50%;background:#333;"></div>' +
            '</div>' +
            '<div style="background:#1e4d0f;color:#fff;text-align:center;padding:7px 8px;font-family:Arial,sans-serif;">' +
                '<div style="font-size:8.5px;letter-spacing:0.14em;font-weight:600;">UGAT TRAINTRACK SYSTEM &nbsp;&middot;&nbsp; AGRICULTURAL TRAINING MANAGEMENT</div>' +
                '<div style="font-size:7px;opacity:0.72;margin-top:2px;">This certificate is an official document of UGAT Integrated Farm. For verification, contact the program office.</div>' +
            '</div>' +
        '</div>';

    document.getElementById('cert-view-body').innerHTML =
        certHTML +
        '<div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--color-border)">' +
            '<button class="btn-outline" onclick="closeModal(\'cert-view-modal\')">Close</button>' +
            '<button class="btn-outline" onclick="_downloadCert(' + id + ',\'png\')">🖼 Save as Image</button>' +
            '<button class="btn-primary" onclick="_downloadCert(' + id + ',\'pdf\')">📄 Download PDF</button>' +
        '</div>';

    openModal('cert-view-modal');
}

async function _downloadCert(id, type) {
    var certEl = document.querySelector('#cert-view-body > div:first-child');
    if (!certEl) return;
    var c = MY_CERTS.find(function(x) { return x.id === id; });
    var filename = 'UGAT_Certificate_' + (TRAINEE_NAME || 'Trainee').replace(/\s+/g, '_');
    showToast('⏳ Generating ' + (type === 'pdf' ? 'PDF' : 'image') + '…');
    try {
        var canvas = await html2canvas(certEl, { scale:2, useCORS:true, logging:false, backgroundColor:'#f4f9ee' });
        if (type === 'png') {
            canvas.toBlob(function(blob) {
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a'); a.href = url; a.download = filename + '.png';
                document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
                showToast('✅ Image saved!');
            }, 'image/png');
        } else {
            var imgData = canvas.toDataURL('image/jpeg', 0.95);
            var jsPDF = window.jspdf.jsPDF;
            var w = certEl.offsetWidth, h = certEl.offsetHeight;
            var pdf = new jsPDF({ orientation: w > h ? 'l' : 'p', unit:'px', format:[w,h] });
            pdf.addImage(imgData, 'JPEG', 0, 0, w, h);
            pdf.save(filename + '.pdf');
            showToast('✅ PDF downloaded!');
        }
    } catch(e) { showToast('❌ Could not generate file.'); }
}

/* ── Modal helpers ─────────────────────────────────────────── */
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
function closeModalOutside(e, id) { if (e.target.id === id) closeModal(id); }

/* ── Utilities ─────────────────────────────────────────────── */
function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function showToast(msg) {
    var t = document.getElementById('toast'); if (!t) return;
    t.textContent = msg; t.classList.add('show');
    setTimeout(function() { t.classList.remove('show'); }, 3000);
}
