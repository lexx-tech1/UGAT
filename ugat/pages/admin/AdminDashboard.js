/**
 * AdminDashboard.js — UGAT TrainTrack
 * Fetches real data from get_dashboard.php and renders all dashboard sections.
 */

document.addEventListener('DOMContentLoaded', async function () {
    try {
        const response = await fetch('get_dashboard.php');
        const result   = await response.json();

        if (!result.success) {
            console.error('Dashboard error:', result.message);
            return;
        }

        const d = result.data;

        renderKPIs(d);
        renderEnrollmentChart(d.enrollment_chart);
        renderAttendanceBars(d.attendance_bars);
        renderUpcomingSessions(d.upcoming_sessions);
        renderCertDonut(d.cert_donut);
        renderStockAlerts(d.stock_alerts_list);
        renderActivityFeed(d.activity);

    } catch (err) {
        console.error('Dashboard fetch error:', err);
    }
});


/* =============================================================================
   KPI CARDS
   ============================================================================= */
function renderKPIs(d) {
    // Total Trainees
    setKPI('kpi-trainees',       d.total_trainees);
    setKPI('kpi-trainees-sub',   `↑ ${d.trainees_this_month} this month`);

    // Active Workshops
    setKPI('kpi-workshops',      d.active_workshops);
    setKPI('kpi-workshops-sub',  `${d.upcoming_workshops} upcoming · ${d.ongoing_workshops} ongoing`);

    // Attendance
    setKPI('kpi-attendance',     d.avg_attendance);

    // Certificates
    setKPI('kpi-certs',          d.certs_issued);
    setKPI('kpi-certs-sub',      `● ${d.certs_eligible} eligible pending`);

    // Stock Alerts
    setKPI('kpi-stock',          d.stock_alerts);
    setKPI('kpi-stock-sub',      `● ${d.stock_out} out · ${d.stock_low} low stock`);

    // Donut center
    setKPI('donut-num',          d.certs_issued);
}

function setKPI(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
}


/* =============================================================================
   ENROLLMENT LINE CHART
   ============================================================================= */
function renderEnrollmentChart(chartData) {
    const canvas = document.getElementById('enrollment-chart');
    if (!canvas || !chartData) return;
    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const W   = canvas.parentElement.clientWidth;
    const H   = 200;

    canvas.width  = W * dpr; canvas.height = H * dpr;
    canvas.style.width = W + 'px'; canvas.style.height = H + 'px';
    ctx.scale(dpr, dpr);

    const pad  = { top:20, right:20, bottom:36, left:40 };
    const cW   = W - pad.left - pad.right;
    const cH   = H - pad.top  - pad.bottom;
    const maxV = Math.max(...chartData.enrolled, ...chartData.certified, 1) + 3;
    const cols  = chartData.labels.length;
    const step  = cols > 1 ? cW / (cols - 1) : cW;

    // Grid
    ctx.strokeStyle = '#eee'; ctx.lineWidth = 1;
    for (let i = 0; i <= 4; i++) {
        const y = pad.top + (cH / 4) * i;
        ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(pad.left + cW, y); ctx.stroke();
        ctx.fillStyle = '#aaa'; ctx.font = '11px Plus Jakarta Sans'; ctx.textAlign = 'right';
        ctx.fillText(Math.round(maxV - (maxV / 4) * i), pad.left - 6, y + 4);
    }

    // X labels
    ctx.fillStyle = '#aaa'; ctx.font = '11px Plus Jakarta Sans'; ctx.textAlign = 'center';
    chartData.labels.forEach((lbl, i) => {
        ctx.fillText(lbl, pad.left + i * step, H - 10);
    });

    function drawLine(data, color, fillColor) {
        if (!data || !data.length) return;
        const pts = data.map((v, i) => ({
            x: pad.left + i * step,
            y: pad.top + cH - (v / maxV) * cH,
        }));
        ctx.beginPath();
        ctx.moveTo(pts[0].x, pad.top + cH);
        pts.forEach(p => ctx.lineTo(p.x, p.y));
        ctx.lineTo(pts[pts.length-1].x, pad.top + cH);
        ctx.closePath(); ctx.fillStyle = fillColor; ctx.fill();
        ctx.beginPath(); ctx.moveTo(pts[0].x, pts[0].y);
        pts.forEach(p => ctx.lineTo(p.x, p.y));
        ctx.strokeStyle = color; ctx.lineWidth = 2.5; ctx.lineJoin = 'round'; ctx.stroke();
        pts.forEach(p => {
            ctx.beginPath(); ctx.arc(p.x, p.y, 4, 0, Math.PI * 2);
            ctx.fillStyle = color; ctx.fill();
            ctx.strokeStyle = '#fff'; ctx.lineWidth = 2; ctx.stroke();
        });
    }

    drawLine(chartData.certified, '#8dc63f', 'rgba(141,198,63,0.08)');
    drawLine(chartData.enrolled,  '#4B8423', 'rgba(75,132,35,0.10)');
}


/* =============================================================================
   ATTENDANCE BARS
   ============================================================================= */
function renderAttendanceBars(bars) {
    const container = document.getElementById('attendance-bars');
    if (!container) return;

    if (!bars || !bars.length) {
        container.innerHTML = '<p style="color:#aaa;padding:1rem;text-align:center">No attendance data yet.</p>';
        return;
    }

    container.innerHTML = bars.map(w => {
        const pPct = w.total ? Math.round((w.present / w.total) * 100) : 0;
        const lPct = w.total ? Math.round((w.late    / w.total) * 100) : 0;
        const aPct = w.total ? Math.round((w.absent  / w.total) * 100) : 0;
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
    }).join('');
}


/* =============================================================================
   UPCOMING SESSIONS
   ============================================================================= */
function renderUpcomingSessions(sessions) {
    const el = document.getElementById('upcoming-sessions');
    if (!el) return;

    if (!sessions || !sessions.length) {
        el.innerHTML = '<p style="color:#aaa;padding:1rem;text-align:center">No upcoming sessions.</p>';
        return;
    }

    const badgeMap = { upcoming:'badge-upcoming', ongoing:'badge-ongoing', completed:'badge-completed' };
    const labelMap = { upcoming:'Upcoming', ongoing:'Ongoing', completed:'Completed' };

    el.innerHTML = sessions.map(s => `
        <div class="session-item">
          <div class="session-date-badge">
            <div class="session-date-day">${s.day}</div>
            <div class="session-date-mon">${s.month}</div>
          </div>
          <div class="session-info">
            <div class="session-title">${s.title}</div>
            <div class="session-meta">${s.meta}</div>
          </div>
          <span class="badge ${badgeMap[s.status] || 'badge-upcoming'}">${labelMap[s.status] || 'Upcoming'}</span>
        </div>`).join('');
}


/* =============================================================================
   CERTIFICATION DONUT
   ============================================================================= */
function renderCertDonut(donutData) {
    const canvas = document.getElementById('cert-donut');
    if (!canvas || !donutData) return;
    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const S   = 160;
    canvas.width = S * dpr; canvas.height = S * dpr;
    canvas.style.width = S + 'px'; canvas.style.height = S + 'px';
    ctx.scale(dpr, dpr);

    const cx = S/2, cy = S/2, R = 62, r = 38;
    const total = donutData.reduce((s, d) => s + d.value, 0) || 1;
    let angle = -Math.PI / 2;

    donutData.forEach(seg => {
        const sweep = (seg.value / total) * 2 * Math.PI;
        ctx.beginPath(); ctx.moveTo(cx, cy);
        ctx.arc(cx, cy, R, angle, angle + sweep);
        ctx.closePath(); ctx.fillStyle = seg.color; ctx.fill();
        angle += sweep;
    });

    ctx.beginPath(); ctx.arc(cx, cy, r, 0, 2 * Math.PI);
    ctx.fillStyle = '#fff'; ctx.fill();

    const legendEl = document.getElementById('donut-legend');
    if (legendEl) {
        legendEl.innerHTML = donutData.map(seg => `
            <div class="donut-leg-row">
              <span class="donut-leg-left">
                <span class="donut-leg-dot" style="background:${seg.color}"></span>
                ${seg.label}
              </span>
              <span class="donut-leg-val">${seg.value}</span>
            </div>`).join('');
    }
}


/* =============================================================================
   STOCK ALERTS
   ============================================================================= */
function renderStockAlerts(alerts) {
    const el = document.getElementById('stock-alerts-list');
    if (!el) return;

    if (!alerts || !alerts.length) {
        el.innerHTML = '<p style="color:#4B8423;padding:1rem;text-align:center">✅ All stock levels are good!</p>';
        return;
    }

    const cfg = {
        out: { badge:'badge-outstock', label:'Out of stock', dot:'#e74c3c' },
        low: { badge:'badge-lowstock', label:'Low stock',    dot:'#f4a523' },
    };

    el.innerHTML = alerts.map(s => `
        <div class="stock-alert-item">
          <span style="width:8px;height:8px;border-radius:50%;background:${cfg[s.status].dot};flex-shrink:0;margin-top:3px"></span>
          <div class="stock-alert-info">
            <div class="stock-alert-name">${s.name}</div>
            <div class="stock-alert-qty">${s.qty} remaining</div>
          </div>
          <span class="badge ${cfg[s.status].badge}">${cfg[s.status].label}</span>
        </div>`).join('');
}


/* =============================================================================
   ACTIVITY FEED
   ============================================================================= */
function renderActivityFeed(activity) {
    const el = document.getElementById('activity-feed');
    if (!el) return;

    if (!activity || !activity.length) {
        el.innerHTML = '<p style="color:#aaa;padding:1rem;text-align:center">No recent activity.</p>';
        return;
    }

    el.innerHTML = activity.map(a => `
        <div class="activity-item">
          <span class="activity-dot" style="background:${a.color}"></span>
          <span class="activity-text">${a.text}</span>
          <span class="activity-time">${a.time}</span>
        </div>`).join('');
}


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