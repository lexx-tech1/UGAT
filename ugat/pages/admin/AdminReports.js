/* =============================================================================
   AdminReports.js — UGAT TrainTrack
   All data fetched from get_reports.php (real database).
   Zero hardcoded values.
   ============================================================================= */

let activeRptTab = 'overview';

/* =============================================================================
   HELPERS
   ============================================================================= */
function fmtPeso(n) {
    return '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function getPeriodParams() {
    const period = document.getElementById('rpt-period')?.value || 'all';
    const from   = document.getElementById('rpt-from')?.value  || '';
    const to     = document.getElementById('rpt-to')?.value    || '';
    return `period=${period}&from=${from}&to=${to}`;
}

function showToast(msg) {
    const t = document.getElementById('toast'); if (!t) return;
    t.textContent = msg; t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

function setEl(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
}

/* =============================================================================
   TAB SWITCHING
   ============================================================================= */
function switchRptTab(tab, btn) {
    document.querySelectorAll('.pm-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.pm-tab-content').forEach(el => el.style.display = 'none');
    document.getElementById('rpt-tab-' + tab).style.display = '';
    activeRptTab = tab;

    // Load data for the tab being switched to
    const loaders = {
        overview:       loadOverview,
        program:        loadProgram,
        attendance:     loadAttendance,
        certifications: loadCertifications,
        inventory:      loadInventory,
    };
    if (loaders[tab]) loaders[tab]();

}

function refreshAllReports() {
    const period      = document.getElementById('rpt-period').value;
    const customRange = document.getElementById('custom-range');
    if (customRange) customRange.style.display = period === 'custom' ? 'flex' : 'none';
    if (period === 'custom') return; // wait for Apply button
    reloadActiveTab();
}

function reloadActiveTab() {
    const loaders = {
        overview:       loadOverview,
        program:        loadProgram,
        attendance:     loadAttendance,
        certifications: loadCertifications,
        inventory:      loadInventory,
    };
    if (loaders[activeRptTab]) loaders[activeRptTab]();
}

/* =============================================================================
   TAB 1 — OVERVIEW
   ============================================================================= */
async function loadOverview() {
    try {
        const r = await fetch('get_reports.php?action=overview&' + getPeriodParams(), { credentials: 'same-origin' });
        const j = await r.json();
        if (!j.success) { showToast('Failed to load overview.'); return; }
        const d = j.data;

        // KPI cards — use IDs for reliable targeting
        const s = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
        // Set generated date
        const gd = document.getElementById('ov-generated-date');
        if (gd) gd.textContent = new Date().toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'});

        s('ov-kpi-trainees',   d.total_trainees);
        s('ov-kpi-workshops',  d.active_workshops);
        s('ov-kpi-att',        d.att_rate + '%');
        s('ov-kpi-certs',      d.certs_issued);
        s('ov-kpi-cert-sub',   d.cert_rate + '% certification rate');
        s('ov-kpi-alerts',     d.stock_out + d.stock_low);
        s('ov-kpi-alerts-sub', d.stock_out + ' out · ' + d.stock_low + ' low');
        // Summary blocks
        s('ov-sum-total',        d.total_trainees);
        s('ov-sum-active',       d.trainees_active);
        s('ov-sum-certified',    d.certs_issued);
        s('ov-sum-pending',      d.trainees_pending);
        s('ov-sum-inactive',     d.trainees_inactive);
        s('ov-sum-ws-total',     d.active_workshops);
        s('ov-sum-ws-upcoming',  d.upcoming_workshops);
        s('ov-sum-ws-ongoing',   d.ongoing_workshops);
        s('ov-sum-ws-completed', d.completed_workshops);
        s('ov-sum-ws-sessions',  d.total_sessions);
        s('ov-sum-att-total',    d.att_total);
        s('ov-sum-att-present',  d.att_present + ' (' + d.att_rate + '%)');
        s('ov-sum-att-late',     d.att_late);
        s('ov-sum-att-absent',   d.att_absent);
        s('ov-sum-att-atrisk',   d.at_risk);
        s('ov-sum-cert-issued',  d.certs_issued);
        s('ov-sum-cert-eligible',d.certs_eligible);
        s('ov-sum-cert-rate',    d.cert_rate + '%');
        s('ov-sum-sms-sent',     d.sms_sent);
        s('ov-sum-sms-pending',  d.sms_pending);

        // Chart
        renderOverviewChart(d.enrollment_chart);

    } catch(e) { console.error('loadOverview error:', e); showToast('Error loading overview.'); }
}

/* =============================================================================
   OVERVIEW CHART
   ============================================================================= */
function renderOverviewChart(chartData) {
    const canvas = document.getElementById('ov-enrollment-chart');
    if (!canvas || !chartData) return;
    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const W   = canvas.parentElement.clientWidth || 600;
    const H   = 120;
    canvas.width  = W * dpr; canvas.height = H * dpr;
    canvas.style.width = W + 'px'; canvas.style.height = H + 'px';
    ctx.scale(dpr, dpr);

    const pad  = { top: 15, right: 20, bottom: 30, left: 35 };
    const cW   = W - pad.left - pad.right;
    const cH   = H - pad.top  - pad.bottom;
    const maxV = Math.max(...chartData.enrolled, ...chartData.certified, 1) + 2;
    const cols = chartData.labels.length;
    const step = cols > 1 ? cW / (cols - 1) : cW;

    // Grid lines
    ctx.strokeStyle = '#eee'; ctx.lineWidth = 1;
    for (let i = 0; i <= 3; i++) {
        const y = pad.top + (cH / 3) * i;
        ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(pad.left + cW, y); ctx.stroke();
        ctx.fillStyle = '#aaa'; ctx.font = '10px Plus Jakarta Sans'; ctx.textAlign = 'right';
        ctx.fillText(Math.round(maxV - (maxV / 3) * i), pad.left - 5, y + 4);
    }

    // Labels
    ctx.fillStyle = '#aaa'; ctx.font = '10px Plus Jakarta Sans'; ctx.textAlign = 'center';
    chartData.labels.forEach((lbl, i) => ctx.fillText(lbl, pad.left + i * step, H - 8));

    function drawLine(data, color, fillColor) {
        if (!data.length) return;
        const pts = data.map((v, i) => ({
            x: pad.left + i * step,
            y: pad.top + cH - (v / maxV) * cH,
        }));
        // Fill area
        ctx.beginPath(); ctx.moveTo(pts[0].x, pad.top + cH);
        pts.forEach(p => ctx.lineTo(p.x, p.y));
        ctx.lineTo(pts[pts.length - 1].x, pad.top + cH); ctx.closePath();
        ctx.fillStyle = fillColor; ctx.fill();
        // Line
        ctx.beginPath(); ctx.moveTo(pts[0].x, pts[0].y);
        pts.forEach(p => ctx.lineTo(p.x, p.y));
        ctx.strokeStyle = color; ctx.lineWidth = 2; ctx.lineJoin = 'round'; ctx.stroke();
        // Dots
        pts.forEach(p => {
            ctx.beginPath(); ctx.arc(p.x, p.y, 3, 0, Math.PI * 2);
            ctx.fillStyle = color; ctx.fill();
            ctx.strokeStyle = '#fff'; ctx.lineWidth = 1.5; ctx.stroke();
        });
    }

    drawLine(chartData.certified, '#8dc63f', 'rgba(141,198,63,0.08)');
    drawLine(chartData.enrolled,  '#4B8423', 'rgba(75,132,35,0.10)');
}

/* =============================================================================
   TAB 2 — PROGRAM
   ============================================================================= */
async function loadProgram() {
    try {
        const r = await fetch('get_reports.php?action=program&' + getPeriodParams(), { credentials: 'same-origin' });
        const j = await r.json();
        if (!j.success) { showToast('Program: ' + (j.message || 'Unknown error')); console.error('Program error:', j.message); return; }

        // Enrollment table
        const bMap = { upcoming: 'badge-upcoming', ongoing: 'badge-ongoing', completed: 'badge-completed' };
        const lMap = { upcoming: 'Upcoming', ongoing: 'Ongoing', completed: 'Completed' };
        const enrollTbody = document.getElementById('rpt-enrollment-tbody');
        if (enrollTbody) {
            enrollTbody.innerHTML = j.workshops.map(w => {
                const enrolled  = parseInt(w.enrolled) || 0;
                const maxSlots  = parseInt(w.max_slots) || 1;
                const fillPct   = Math.round((enrolled / maxSlots) * 100);
                return `
                  <tr>
                    <td><strong>${w.title}</strong></td>
                    <td>${w.category || '—'}</td>
                    <td>${enrolled}</td>
                    <td>${maxSlots}</td>
                    <td>
                      <div style="display:flex;align-items:center;gap:0.5rem">
                        <div class="progress-bar" style="width:70px"><div class="progress-fill" style="width:${fillPct}%"></div></div>
                        <span class="light-txt">${fillPct}%</span>
                      </div>
                    </td>
                    <td><span class="badge ${bMap[w.status] || 'badge-upcoming'}">${lMap[w.status] || w.status}</span></td>
                  </tr>`;
            }).join('') || '<tr><td colspan="6" style="text-align:center;padding:1rem;color:#aaa">No workshops found.</td></tr>';
        }

        // Completion bars
        const barsEl = document.getElementById('rpt-completion-bars');
        if (barsEl) {
            barsEl.innerHTML = j.workshops.map(w => {
                const total     = parseInt(w.total_sessions)     || 0;
                const completed = parseInt(w.completed_sessions) || 0;
                const pct       = total > 0 ? Math.round((completed / total) * 100) : 0;
                return `
                  <div class="completion-row">
                    <span class="completion-name" title="${w.title}">${w.title}</span>
                    <div class="completion-bar-wrap">
                      <div class="completion-bar"><div class="completion-fill" style="width:${pct}%"></div></div>
                    </div>
                    <span class="completion-label">${completed} / ${total} sessions (${pct}%)</span>
                  </div>`;
            }).join('') || '<p class="light-txt" style="padding:1rem">No session data yet.</p>';
        }

        // At-risk table
        const atRiskTbody = document.getElementById('rpt-atrisk-tbody');
        if (atRiskTbody) {
            const bMap2 = { Enrolled: 'badge-upcoming', Inactive: 'badge-pending', Incomplete: 'badge-pending' };
            atRiskTbody.innerHTML = j.at_risk.map(t => `
              <tr>
                <td><div class="trainee-cell"><div><div class="trainee-name">${t.name}</div></div></div></td>
                <td>${t.workshop}</td>
                <td>${t.sessions}</td>
                <td><span style="font-weight:700;color:var(--color-danger)">${t.rate}</span></td>
                <td class="light-txt">${t.last_seen}</td>
                <td><span class="badge ${bMap2[t.status] || 'badge-pending'}">${t.status}</span></td>
              </tr>`).join('') || '<tr><td colspan="6" style="text-align:center;padding:1rem;color:#aaa">No at-risk trainees. ✅</td></tr>';
        }

    } catch(e) { console.error('loadProgram error:', e); showToast('Error loading program data.'); }
}

/* =============================================================================
   TAB 3 — ATTENDANCE
   ============================================================================= */
async function loadAttendance() {
    try {
        const r = await fetch('get_reports.php?action=attendance&' + getPeriodParams(), { credentials: 'same-origin' });
        const j = await r.json();
        if (!j.success) { showToast('Attendance: ' + (j.message || 'Unknown error')); console.error('Attendance error:', j.message); return; }

        // KPI cards — use IDs for reliable targeting even when tab is hidden
        const setN = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
        setN('att-kpi-total',  j.kpi.total);
        setN('att-kpi-rate',   j.kpi.rate + '%');
        setN('att-kpi-atrisk', j.low_att.length);
        setN('att-kpi-absent', j.kpi.absent);

        // Attendance bars per workshop
        const barsEl = document.getElementById('rpt-att-bars');
        if (barsEl) {
            barsEl.innerHTML = j.att_bars.map(w => {
                const total = parseInt(w.total) || 1;
                const pPct  = Math.round((w.present / total) * 100);
                const lPct  = Math.round((w.late    / total) * 100);
                const aPct  = Math.round((w.absent  / total) * 100);
                return `
                  <div class="att-bar-row">
                    <div class="att-bar-meta">
                      <span class="att-bar-name">${w.name}</span>
                      <span class="att-bar-rate">${pPct}%</span>
                    </div>
                    <div class="att-stacked-bar">
                      <div class="att-seg-present" style="width:${pPct}%"></div>
                      <div class="att-seg-late"    style="width:${lPct}%"></div>
                      <div class="att-seg-absent"  style="width:${aPct}%"></div>
                    </div>
                    <div class="att-bar-pills">
                      <span class="att-pill att-pill-p">P: ${w.present}</span>
                      <span class="att-pill att-pill-l">L: ${w.late}</span>
                      <span class="att-pill att-pill-a">A: ${w.absent}</span>
                    </div>
                  </div>`;
            }).join('') || '<p class="light-txt" style="padding:1rem">No attendance data yet.</p>';
        }

        // Low attendance trainees
        const lowTbody = document.getElementById('rpt-lowatt-tbody');
        if (lowTbody) {
            lowTbody.innerHTML = j.low_att.map(t => `
              <tr>
                <td><div class="trainee-cell"><div class="trainee-name">${t.name}</div></div></td>
                <td>${t.workshop}</td>
                <td><span style="font-weight:700;color:var(--color-danger)">${t.rate}%</span></td>
                <td class="light-txt">${t.sessions}</td>
              </tr>`).join('') || '<tr><td colspan="4" style="text-align:center;padding:1rem;color:#aaa">No low-attendance trainees. ✅</td></tr>';
        }

        // Session log
        const logTbody = document.getElementById('rpt-session-log-tbody');
        if (logTbody) {
            logTbody.innerHTML = j.session_log.map(s => {
                const rate    = parseInt(s.rate) || 0;
                const rateClr = rate >= 80 ? 'var(--color-primary)' : 'var(--color-warning)';
                return `
                  <tr>
                    <td>${s.workshop}</td>
                    <td><strong>${s.session}</strong></td>
                    <td class="light-txt">${s.date}</td>
                    <td><span class="att-stat present-stat" style="font-size:var(--text-caption)">${s.present}</span></td>
                    <td><span class="att-stat late-stat"    style="font-size:var(--text-caption)">${s.late}</span></td>
                    <td><span class="att-stat absent-stat"  style="font-size:var(--text-caption)">${s.absent}</span></td>
                    <td><span style="font-weight:700;color:${rateClr}">${s.rate}</span></td>
                  </tr>`;
            }).join('') || '<tr><td colspan="7" style="text-align:center;padding:1rem;color:#aaa">No sessions logged yet.</td></tr>';
        }

    } catch(e) { console.error('loadAttendance error:', e); showToast('Error loading attendance data.'); }
}

/* =============================================================================
   TAB 4 — CERTIFICATIONS
   ============================================================================= */
async function loadCertifications() {
    try {
        const r = await fetch('get_reports.php?action=certifications&' + getPeriodParams(), { credentials: 'same-origin' });
        const j = await r.json();
        if (!j.success) { showToast('Certs: ' + (j.message || 'Unknown error')); console.error('Certs error:', j.message); return; }

        // KPI cards — use IDs
        const sc = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
        sc('cert-kpi-issued',   j.kpi.issued);
        sc('cert-kpi-eligible', j.kpi.eligible);
        sc('cert-kpi-rate',     j.kpi.cert_rate + '%');
        sc('cert-kpi-sms',      j.kpi.sms_sent);
        sc('cert-kpi-sms-sub',  j.kpi.sms_pending + ' pending');

        // Certs by workshop
        const wTbody = document.getElementById('rpt-cert-workshop-tbody');
        if (wTbody) {
            wTbody.innerHTML = j.cert_by_workshop.map(w => `
              <tr>
                <td>${w.workshop}</td>
                <td>${w.enrolled}</td>
                <td>${w.eligible}</td>
                <td><strong>${w.issued}</strong></td>
                <td><span style="font-weight:700;color:var(--color-primary)">${w.rate}</span></td>
              </tr>`).join('') || '<tr><td colspan="5" style="text-align:center;padding:1rem;color:#aaa">No data.</td></tr>';
        }

        // Eligible not yet issued
        const pendTbody = document.getElementById('rpt-cert-pending-tbody');
        if (pendTbody) {
            pendTbody.innerHTML = j.cert_pending.map(t => `
              <tr>
                <td><div class="trainee-cell"><div class="trainee-name">${t.name}</div></div></td>
                <td>${t.workshop}</td>
                <td><span style="font-weight:700;color:var(--color-primary)">${t.rate}</span></td>
                <td class="light-txt">${t.completedOn}</td>
              </tr>`).join('') || '<tr><td colspan="4" style="text-align:center;padding:1rem;color:#aaa">No eligible trainees pending. ✅</td></tr>';
        }

        // Issued log
        const logTbody = document.getElementById('rpt-cert-log-tbody');
        if (logTbody) {
            logTbody.innerHTML = j.cert_log.map(c => `
              <tr>
                <td><div class="trainee-cell"><div class="trainee-name">${c.name}</div></div></td>
                <td>${c.workshop}</td>
                <td><span style="font-family:monospace;font-size:var(--text-body-sm);color:#4B8423">${c.cert_no}</span></td>
                <td class="light-txt">${c.issued_on}</td>
                <td>${c.rate}</td>
                <td><span class="badge ${c.sms ? 'badge-issued' : 'badge-pending'}">${c.sms ? 'Sent' : 'Pending'}</span></td>
              </tr>`).join('') || '<tr><td colspan="6" style="text-align:center;padding:1rem;color:#aaa">No certificates issued yet.</td></tr>';
        }

    } catch(e) { console.error('loadCertifications error:', e); showToast('Error loading certification data.'); }
}

/* =============================================================================
   TAB 5 — INVENTORY
   ============================================================================= */
async function loadInventory() {
    try {
        const r = await fetch('get_reports.php?action=inventory&' + getPeriodParams(), { credentials: 'same-origin' });
        const j = await r.json();
        if (!j.success) { showToast('Inventory: ' + (j.message || 'Unknown error')); console.error('Inventory error:', j.message); return; }

        // KPI cards — use IDs
        const si = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
        si('inv-kpi-value', fmtPeso(j.kpi.total_value));
        si('inv-kpi-skus',  j.kpi.total_skus);
        si('inv-kpi-low',   j.kpi.low_stock);
        si('inv-kpi-out',   j.kpi.out_of_stock);

        // Stock value table
        const valTbody = document.getElementById('rpt-inv-value-tbody');
        if (valTbody) {
            const totalVal = j.inv_value.reduce((s, i) => s + parseFloat(i.value), 0);
            valTbody.innerHTML = j.inv_value.map(item => {
                const pct  = totalVal > 0 ? ((item.value / totalVal) * 100).toFixed(1) : 0;
                const bCls = item.status === 'instock' ? 'badge-instock' : item.status === 'low' ? 'badge-lowstock' : 'badge-outstock';
                const bTxt = item.status === 'instock' ? 'In Stock' : item.status === 'low' ? 'Low Stock' : 'Out of Stock';
                return `
                  <tr>
                    <td>${item.name}</td>
                    <td>${item.category === 'farm' ? 'Farm Product' : 'Training Kit'}</td>
                    <td>${item.stock} ${item.unit}</td>
                    <td>${fmtPeso(item.unit_price)}</td>
                    <td><strong>${fmtPeso(item.value)}</strong><br><span class="light-txt">${pct}% of total</span></td>
                    <td><span class="badge ${bCls}">${bTxt}</span></td>
                  </tr>`;
            }).join('') || '<tr><td colspan="6" style="text-align:center;padding:1rem;color:#aaa">No items.</td></tr>';
        }

        // Reorder table
        const reorderTbody = document.getElementById('rpt-inv-reorder-tbody');
        if (reorderTbody) {
            reorderTbody.innerHTML = j.inv_reorder.length === 0
                ? '<tr><td colspan="6" style="text-align:center;padding:1rem;color:var(--color-text-light)">All items above reorder points ✅</td></tr>'
                : j.inv_reorder.map(item => {
                    const priority = item.priority === 'critical'
                        ? '<span class="prio-high">🔴 Critical</span>'
                        : '<span class="prio-medium">🟡 Medium</span>';
                    const clr = item.priority === 'critical' ? 'var(--color-danger)' : 'var(--color-warning)';
                    return `
                      <tr>
                        <td>${item.name}</td>
                        <td><strong style="color:${clr}">${item.stock} ${item.unit}</strong></td>
                        <td>${item.reorder_point} ${item.unit}</td>
                        <td>${item.suggest_qty} ${item.unit}</td>
                        <td>${fmtPeso(item.est_cost)}</td>
                        <td>${priority}</td>
                      </tr>`;
                }).join('');
        }

        // Movement table
        const moveTbody = document.getElementById('rpt-inv-movement-tbody');
        if (moveTbody) {
            moveTbody.innerHTML = j.inv_movements.map(m => {
                const net    = m.net;
                const netClr = net >= 0 ? 'var(--color-primary)' : 'var(--color-danger)';
                return `
                  <tr>
                    <td>${m.name}</td>
                    <td>${m.opening_stock} ${m.unit}</td>
                    <td><span style="color:#4B8423;font-weight:700">+${m.total_in}</span></td>
                    <td><span style="color:var(--color-danger);font-weight:700">-${m.total_out}</span></td>
                    <td><strong>${m.current_stock} ${m.unit}</strong></td>
                    <td><strong style="color:${netClr}">${net >= 0 ? '+' : ''}${net}</strong></td>
                  </tr>`;
            }).join('') || '<tr><td colspan="6" style="text-align:center;padding:1rem;color:#aaa">No inventory data.</td></tr>';
        }

    } catch(e) { console.error('loadInventory error:', e); showToast('Error loading inventory data.'); }
}

/* =============================================================================
   EXPORT
   ============================================================================= */
function exportSection(section) {
    const period = document.getElementById('rpt-period')?.value || 'all';
    const from   = document.getElementById('rpt-from')?.value  || '';
    const to     = document.getElementById('rpt-to')?.value    || '';
    const periodParam = (from || to) ? 'custom' : period;

    // Inventory sections → export_inventory.php CSV
    const invMap = { 'inv-value': 'value', 'inv-reorder': 'reorder', 'inv-movements': 'movements' };
    if (invMap[section]) {
        showToast('📥 Exporting inventory CSV…');
        window.open('export_inventory.php?type=' + invMap[section] + '&format=csv', '_blank');
        return;
    }

    // Full overview → PDF view
    if (section === 'overview') {
        const params = new URLSearchParams({ sections: 'program,attendance,certifications,inventory', format: 'pdf', period: periodParam, from, to });
        window.open('export_reports.php?' + params.toString(), '_blank');
        return;
    }

    // Cert PDF section → PDF view
    if (section === 'certs-pdf') {
        const params = new URLSearchParams({ sections: 'certifications', format: 'pdf', period: periodParam, from, to });
        window.open('export_reports.php?' + params.toString(), '_blank');
        return;
    }

    // All other sections → CSV via export_reports.php
    const csvSectionMap = {
        'enrollment':     'program',
        'completion':     'program',
        'atrisk':         'attendance',
        'att-workshop':   'attendance',
        'low-att':        'attendance',
        'session-log':    'attendance',
        'certs-workshop': 'certifications',
        'cert-pending':   'certifications',
        'certs-log':      'certifications',
    };
    const apiSection = csvSectionMap[section] || 'program';
    const params = new URLSearchParams({ sections: apiSection, format: 'csv', period: periodParam, from, to });
    showToast('📥 Exporting CSV…');
    window.open('export_reports.php?' + params.toString(), '_blank');
}

function doExportAll() {
    const format   = document.getElementById('exp-all-format')?.value || 'pdf';
    const period   = document.getElementById('rpt-period')?.value || 'all';
    const from     = document.getElementById('exp-all-from')?.value || '';
    const to       = document.getElementById('exp-all-to')?.value   || '';

    const checkboxes = document.querySelectorAll('#export-all-modal input[type=checkbox]');
    const sectionMap = ['program','attendance','certifications','inventory'];
    const sections   = [];
    checkboxes.forEach((cb, i) => { if (cb.checked && sectionMap[i]) sections.push(sectionMap[i]); });

    if (!sections.length) { showToast('Please select at least one section.'); return; }

    closeModal('export-all-modal');
    showToast('📥 Preparing full report as ' + format.toUpperCase() + '…');

    const params = new URLSearchParams({
        sections: sections.join(','),
        format,
        period: (from || to) ? 'custom' : period,
        from,
        to,
    });
    window.open('export_reports.php?' + params.toString(), '_blank');
}

function exportAsPdf() {
    const period = document.getElementById('rpt-period')?.value || 'all';
    const from   = document.getElementById('rpt-from')?.value  || '';
    const to     = document.getElementById('rpt-to')?.value    || '';
    const tabSectionMap = {
        overview:       'program,attendance,certifications,inventory',
        program:        'program',
        attendance:     'attendance',
        certifications: 'certifications',
        inventory:      'inventory',
    };
    const sections = tabSectionMap[activeRptTab] || 'program,attendance,certifications,inventory';
    const params = new URLSearchParams({ sections, format: 'pdf', period: (from||to)?'custom':period, from, to });
    window.open('export_reports.php?' + params.toString(), '_blank');
}

/* =============================================================================
   MODAL HELPERS
   ============================================================================= */
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
function closeModalOutside(e, id) { if (e.target.id === id) closeModal(id); }

/* =============================================================================
   INIT
   ============================================================================= */
document.addEventListener('DOMContentLoaded', async function () {
    // Set sensible defaults for date pickers
    const today = new Date().toISOString().split('T')[0];
    const firstOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1)
        .toISOString().split('T')[0];

    // Export modal dates
    const expFrom = document.getElementById('exp-all-from');
    const expTo   = document.getElementById('exp-all-to');
    if (expFrom) expFrom.value = firstOfMonth;
    if (expTo)   expTo.value   = today;

    // Report custom range — default from = first of month, to = today
    const rptFrom = document.getElementById('rpt-from');
    const rptTo   = document.getElementById('rpt-to');
    if (rptFrom) rptFrom.value = firstOfMonth;
    if (rptTo)   rptTo.value   = today;

    // Load overview (default tab)
    await loadOverview();
});