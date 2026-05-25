/* TraineeWorkshops.js */

/* =================================================================
   DATA
   ================================================================= */
let MY_WORKSHOPS = []; // will be populated from backend

const AVAILABLE_WORKSHOPS = [
  { id:'mushroom',    title:'Mushroom Cultivation',           date:'May 3, 2026',   sessions:3, slots:10 },
  { id:'hydroponics', title:'Hydroponics System Setup',       date:'May 10, 2026',  sessions:3, slots:2  },
  { id:'raised-bed',  title:'Raised Bed Vegetable Gardening', date:'May 20, 2026',  sessions:3, slots:5  },
  { id:'vermicompost',title:'Vermicomposting Basics',         date:'Jun 1, 2026',   sessions:2, slots:8  },
];

const STATUS_MAP = { upcoming:'badge-upcoming', ongoing:'badge-ongoing', completed:'badge-completed' };
const CERT_MAP   = { issued:'badge-issued', pending:'badge-pending', locked:'badge-locked' };

/* =================================================================
   TAB SWITCHING
   ================================================================= */

function switchTab(tab, btn) {
  document.querySelectorAll('.pm-tab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('tab-workshops').style.display      = tab==='workshops'      ? '' : 'none';
  document.getElementById('tab-certifications').style.display = tab==='certifications' ? '' : 'none';
  if (tab === 'certifications') {
    if (!MY_WORKSHOPS.length) {
      loadMyWorkshops().then(function() { renderCerts(); });
    } else {
      renderCerts();
    }
  }
}
/* =================================================================
   WORKSHOPS TAB
   ================================================================= */

function renderWorkshops() {
  const q    = (document.getElementById('ws-search')?.value || '').toLowerCase();
  const list = document.getElementById('ws-list');
  const data = MY_WORKSHOPS.filter(w =>
    w.title.toLowerCase().includes(q) || w.category.toLowerCase().includes(q)
  );

  if (!data.length) {
    list.innerHTML = '<p class="light-txt" style="padding:2rem 0;text-align:center">No workshops found.</p>';
    return;
  }

  const groups = { ongoing:'Ongoing Workshops', upcoming:'Upcoming Workshops', completed:'Completed' };
  let html = '';
  ['ongoing','upcoming','completed'].forEach(status => {
    const items = data.filter(w => w.status === status);
    if (!items.length) return;
    html += '<h3 class="section-title" style="margin-top:1rem">' + groups[status] + '</h3>';
    items.forEach(w => {
      const pct = Math.round((w.attended / w.total) * 100);
      const sessionChips = w.sessions.map(s =>
        '<div class="session-chip ' + (s.done ? 'done' : s.current ? 'current' : 'upcoming') + '">' +
          '<span class="session-chip-label">' + s.label + '</span>' +
          '<span class="session-chip-date">' + s.date + '</span>' +
          '<span style="font-size:9px">' + (s.done ? '✓ Done' : s.current ? 'Next' : '—') + '</span>' +
        '</div>'
      ).join('');

      const certLabel = w.certStatus === 'issued' ? 'Cert. issued'
                      : w.certStatus === 'pending' ? 'Cert. pending' : 'Not started';

      html +=
        '<div class="ws-list-item">' +
          '<img src="' + w.img + '" class="ws-list-img" alt="' + w.title + '" onerror="this.src=\'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400\'">' +
          '<div class="ws-list-body">' +
            '<div class="ws-list-top">' +
              '<div>' +
                '<p class="ws-list-cat">' + w.category + '</p>' +
                '<h4 class="ws-list-title">' + w.title + '</h4>' +
              '</div>' +
              '<span class="badge ' + STATUS_MAP[w.status] + '">' + w.status.charAt(0).toUpperCase() + w.status.slice(1) + '</span>' +
            '</div>' +
            '<div class="ws-list-meta">' +
              '<span>📅 ' + w.dateRange + '</span>' +
              '<span>📍 ' + w.location + '</span>' +
              '<span>👩‍🏫 Facilitator: ' + w.facilitator + '</span>' +
            '</div>' +
            '<p style="font-size:var(--text-caption);font-weight:600;color:var(--color-text);margin-top:0.5rem">Sessions</p>' +
            '<div class="ws-sessions-row">' + sessionChips + '</div>' +
            '<div class="ws-list-footer">' +
              '<div class="progress-bar" style="width:120px"><div class="progress-fill" style="width:' + pct + '%"></div></div>' +
              '<span class="ws-progress-txt">' + w.attended + ' of ' + w.total + ' sessions done</span>' +
              '<span class="badge ' + CERT_MAP[w.certStatus] + '">' + certLabel + '</span>' +
            '</div>' +
          '</div>' +
        '</div>';
    });
  });
  list.innerHTML = html;
}

/* =================================================================
   CERTIFICATIONS TAB
   ================================================================= */
function renderCerts() {
  const issued   = MY_WORKSHOPS.filter(w => w.certStatus === 'issued');
  const eligible = MY_WORKSHOPS.filter(w => w.certStatus === 'pending');
  const locked   = MY_WORKSHOPS.filter(w => w.certStatus === 'locked');

  // Update the 3 stat cards
  document.getElementById('cert-stat-issued').textContent   = issued.length;
  document.getElementById('cert-stat-eligible').textContent = eligible.length;
  document.getElementById('cert-stat-locked').textContent   = locked.length;

  document.getElementById('cert-issued-list').innerHTML   = issued.map(certCard).join('')   || '<p class="light-txt">No certificates issued yet.</p>';
  document.getElementById('cert-eligible-list').innerHTML = eligible.map(certCard).join('') || '<p class="light-txt">None eligible yet.</p>';
  document.getElementById('cert-locked-list').innerHTML   = locked.map(certCard).join('')   || '<p class="light-txt">None locked.</p>';
}
function certCard(w) {
  const isIssued   = w.certStatus === 'issued';
  const isEligible = w.certStatus === 'pending';

  const actions = isIssued
    ? '<button class="btn-primary" style="font-size:var(--text-caption);padding:0.4rem 1rem" onclick="viewCert(\'' + w.id + '\')">⬇ Download Certificate</button>'
    : isEligible
    ? '<button class="btn-outline" style="font-size:var(--text-caption);padding:0.4rem 1rem;cursor:default" disabled>Certificate not yet issued</button>'
    : '<button class="btn-outline" style="font-size:var(--text-caption);padding:0.4rem 1rem;opacity:0.5;cursor:not-allowed" disabled>Complete sessions first</button>';

  const badgeLabel = isIssued ? 'Issued' : isEligible ? 'Pending Issuance' : 'Locked';
  const badgeCls   = isIssued ? 'badge-issued' : isEligible ? 'badge-pending' : 'badge-locked';
  const border     = isIssued ? '#4B8423' : isEligible ? '#f4a523' : '#ccc';

  return '<div class="cert-card" style="border-color:' + border + '">' +
    '<img src="' + w.img + '" class="cert-card-img" alt="' + w.title + '">' +
    '<div class="cert-card-body">' +
      '<div style="display:flex;justify-content:space-between;align-items:flex-start">' +
        '<h4 class="cert-card-title">' + w.title + '</h4>' +
        '<span class="badge ' + badgeCls + '" style="flex-shrink:0">' + badgeLabel + '</span>' +
      '</div>' +
      (isIssued
        ? '<p class="cert-card-sub">Issued ' + w.certDate + ' · UGAT Integrated Farm</p>'
        : '<p class="cert-card-sub">' + w.dateRange + '</p>') +
      '<div class="cert-card-rows">' +
        '<div class="cert-card-row"><span>Session Completed</span><strong>' + w.attended + ' of ' + w.total + '</strong></div>' +
        '<div class="cert-card-row"><span>Attendance Rate</span><strong>' + w.rate + '%</strong></div>' +
        (isIssued ? '<div class="cert-card-row"><span>Certificate No.</span><strong style="font-family:monospace;color:#4B8423">' + w.certNo + '</strong></div>' : '') +
        (!isIssued && !isEligible ? '<div class="cert-card-row"><span>Status</span><strong style="color:#999">Complete all sessions to qualify</strong></div>' : '') +
      '</div>' +
      '<div class="cert-card-actions">' + actions + '</div>' +
    '</div>' +
  '</div>';
}

function viewCert(id) {
  var w = MY_WORKSHOPS.find(function(x) { return x.id === id; });
  if (!w) return;

  var name   = w.traineeName || 'Trainee';
  var certNo = w.certNo || '—';
  var ws     = w.title || '—';

  // Parse certDate ("May 22, 2026") into day and "Month Year"
  var dt = w.certDate ? new Date(w.certDate) : new Date();
  var day       = dt.getDate();
  var monthYear = dt.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
  var issuedFmt = dt.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }).toUpperCase();

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
    '<defs>' +
      '<path id="vct" d="M13,43 A30,30 0 0,1 73,43"/>' +
      '<path id="vcb" d="M18,57 A28,28 0 0,0 68,57"/>' +
    '</defs>' +
    '<text fill="#8dc63f" font-size="7.5" letter-spacing="1.8" font-family="Arial,sans-serif" font-weight="bold"><textPath href="#vct" startOffset="4%">UGAT TRAINTRACK</textPath></text>' +
    '<text fill="#8dc63f" font-size="7" letter-spacing="1.5" font-family="Arial,sans-serif" font-weight="bold"><textPath href="#vcb" startOffset="8%">OFFICIAL SEAL</textPath></text>' +
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
          '<div style="margin-bottom:22px;">' +
            '<div style="font-size:22px;font-weight:700;color:#1e4d0f;display:inline-block;padding-bottom:4px;border-bottom:2.5px solid #c9a227;letter-spacing:0.01em;">Certificate of Completion</div>' +
          '</div>' +
          '<div style="font-size:30px;font-weight:700;font-style:italic;color:#1e4d0f;line-height:1.15;margin-bottom:0;">' + name + '</div>' +
          '<div style="width:55%;height:1.5px;background:#4B8423;margin:10px auto 14px;"></div>' +
          '<p style="font-size:10px;color:#444;margin-bottom:6px;line-height:1.55;">has successfully completed all requirements and demonstrated satisfactory competence in</p>' +
          '<div style="font-size:17px;font-weight:700;color:#1e4d0f;margin-bottom:3px;">' + ws + '</div>' +
          '<div style="font-size:7.5px;letter-spacing:0.22em;color:#888;margin-bottom:8px;font-family:Arial,sans-serif;">UGAT TRAINTRACK CERTIFIED PROGRAM</div>' +
          '<div style="border-top:1.5px dashed #aaa;width:75%;margin:8px auto;"></div>' +
          '<p style="font-size:9.5px;font-style:italic;color:#555;margin-bottom:5px;line-height:1.55;">Awarded in recognition of dedicated participation, practical learning, and commitment to sustainable agriculture.</p>' +
          '<p style="font-size:10px;color:#333;margin-bottom:12px;"><em>Given this <strong>' + day + '</strong> day of <strong>' + monthYear + '</strong> at <strong>San Isidro, Daet, Camarines Norte.</strong></em></p>' +
          '<div style="display:flex;justify-content:center;margin:4px 0 14px;">' + sealSVG + '</div>' +
          '<div style="display:flex;justify-content:space-between;padding:0 24px;">' +
            '<div style="text-align:center;width:130px;">' +
              '<div style="height:1px;background:#1e4d0f;margin-bottom:5px;"></div>' +
              '<div style="font-size:7.5px;letter-spacing:0.1em;color:#222;font-family:Arial,sans-serif;text-transform:uppercase;">Program Coordinator</div>' +
              '<div style="font-size:8px;color:#666;font-style:italic;">UGAT Integrated Farm</div>' +
            '</div>' +
            '<div style="text-align:center;width:130px;">' +
              '<div style="height:1px;background:#1e4d0f;margin-bottom:5px;"></div>' +
              '<div style="font-size:7.5px;letter-spacing:0.1em;color:#222;font-family:Arial,sans-serif;text-transform:uppercase;">Farm Director</div>' +
              '<div style="font-size:8px;color:#666;font-style:italic;">UGAT Integrated Farm</div>' +
            '</div>' +
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
      '<button class="btn-outline" onclick="_downloadTraineeCert(\'' + id + '\',\'png\')">🖼 Save as Image</button>' +
      '<button class="btn-primary" onclick="_downloadTraineeCert(\'' + id + '\',\'pdf\')">📄 Download PDF</button>' +
    '</div>';
  openModal('cert-view-modal');
}

async function _downloadTraineeCert(id, type) {
  var certEl = document.querySelector('#cert-view-body > div:first-child');
  if (!certEl) return;
  var w = MY_WORKSHOPS.find(function(x) { return x.id === id; });
  var filename = 'UGAT_Certificate_' + ((w && w.traineeName) || 'Trainee').replace(/\s+/g, '_');
  await _downloadCertAs(certEl, type || 'pdf', filename);
}

async function _downloadCertAs(certEl, type, filename) {
  showToast('⏳ Generating ' + (type === 'pdf' ? 'PDF' : 'image') + '…');
  try {
    var canvas = await html2canvas(certEl, {
      scale: 2, useCORS: true, logging: false, backgroundColor: '#f4f9ee',
    });
    if (type === 'png') {
      canvas.toBlob(function(blob) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url; a.download = filename + '.png';
        document.body.appendChild(a); a.click();
        document.body.removeChild(a); URL.revokeObjectURL(url);
        showToast('✅ Image saved!');
      }, 'image/png');
    } else {
      var imgData = canvas.toDataURL('image/jpeg', 0.95);
      var jsPDF = window.jspdf.jsPDF;
      var w = certEl.offsetWidth, h = certEl.offsetHeight;
      var pdf = new jsPDF({ orientation: w > h ? 'l' : 'p', unit: 'px', format: [w, h] });
      pdf.addImage(imgData, 'JPEG', 0, 0, w, h);
      pdf.save(filename + '.pdf');
      showToast('✅ PDF downloaded!');
    }
  } catch(e) {
    console.error('Certificate download error:', e);
    showToast('❌ Could not generate file. Please try again.');
  }
}

/* =================================================================
   ADDRESS CASCADE HELPERS
   ================================================================= */

async function fetchAddr(type, parentId) {
  try {
    var paramMap = { provinces: 'region_id', cities: 'province_id', barangays: 'city_id' };
    var qs = '?type=' + type;
    if (parentId) qs += '&' + paramMap[type] + '=' + parentId;
    var res = await fetch('../admin/get_address.php' + qs);
    var d   = await res.json();
    return d.success ? (d[type] || []) : [];
  } catch(e) { return []; }
}

function fillSelect(id, items, placeholder, enable) {
  if (enable === undefined) enable = true;
  var sel = document.getElementById(id);
  if (!sel) return;
  sel.innerHTML = '<option value="">' + placeholder + '</option>';
  items.forEach(function(item) {
    var opt = document.createElement('option');
    opt.value = item.id;
    opt.textContent = item.name;
    sel.appendChild(opt);
  });
  sel.disabled = !enable;
  sel.style.opacity = enable ? '1' : '0.55';
  sel.style.cursor  = enable ? 'auto' : 'not-allowed';
}

function resetSelect(id, placeholder) {
  fillSelect(id, [], placeholder, false);
}

/* ── STEP 1 address cascade ── */
async function loadS1Regions() {
  var regions = await fetchAddr('regions');
  fillSelect('e-s1-region', regions, 'Select region…', true);
}

async function onS1RegionChange() {
  var id = document.getElementById('e-s1-region').value;
  resetSelect('e-s1-province', 'Select province…');
  resetSelect('e-s1-city',     'Select city…');
  resetSelect('e-s1-barangay', 'Select barangay…');
  buildS1Address();
  if (!id) return;
  var items = await fetchAddr('provinces', id);
  fillSelect('e-s1-province', items, 'Select province…', true);
}

async function onS1ProvinceChange() {
  var id = document.getElementById('e-s1-province').value;
  resetSelect('e-s1-city',     'Select city…');
  resetSelect('e-s1-barangay', 'Select barangay…');
  buildS1Address();
  if (!id) return;
  var items = await fetchAddr('cities', id);
  fillSelect('e-s1-city', items, 'Select city…', true);
}

async function onS1CityChange() {
  var id = document.getElementById('e-s1-city').value;
  resetSelect('e-s1-barangay', 'Select barangay…');
  buildS1Address();
  if (!id) return;
  var items = await fetchAddr('barangays', id);
  fillSelect('e-s1-barangay', items, 'Select barangay…', true);
}

function buildS1Address() {
  var street   = document.getElementById('e-s1-street')?.value.trim()   || '';
  var brgy     = document.getElementById('e-s1-barangay');
  var city     = document.getElementById('e-s1-city');
  var province = document.getElementById('e-s1-province');
  var region   = document.getElementById('e-s1-region');

  var SKIP = ['Select region…','Select province…','Select city…','Select barangay…',''];
  var brgyName     = brgy     ? brgy.options[brgy.selectedIndex]?.text         || '' : '';
  var cityName     = city     ? city.options[city.selectedIndex]?.text         || '' : '';
  var provinceName = province ? province.options[province.selectedIndex]?.text || '' : '';
  var regionName   = region   ? region.options[region.selectedIndex]?.text     || '' : '';

  var parts = [street, brgyName, cityName, provinceName, regionName]
    .map(function(p) { return p.trim(); })
    .filter(function(p) { return p && SKIP.indexOf(p) === -1; });

  var set = function(id, val) { var el = document.getElementById(id); if (el) el.value = val; };
  set('e-address',  parts.join(', '));
  set('e-city',     cityName);
  set('e-province', provinceName);
  set('e-region',   regionName);
  set('e-barangay', brgyName);
}

/* ── BIRTHPLACE cascade ── */
async function onBplaceRegionChange() {
  var id = document.getElementById('e-bplace-region').value;
  resetSelect('e-bplace-province', 'Select province…');
  resetSelect('e-bplace-city',     'Select city…');
  resetSelect('e-bplace-barangay', 'Select barangay…');
  if (!id) return;
  var items = await fetchAddr('provinces', id);
  fillSelect('e-bplace-province', items, 'Select province…', true);
}

async function onBplaceProvinceChange() {
  var id = document.getElementById('e-bplace-province').value;
  resetSelect('e-bplace-city',     'Select city…');
  resetSelect('e-bplace-barangay', 'Select barangay…');
  if (!id) return;
  var items = await fetchAddr('cities', id);
  fillSelect('e-bplace-city', items, 'Select city…', true);
}

async function onBplaceCityChange() {
  var id = document.getElementById('e-bplace-city').value;
  resetSelect('e-bplace-barangay', 'Select barangay…');
  if (!id) return;
  var items = await fetchAddr('barangays', id);
  fillSelect('e-bplace-barangay', items, 'Select barangay…', true);
}

/* ── PARENT/GUARDIAN cascade ── */
async function onParentRegionChange() {
  var id = document.getElementById('e-parent-region').value;
  resetSelect('e-parent-province', 'Select province…');
  resetSelect('e-parent-city',     'Select city…');
  resetSelect('e-parent-barangay', 'Select barangay…');
  if (!id) return;
  var items = await fetchAddr('provinces', id);
  fillSelect('e-parent-province', items, 'Select province…', true);
}

async function onParentProvinceChange() {
  var id = document.getElementById('e-parent-province').value;
  resetSelect('e-parent-city',     'Select city…');
  resetSelect('e-parent-barangay', 'Select barangay…');
  if (!id) return;
  var items = await fetchAddr('cities', id);
  fillSelect('e-parent-city', items, 'Select city…', true);
}

async function onParentCityChange() {
  var id = document.getElementById('e-parent-city').value;
  resetSelect('e-parent-barangay', 'Select barangay…');
  if (!id) return;
  var items = await fetchAddr('barangays', id);
  fillSelect('e-parent-barangay', items, 'Select barangay…', true);
}

/* ── Same address checkbox ── */
async function toggleParentSameAddress(checked) {
  if (checked) {
    // Use Step 1 dropdown IDs directly
    var rSel1 = document.getElementById('e-s1-region');
    var pSel1 = document.getElementById('e-s1-province');
    var cSel1 = document.getElementById('e-s1-city');
    var bSel1 = document.getElementById('e-s1-barangay');

    var regionId   = rSel1 ? rSel1.value : '';
    var provinceId = pSel1 ? pSel1.value : '';
    var cityId     = cSel1 ? cSel1.value : '';
    var barangayId = bSel1 ? bSel1.value : '';
    var street     = document.getElementById('e-s1-street')?.value.trim() || '';

    // Street
    var streetEl = document.getElementById('e-parent-street');
    if (streetEl) { streetEl.value = street; streetEl.disabled = true; }

    // Region
    var regions = await fetchAddr('regions');
    fillSelect('e-parent-region', regions, 'Select region…', true);
    var rSel = document.getElementById('e-parent-region');
    if (regionId) rSel.value = regionId;
    rSel.disabled = true; rSel.style.opacity = '0.55'; rSel.style.cursor = 'not-allowed';
    if (!rSel.value) return;

    // Province
    var provinces = await fetchAddr('provinces', regionId);
    fillSelect('e-parent-province', provinces, 'Select province…', true);
    var pSel = document.getElementById('e-parent-province');
    if (provinceId) pSel.value = provinceId;
    pSel.disabled = true; pSel.style.opacity = '0.55'; pSel.style.cursor = 'not-allowed';
    if (!pSel.value) return;

    // City
    var cities = await fetchAddr('cities', provinceId);
    fillSelect('e-parent-city', cities, 'Select city…', true);
    var cSel = document.getElementById('e-parent-city');
    if (cityId) cSel.value = cityId;
    cSel.disabled = true; cSel.style.opacity = '0.55'; cSel.style.cursor = 'not-allowed';
    if (!cSel.value) return;

    // Barangay — copy and lock
    var barangays = await fetchAddr('barangays', cityId);
    fillSelect('e-parent-barangay', barangays, 'Select barangay…', true);
    var bSel = document.getElementById('e-parent-barangay');
    if (barangayId) bSel.value = barangayId;
    bSel.disabled = true; bSel.style.opacity = '0.55'; bSel.style.cursor = 'not-allowed';

  } else {
    // Uncheck — clear and re-enable all
    var regions = await fetchAddr('regions');
    fillSelect('e-parent-region',    regions, 'Select region…', true);
    resetSelect('e-parent-province', 'Select province…');
    resetSelect('e-parent-city',     'Select city…');
    resetSelect('e-parent-barangay', 'Select barangay…');
    var streetEl = document.getElementById('e-parent-street');
    if (streetEl) { streetEl.value = ''; streetEl.disabled = false; }
  }
}

/* ── Load regions on Step 2 open ── */
async function loadStep2Regions() {
  var regions = await fetchAddr('regions');
  fillSelect('e-bplace-region', regions, 'Select region…', true);
  fillSelect('e-parent-region', regions, 'Select region…', true);
}

/* =================================================================
   ENROLLMENT MODAL
   ================================================================= */

var enrollStep   = 1;
var enrollData   = {};
var _profileData = null; // cached profile for form prefill

function openEnrollModal(preselectedId) {

    enrollStep = 1;
    enrollGoStep(1, true);
    openModal('enroll-modal');
    renderWorkshopSelectGrid(preselectedId);
    prefillEnrollmentForm();
}
async function prefillEnrollmentForm() {
  try {
    var res  = await fetch('../auth/get_session.php?t=' + Date.now());
    var data = await res.json();
    if (!data.success) return;
    var u = data.user;
    _profileData = u;

    function fill(id, value) {
      var el = document.getElementById(id);
      if (el && !el.value) el.value = value || '';
    }

    fill('e-lastname',    u.last_name);
    fill('e-firstname',   u.first_name);
    fill('e-middlename',  u.middle_name);
    fill('e-contact',     u.phone);
    fill('e-email',       u.email);
    fill('e-nationality', u.nationality);
    fill('e-dob',         u.birthdate);

    // Step 2 static fields — DOM exists even when step is hidden
    fill('e-parent-name', u.guardian_name);

    if (u.gender) {
      var sexRadio = document.querySelector('input[name="e-sex"][value="' + u.gender + '"]');
      if (sexRadio) sexRadio.checked = true;
    }
    if (u.civil_status) {
      var civilSel = document.getElementById('e-civil');
      if (civilSel) civilSel.value = u.civil_status;
    }
    if (u.education) {
      var eduRadio = document.querySelector('input[name="e-edu"][value="' + u.education + '"]');
      if (eduRadio) eduRadio.checked = true;
    }
    if (u.employment) {
      var empSel = document.getElementById('e-emp-status');
      if (empSel) empSel.value = u.employment;
    }

    // ── Load regions FIRST, then cascade select ──────────────
    var regions = await fetchAddr('regions');
    fillSelect('e-s1-region', regions, 'Select region…', true);

    if (u.region) {
      var rSel = document.getElementById('e-s1-region');
      for (var i = 0; i < rSel.options.length; i++) {
        if (rSel.options[i].text.toLowerCase().includes(u.region.toLowerCase())) {
          rSel.selectedIndex = i; break;
        }
      }

      if (rSel.value && u.province) {
        var provinces = await fetchAddr('provinces', rSel.value);
        fillSelect('e-s1-province', provinces, 'Select province…', true);
        var pSel = document.getElementById('e-s1-province');
        for (var j = 0; j < pSel.options.length; j++) {
          if (pSel.options[j].text.toLowerCase().includes(u.province.toLowerCase())) {
            pSel.selectedIndex = j; break;
          }
        }

        if (pSel.value && u.city) {
          var cities = await fetchAddr('cities', pSel.value);
          fillSelect('e-s1-city', cities, 'Select city…', true);
          var cSel = document.getElementById('e-s1-city');
          for (var k = 0; k < cSel.options.length; k++) {
            if (cSel.options[k].text.toLowerCase().includes(u.city.toLowerCase())) {
              cSel.selectedIndex = k; break;
            }
          }

          if (cSel.value) {
            var barangays = await fetchAddr('barangays', cSel.value);
            fillSelect('e-s1-barangay', barangays, 'Select barangay…', true);

            // ── Auto-select barangay ──────────────────────────
            if (u.barangay) {
              var bSel = document.getElementById('e-s1-barangay');
              for (var m = 0; m < bSel.options.length; m++) {
                if (bSel.options[m].text.toLowerCase().includes(u.barangay.toLowerCase())) {
                  bSel.selectedIndex = m; break;
                }
              }
            }
          }
        }
      }
    }

    // Street
    if (u.address) {
      var streetEl = document.getElementById('e-s1-street');
      if (streetEl && !streetEl.value) {
        streetEl.value = u.address.split(',')[0].trim();
      }
    }

    buildS1Address();

  } catch(err) {
    console.error('Prefill error:', err);
  }
}
// Cascade-prefill birthplace selects from stored profile data.
// Called after loadStep2Regions() so the region <select> is already populated.
async function applyStep2Prefill() {
  if (!_profileData) return;
  var u = _profileData;

  // Guardian name (also set here in case Step 2 was reached before prefillEnrollmentForm finished)
  var nameEl = document.getElementById('e-parent-name');
  if (nameEl && !nameEl.value && u.guardian_name) nameEl.value = u.guardian_name;

  // Birthplace cascade — only apply if region select is still empty
  var bpReg = document.getElementById('e-bplace-region');
  if (!bpReg || bpReg.value || !u.birthplace_reg) return;

  for (var i = 0; i < bpReg.options.length; i++) {
    if (bpReg.options[i].text.toLowerCase().includes(u.birthplace_reg.toLowerCase())) {
      bpReg.selectedIndex = i; break;
    }
  }
  if (!bpReg.value || !u.birthplace_prov) return;

  var provs = await fetchAddr('provinces', bpReg.value);
  fillSelect('e-bplace-province', provs, 'Select province…', true);
  var bpProv = document.getElementById('e-bplace-province');
  for (var j = 0; j < bpProv.options.length; j++) {
    if (bpProv.options[j].text.toLowerCase().includes(u.birthplace_prov.toLowerCase())) {
      bpProv.selectedIndex = j; break;
    }
  }
  if (!bpProv.value || !u.birthplace_city) return;

  var cities = await fetchAddr('cities', bpProv.value);
  fillSelect('e-bplace-city', cities, 'Select city…', true);
  var bpCity = document.getElementById('e-bplace-city');
  for (var k = 0; k < bpCity.options.length; k++) {
    if (bpCity.options[k].text.toLowerCase().includes(u.birthplace_city.toLowerCase())) {
      bpCity.selectedIndex = k; break;
    }
  }
}

// Prefill classification checkboxes and PWD radio from stored profile data.
// Called when the user reaches Step 3.
function applyStep3Prefill() {
  if (!_profileData) return;
  var u = _profileData;

  // Classification checkboxes — only apply if none are checked yet
  if (u.learner_class && !document.querySelector('input[name="e-class"]:checked')) {
    var classes = u.learner_class.split(',').map(function(c) { return c.trim(); });
    classes.forEach(function(cls) {
      if (cls.indexOf('Others:') === 0) {
        var cb = document.querySelector('input[name="e-class"][value="Others"]');
        if (cb) { cb.checked = true; toggleOthersBox(true); }
        var txt = document.getElementById('e-class-others-text');
        if (txt) txt.value = cls.replace(/^Others:\s*/, '');
      } else {
        var cb = document.querySelector('input[name="e-class"][value="' + cls + '"]');
        if (cb) cb.checked = true;
      }
    });
  }

  // PWD radio — only apply if none is checked yet
  if (u.is_pwd !== null && u.is_pwd !== undefined &&
      !document.querySelector('input[name="e-pwd"]:checked')) {
    var pwdVal   = parseInt(u.is_pwd) === 1 ? 'Yes - PWD' : 'No';
    var pwdRadio = document.querySelector('input[name="e-pwd"][value="' + pwdVal + '"]');
    if (pwdRadio) pwdRadio.checked = true;
  }
}

async function renderWorkshopSelectGrid(preselectedId) {
    var grid = document.getElementById('workshop-select-grid');
    if (!grid) return;
    grid.innerHTML = '<p style="color:#aaa;padding:1rem">Loading workshops…</p>';

    try {
        var res = await fetch('get_available_workshops.php');
        var d   = await res.json();

        if (!d.success || !d.workshops.length) {
            grid.innerHTML = '<p style="color:#aaa;padding:1rem">No available workshops.</p>';
            return;
        }

        grid.innerHTML = d.workshops.map(function(w) {
            var slots = parseInt(w.max_slots) - parseInt(w.filled_slots || 0);
            var date  = w.first_session_date || 'TBD';
            // Auto-check if this matches preselectedId
            var checked = preselectedId && parseInt(w.id) === parseInt(preselectedId) ? 'checked' : '';
            return '<label class="ws-select-card' + (checked ? ' selected' : '') + '">' +
                '<input type="checkbox" name="e-workshop" value="' + w.id + '" ' +
                    'data-title="' + w.title + '" ' +
                    'data-date="' + date + '" ' +
                    'data-sessions="' + (w.session_count || 0) + '" ' +
                    checked + '>' +
                '<div class="ws-select-title">' + w.title + '</div>' +
                '<div class="ws-select-meta">📅 ' + date + ' · ' + (w.session_count || 0) + ' sessions · ' + slots + ' slots left</div>' +
            '</label>';
        }).join('');

    } catch(e) {
        grid.innerHTML = '<p style="color:#aaa;padding:1rem">Could not load workshops.</p>';
    }
}
function enrollGoStep(step, init) {
  if (!init && step > enrollStep) {
    var err = validateEnrollStep(enrollStep);
    if (err) { showEnrollError(enrollStep, err); return; }
    hideEnrollError(enrollStep);
    collectEnrollData(enrollStep);
  }

  enrollStep = step;

  [1,2,3,4,'success'].forEach(function(p) {
    var el = document.getElementById('enroll-page-' + p);
    if (el) el.style.display = 'none';
  });
  document.getElementById('enroll-page-' + step).style.display = '';

  [1,2,3,4].forEach(function(i) {
    var dot = document.getElementById('estep-' + i);
    if (!dot) return;
    dot.classList.remove('active','done');
    if (i < step) dot.classList.add('done');
    if (i === step) dot.classList.add('active');
  });

  if (step === 2) loadStep2Regions().then(function() { applyStep2Prefill(); });
  if (step === 3) applyStep3Prefill();
  if (step === 4) buildReview();
}

function collectEnrollData(step) {
  if (step === 1) {
    // Trigger buildS1Address to ensure hidden fields are current
    buildS1Address();

    enrollData.lastname    = document.getElementById('e-lastname')?.value.trim()    || '';
    enrollData.firstname   = document.getElementById('e-firstname')?.value.trim()   || '';
    enrollData.middlename  = document.getElementById('e-middlename')?.value.trim()  || '';
    enrollData.contact     = document.getElementById('e-contact')?.value.trim()     || '';
    enrollData.email       = document.getElementById('e-email')?.value.trim()       || '';
    enrollData.nationality = document.getElementById('e-nationality')?.value.trim() || '';

    // From hidden fields assembled by buildS1Address
    enrollData.address  = document.getElementById('e-address')?.value  || '';
    enrollData.city     = document.getElementById('e-city')?.value     || '';
    enrollData.province = document.getElementById('e-province')?.value || '';
    enrollData.region   = document.getElementById('e-region')?.value   || '';
    enrollData.barangay = document.getElementById('e-barangay')?.value || '';
    enrollData.street   = document.getElementById('e-s1-street')?.value.trim() || '';
  }

  if (step === 2) {
    enrollData.sex   = document.querySelector('input[name="e-sex"]:checked')?.value || '';
    enrollData.civil = document.getElementById('e-civil')?.value || '';
    enrollData.dob   = document.getElementById('e-dob')?.value   || '';

    var bpRegion   = document.getElementById('e-bplace-region');
    var bpProvince = document.getElementById('e-bplace-province');
    var bpCity     = document.getElementById('e-bplace-city');
    var bpBarangay = document.getElementById('e-bplace-barangay');
    enrollData.bplaceRegion   = bpRegion   ? bpRegion.options[bpRegion.selectedIndex]?.text     || '' : '';
    enrollData.bplaceProv     = bpProvince ? bpProvince.options[bpProvince.selectedIndex]?.text || '' : '';
    enrollData.bplaceCity     = bpCity     ? bpCity.options[bpCity.selectedIndex]?.text         || '' : '';
    enrollData.bplaceBarangay = bpBarangay ? bpBarangay.options[bpBarangay.selectedIndex]?.text || '' : '';
    enrollData.bplaceStreet   = document.getElementById('e-bplace-street')?.value.trim() || '';

    var paRegion   = document.getElementById('e-parent-region');
    var paProvince = document.getElementById('e-parent-province');
    var paCity     = document.getElementById('e-parent-city');
    var paBarangay = document.getElementById('e-parent-barangay');
    enrollData.parentRegion   = paRegion   ? paRegion.options[paRegion.selectedIndex]?.text     || '' : '';
    enrollData.parentProv     = paProvince ? paProvince.options[paProvince.selectedIndex]?.text || '' : '';
    enrollData.parentCity     = paCity     ? paCity.options[paCity.selectedIndex]?.text         || '' : '';
    enrollData.parentBarangay = paBarangay ? paBarangay.options[paBarangay.selectedIndex]?.text || '' : '';
    enrollData.parentStreet   = document.getElementById('e-parent-street')?.value.trim() || '';

    enrollData.education  = document.querySelector('input[name="e-edu"]:checked')?.value || '';
    enrollData.empStatus  = document.getElementById('e-emp-status')?.value || '';
    enrollData.empType    = document.getElementById('e-emp-type')?.value   || '';
    enrollData.parentName = document.getElementById('e-parent-name')?.value.trim() || '';
  }

  if (step === 3) {
    // ── Classification — multi-select with Others specify ──
    var classChecked = document.querySelectorAll('input[name="e-class"]:checked');
    var classifications = Array.from(classChecked).map(function(cb) {
      if (cb.value === 'Others') {
        var txt = document.getElementById('e-class-others-text');
        var specified = txt ? txt.value.trim() : '';
        return specified ? 'Others: ' + specified : 'Others';
      }
      return cb.value;
    });
    enrollData.classification = classifications.join(', ');

    enrollData.pwd = document.querySelector('input[name="e-pwd"]:checked')?.value || '';

var wsChecked = document.querySelectorAll('input[name="e-workshop"]:checked');
var workshopIds    = [];
var workshopTitles = [];
var workshopDates  = [];
wsChecked.forEach(function(cb) {
  workshopIds.push(cb.value);
  workshopTitles.push(cb.dataset.title);
  workshopDates.push(cb.dataset.date);
});
enrollData.workshopIds    = workshopIds;
enrollData.workshopTitles = workshopTitles.join(', ');
enrollData.workshopDate   = workshopDates.join(', ');
enrollData.workshopSess   = wsChecked.length > 0 ? wsChecked[0].dataset.sessions : '';
  }
}

function toggleOthersBox(checked) {
  var box = document.getElementById('e-class-others-box');
  if (box) box.style.display = checked ? 'block' : 'none';
  if (!checked) {
    var txt = document.getElementById('e-class-others-text');
    if (txt) txt.value = '';
  }
}

function validateEnrollStep(step) {
  if (step === 1) {
    if (!document.getElementById('e-lastname')?.value.trim())  return 'Last name is required.';
    if (!document.getElementById('e-firstname')?.value.trim()) return 'First name is required.';
    if (!document.getElementById('e-contact')?.value.trim())   return 'Contact number is required.';
    if (!document.getElementById('e-s1-region')?.value)        return 'Please select a region.';
    if (!document.getElementById('e-s1-province')?.value)      return 'Please select a province.';
    if (!document.getElementById('e-s1-city')?.value)          return 'Please select a city/municipality.';
    if (!document.getElementById('e-s1-barangay')?.value)      return 'Please select a barangay.';
    if (!document.getElementById('e-email')?.value.trim())     return 'Email is required.';
  }
  if (step === 2) {
    if (!document.querySelector('input[name="e-sex"]:checked'))  return 'Please select your sex.';
    if (!document.getElementById('e-civil')?.value)              return 'Please select your civil status.';
    if (!document.getElementById('e-dob')?.value)                return 'Date of birth is required.';
    if (!document.querySelector('input[name="e-edu"]:checked'))  return 'Please select your educational attainment.';
    if (!document.getElementById('e-emp-status')?.value)         return 'Please select your employment status.';
  }
  if (step === 3) {
if (!document.querySelector('input[name="e-class"]:checked')) return 'Please select at least one classification.';
    if (!document.querySelector('input[name="e-pwd"]:checked'))      return 'Please indicate PWD status.';
if (!document.querySelector('input[name="e-workshop"]:checked')) return 'Please select at least one workshop.';
  }
  return null;
}

function showEnrollError(step, msg) {
  var el = document.getElementById('e-err-' + step);
  if (el) { el.textContent = msg; el.style.display = 'block'; }
}

function hideEnrollError(step) {
  var el = document.getElementById('e-err-' + step);
  if (el) el.style.display = 'none';
}

function buildReview() {
  var addr = [enrollData.street, enrollData.barangay, enrollData.city, enrollData.province, enrollData.region]
    .filter(function(p) { return p; }).join(', ');

  var bplace = [enrollData.bplaceStreet, enrollData.bplaceBarangay, enrollData.bplaceCity, enrollData.bplaceProv, enrollData.bplaceRegion]
    .filter(function(p) { return p; }).join(', ');

  var parentAddr = [enrollData.parentStreet, enrollData.parentBarangay, enrollData.parentCity, enrollData.parentProv, enrollData.parentRegion]
    .filter(function(p) { return p; }).join(', ');

  document.getElementById('review-content').innerHTML =
    '<div class="review-block">' +
      '<p class="review-block-title">Step 1 — Personal information</p>' +
'<div class="review-row"><span>Full name</span><strong>' + 
  [enrollData.firstname, enrollData.middlename, enrollData.lastname]
  .filter(function(p) { return p; })
  .join(' ') + 
'</strong></div>' +
      '<div class="review-row"><span>Contact number</span><strong>' + (enrollData.contact||'—') + '</strong></div>' +
      '<div class="review-row"><span>Email</span><strong>' + (enrollData.email||'—') + '</strong></div>' +
      '<div class="review-row"><span>Address</span><strong>' + (addr||'—') + '</strong></div>' +
      '<div class="review-row"><span>Nationality</span><strong>' + (enrollData.nationality||'—') + '</strong></div>' +
    '</div>' +
    '<div class="review-block">' +
      '<p class="review-block-title">Step 2 — Personal background</p>' +
      '<div class="review-row"><span>Sex / Civil status</span><strong>' + (enrollData.sex||'—') + ' · ' + (enrollData.civil||'—') + '</strong></div>' +
      '<div class="review-row"><span>Date of birth</span><strong>' + (enrollData.dob||'—') + '</strong></div>' +
      '<div class="review-row"><span>Birthplace</span><strong>' + (bplace||'—') + '</strong></div>' +
      '<div class="review-row"><span>Education</span><strong>' + (enrollData.education||'—') + '</strong></div>' +
      '<div class="review-row"><span>Employment</span><strong>' + (enrollData.empStatus||'—') + (enrollData.empType ? ' · ' + enrollData.empType : '') + '</strong></div>' +
      '<div class="review-row"><span>Parent/Guardian</span><strong>' + (enrollData.parentName||'—') + '</strong></div>' +
      '<div class="review-row"><span>Parent Address</span><strong>' + (parentAddr||'—') + '</strong></div>' +
    '</div>' +
    '<div class="review-block">' +
      '<p class="review-block-title">Step 3 — Classification & workshop</p>' +
      '<div class="review-row"><span>Classification</span><strong>' + (enrollData.classification||'—') + '</strong></div>' +
      '<div class="review-row"><span>PWD</span><strong>' + (enrollData.pwd||'—') + '</strong></div>' +
      '<div class="review-row"><span>Workshop(s)</span><strong>' + (enrollData.workshopTitles||enrollData.workshopTitle||'—') + '</strong></div>' +
      '<div class="review-row"><span>Schedule</span><strong>' + (enrollData.workshopDate||'—') + ' · UGAT Demo Farm</strong></div>' +
    '</div>';

  document.getElementById('review-sms-box').innerHTML =
    '📱 SMS confirmation will be sent to <strong>' + (enrollData.contact||'') + '</strong> via Semaphore upon submission.';
}

async function submitEnrollment() {
  if (!document.getElementById('e-certify')?.checked) {
    showEnrollError(4, 'Please certify that all information is true and correct.');
    return;
  }
  hideEnrollError(4);

  // Disable button to prevent double submit
  var btn = document.querySelector('#enroll-page-4 .btn-primary');
  if (btn) { btn.disabled = true; btn.textContent = 'Submitting…'; }

  try {
    var res = await fetch('submit_enrollment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        workshop_ids:   enrollData.workshopIds,
        first_name:     enrollData.firstname,
        last_name:      enrollData.lastname,
        middle_name:    enrollData.middlename,
        contact:        enrollData.contact,
        email:          enrollData.email,
        address:        enrollData.address,
        city:           enrollData.city,
        province:       enrollData.province,
        region:         enrollData.region,
        barangay:       enrollData.barangay,
        nationality:    enrollData.nationality,
        sex:            enrollData.sex,
        civil_status:   enrollData.civil,
        birthdate:      enrollData.dob,
        birthplace:      enrollData.bplaceCity + ', ' + enrollData.bplaceProv,
        birthplace_city: enrollData.bplaceCity,
        birthplace_prov: enrollData.bplaceProv,
        birthplace_reg:  enrollData.bplaceRegion,
        education:      enrollData.education,
        employment:     enrollData.empStatus,
        classification: enrollData.classification,
        is_pwd:         enrollData.pwd === 'Yes - PWD' ? 1 : 0,
        guardian_name:  enrollData.parentName,
        guardian_addr:  [enrollData.parentStreet, enrollData.parentBarangay, enrollData.parentCity, enrollData.parentProv, enrollData.parentRegion].filter(function(p){return p;}).join(', '),
      })
    });

    var d = await res.json();

    if (!d.success) {
      showEnrollError(4, d.message || 'Submission failed. Please try again.');
      if (btn) { btn.disabled = false; btn.textContent = 'Submit Enrollment →'; }
      return;
    }

    // Show success screen
    [1,2,3,4].forEach(function(p) {
      var el = document.getElementById('enroll-page-' + p);
      if (el) el.style.display = 'none';
    });
    document.getElementById('enroll-page-success').style.display = '';

    document.getElementById('success-text').innerHTML =
      'Your enrollment in <strong>' + (enrollData.workshopTitles||'') + '</strong> has been submitted. UGAT staff will review and confirm your slot. You will receive an SMS notification once confirmed.';
    document.getElementById('success-sms').innerHTML =
      '📱 Confirmation will be sent to <strong>' + (enrollData.contact||'') + '</strong> via SMS.';

    [1,2,3,4].forEach(function(i) {
      var dot = document.getElementById('estep-' + i);
      if (dot) { dot.classList.remove('active'); dot.classList.add('done'); }
    });

  } catch(err) {
    showEnrollError(4, 'Could not connect to server. Please try again.');
    if (btn) { btn.disabled = false; btn.textContent = 'Submit Enrollment →'; }
  }
}

/* =================================================================
   MODAL / TOAST HELPERS
   ================================================================= */

function openModal(id) {
  var el = document.getElementById(id);
  if (el) { el.style.display = 'flex'; requestAnimationFrame(function() { el.classList.add('open'); }); }
}

function closeModal(id) {
  var el = document.getElementById(id);
  if (el) { el.classList.remove('open'); setTimeout(function() { el.style.display = 'none'; }, 200); }
}

function closeModalOutside(e, id) { if (e.target.id === id) closeModal(id); }

function filterWS(f, btn) {
  document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
  btn.classList.add('active');
  renderWorkshops();
}

function showToast(msg) {
  var t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(function() { t.classList.remove('show'); }, 3000);
}

function previewIDPhoto(input) {
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) {
      var img = document.getElementById('id-photo-preview');
      var ph  = document.getElementById('id-photo-placeholder');
      img.src = e.target.result;
      img.style.display = 'block';
      if (ph) ph.style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
async function tryEnroll(workshopId, btn) {
    var original = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Checking…';

    try {
        var res = await fetch('check_workshop_conflict.php?workshop_id=' + workshopId);
        var d   = await res.json();

        if (!d.success) {
            showToast('Could not check schedule. Please try again.');
            btn.disabled = false;
            btn.textContent = original;
            return;
        }

        if (d.conflict) {
            // Show the conflict inline below the button
            var card = btn.closest('.ws-list-item');
            var existing = card ? card.querySelector('.conflict-msg') : null;
            if (!existing && card) {
                var msg = document.createElement('p');
                msg.className = 'conflict-msg';
                msg.style.cssText = 'margin:0.5rem 0 0;font-size:var(--text-caption);color:#c0392b;font-weight:600;';
                msg.textContent = '⚠ ' + d.message;
                btn.parentNode.appendChild(msg);
            }
            btn.disabled = false;
            btn.textContent = original;
            return;
        }

        // No conflict — open enrollment modal
        openEnrollModal(workshopId);
        btn.disabled = false;
        btn.textContent = original;

    } catch(e) {
        showToast('Could not check schedule. Please try again.');
        btn.disabled = false;
        btn.textContent = original;
    }
}

async function renderBrowseWorkshops(q) {
    var list = document.getElementById('ws-list');
    list.innerHTML = '<p class="light-txt" style="padding:2rem 0;text-align:center">Loading workshops…</p>';

    try {
        var res  = await fetch('get_all_workshops.php?t=' + Date.now());
        var data = await res.json();

        if (!data.success || !data.workshops.length) {
            list.innerHTML = '<p class="light-txt" style="padding:2rem 0;text-align:center">No workshops available.</p>';
            return;
        }

        var filtered = data.workshops.filter(function(w) {
            return !q || w.title.toLowerCase().includes(q) || w.category.toLowerCase().includes(q);
        });

        if (!filtered.length) {
            list.innerHTML = '<p class="light-txt" style="padding:2rem 0;text-align:center">No workshops found.</p>';
            return;
        }

        var groups = { ongoing: 'Ongoing Workshops', upcoming: 'Upcoming Workshops' };
        var html = '';
        ['ongoing', 'upcoming'].forEach(function(status) {
            var items = filtered.filter(function(w) { return w.status === status; });
            if (!items.length) return;
            html += '<h3 class="section-title" style="margin-top:1rem">' + groups[status] + '</h3>';
            items.forEach(function(w) {

var btnHtml = '';
if (w.status === 'upcoming') {
    if (w.alreadyEnrolled) {
        btnHtml = '<button style="background:#e8f5e9;color:#4B8423;border:1.5px solid #4B8423;padding:0.4rem 1.1rem;border-radius:6px;font-size:var(--text-caption);font-weight:600;cursor:default" disabled>✓ Already Enrolled</button>';
    } else if (w.isPending) {
        btnHtml = '<button style="background:#fff8e1;color:#d6901e;border:1.5px solid #d6901e;padding:0.4rem 1.1rem;border-radius:6px;font-size:var(--text-caption);font-weight:600;cursor:default" disabled> Pending Approval</button>';
    } else {
        btnHtml = '<button style="background:#4B8423;color:#fff;padding:0.4rem 1.1rem;border-radius:6px;font-size:var(--text-caption);font-weight:600;border:none;cursor:pointer" onclick="tryEnroll(' + w.raw_id + ', this)">+ Enroll</button>';
    }
} else {
    btnHtml = '<span style="font-size:var(--text-caption);color:#999">Enrollment closed</span>';
}

                html +=
                    '<div class="ws-list-item">' +
                        '<img src="' + w.img + '" class="ws-list-img" alt="' + w.title + '" onerror="this.src=\'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400\'">' +
                        '<div class="ws-list-body">' +
                            '<div class="ws-list-top">' +
                                '<div>' +
                                    '<p class="ws-list-cat">' + w.category + '</p>' +
                                    '<h4 class="ws-list-title">' + w.title + '</h4>' +
                                '</div>' +
                                '<span class="badge ' + STATUS_MAP[w.status] + '">' + w.status.charAt(0).toUpperCase() + w.status.slice(1) + '</span>' +
                            '</div>' +
                            '<div class="ws-list-meta">' +
                                '<span>📅 ' + w.firstDate + '</span>' +
                                '<span>📍 ' + w.location + '</span>' +
                                '<span>👩‍🏫 Facilitator: ' + w.facilitator + '</span>' +
                                '<span>🪑 ' + w.slotsLeft + ' slots left · ' + w.sessionCount + ' sessions</span>' +
                            '</div>' +
                            '<div class="ws-list-footer" style="margin-top:0.75rem">' +
                                btnHtml +
                            '</div>' +
                        '</div>' +
                    '</div>';
            });
        });

        list.innerHTML = html;

    } catch(e) {
        list.innerHTML = '<p class="light-txt" style="padding:2rem 0;text-align:center">Could not load workshops.</p>';
    }
}
/* =================================================================
   FILTER
   ================================================================= */

var currentFilter = 'browse';

function showFilter(filter, btn) {
  currentFilter = filter || 'browse';

  // Update pill active styles
  document.querySelectorAll('.ws-filter-pill').forEach(function(b) {
    var isActive = b.dataset.filter === currentFilter;
    b.style.background = isActive ? '#4B8423' : '#fff';
    b.style.color      = isActive ? '#fff'    : '#555';
    b.style.border     = isActive ? '1.5px solid #4B8423' : '1.5px solid #e5e7eb';
  });

  var q    = (document.getElementById('ws-search')?.value || '').toLowerCase();
  var list = document.getElementById('ws-list');

if (currentFilter === 'browse') {
    renderBrowseWorkshops(q);
    return;
}

// My Enrolled = confirmed enrollments only (not pending)
if (currentFilter === 'enrolled') {
    var data = MY_WORKSHOPS.filter(function(w) {
        return w.enrollStatus === 'enrolled' &&
            (w.title.toLowerCase().includes(q) || w.category.toLowerCase().includes(q));
    });
    renderWorkshopList(data);
    return;
}

// My Pending = awaiting staff confirmation
if (currentFilter === 'pending') {
    var data = MY_WORKSHOPS.filter(function(w) {
        return w.enrollStatus === 'pending' &&
            (w.title.toLowerCase().includes(q) || w.category.toLowerCase().includes(q));
    });
    renderWorkshopList(data);
    return;
}
}

function renderWorkshopList(data) {
  var list = document.getElementById('ws-list');

  if (!data.length) {
    list.innerHTML = '<p class="light-txt" style="padding:2rem 0;text-align:center">No workshops found.</p>';
    return;
  }

  var groups = { ongoing:'Ongoing Workshops', upcoming:'Upcoming Workshops', completed:'Completed' };
  var html = '';
  ['ongoing','upcoming','completed'].forEach(function(status) {
    var items = data.filter(function(w) { return w.status === status; });
    if (!items.length) return;
    html += '<h3 class="section-title" style="margin-top:1rem">' + groups[status] + '</h3>';
    items.forEach(function(w) {
      var pct = Math.round((w.attended / w.total) * 100);
      var sessionChips = w.sessions.map(function(s) {
        return '<div class="session-chip ' + (s.done ? 'done' : s.current ? 'current' : 'upcoming') + '">' +
          '<span class="session-chip-label">' + s.label + '</span>' +
          '<span class="session-chip-date">' + s.date + '</span>' +
          '<span style="font-size:9px">' + (s.done ? '✓ Done' : s.current ? 'Next' : '—') + '</span>' +
        '</div>';
      }).join('');
      var certLabel = w.certStatus === 'issued' ? 'Cert. issued'
                    : w.certStatus === 'pending' ? 'Cert. pending' : 'Not started';
      html +=
        '<div class="ws-list-item">' +
          '<img src="' + w.img + '" class="ws-list-img" alt="' + w.title + '" onerror="this.src=\'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400\'">' +
          '<div class="ws-list-body">' +
            '<div class="ws-list-top">' +
              '<div>' +
                '<p class="ws-list-cat">' + w.category + '</p>' +
                '<h4 class="ws-list-title">' + w.title + '</h4>' +
              '</div>' +
              '<span class="badge ' + STATUS_MAP[w.status] + '">' + w.status.charAt(0).toUpperCase() + w.status.slice(1) + '</span>' +
            '</div>' +
            '<div class="ws-list-meta">' +
              '<span>📅 ' + w.dateRange + '</span>' +
              '<span>📍 ' + w.location + '</span>' +
              '<span>👩‍🏫 Facilitator: ' + w.facilitator + '</span>' +
            '</div>' +
            '<p style="font-size:var(--text-caption);font-weight:600;color:var(--color-text);margin-top:0.5rem">Sessions</p>' +
            '<div class="ws-sessions-row">' + sessionChips + '</div>' +
            '<div class="ws-list-footer">' +
              '<div class="progress-bar" style="width:120px"><div class="progress-fill" style="width:' + pct + '%"></div></div>' +
              '<span class="ws-progress-txt">' + w.attended + ' of ' + w.total + ' sessions done</span>' +
              '<span class="badge ' + CERT_MAP[w.certStatus] + '">' + certLabel + '</span>' +
            '</div>' +
          '</div>' +
        '</div>';
    });
  });
  list.innerHTML = html;
}

/* =================================================================
   INIT
   ================================================================= */
document.addEventListener('DOMContentLoaded', async function() {
  await loadMyWorkshops();
  var defaultBtn = document.querySelector('.ws-filter-pill[data-filter="browse"]');
  if (defaultBtn) showFilter('browse', defaultBtn);
});

async function loadMyWorkshops() {
  try {
    var res  = await fetch('get_my_workshops.php?t=' + Date.now());
    var data = await res.json();
    if (data.success && Array.isArray(data.workshops)) {
      MY_WORKSHOPS = data.workshops;
    } else {
      MY_WORKSHOPS = [];
    }
  } catch(e) {
    console.error('Failed to load workshops:', e);
    MY_WORKSHOPS = [];
  }
  updateKPIs();
}

function updateKPIs() {
    var total     = MY_WORKSHOPS.filter(function(w) { return w.enrollStatus === 'enrolled'; }).length;
    var completed = MY_WORKSHOPS.filter(function(w) { return w.status === 'completed'; }).length;

    // Upcoming = sessions not yet done across enrolled workshops only
    var upcoming = MY_WORKSHOPS.filter(function(w) {
        return w.enrollStatus === 'enrolled';
    }).reduce(function(sum, w) {
        return sum + (w.sessions || []).filter(function(s) { return !s.done; }).length;
    }, 0);

    var totalSessions    = MY_WORKSHOPS.reduce(function(s, w) { return s + (w.total || 0); }, 0);
    var attendedSessions = MY_WORKSHOPS.reduce(function(s, w) { return s + (w.attended || 0); }, 0);
    var rate = totalSessions > 0 ? Math.round((attendedSessions / totalSessions) * 100) : 0;

    document.getElementById('stat-total').textContent     = total     || '0';
    document.getElementById('stat-upcoming').textContent  = upcoming  || '0';
    document.getElementById('stat-rate').textContent      = rate + '%';
    document.getElementById('stat-completed').textContent = completed || '0';
}