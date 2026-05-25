/**
 * Login.js — UGAT TrainTrack
 * Handles Login, Register (with PH address autocomplete), tab switching,
 * password toggle, toast notifications, and keyboard shortcuts.
 */


/* =============================================================================
   TAB SWITCHING
   ============================================================================= */
function switchAuthTab(tab) {
  const loginForm    = document.getElementById('auth-login-form');
  const registerForm = document.getElementById('auth-register-form');
  const tabLoginBtn  = document.getElementById('tab-login');
  const tabRegBtn    = document.getElementById('tab-register');
  if (!loginForm || !registerForm) return;

  if (tab === 'login') {
    loginForm.style.display    = 'block';
    registerForm.style.display = 'none';
    tabLoginBtn.classList.add('active');
    tabRegBtn.classList.remove('active');
    clearRegisterErrors();
  } else {
    loginForm.style.display    = 'none';
    registerForm.style.display = 'block';
    tabLoginBtn.classList.remove('active');
    tabRegBtn.classList.add('active');
    const loginErr = document.getElementById('login-error');
    if (loginErr) loginErr.style.display = 'none';
  }
}


/* =============================================================================
   LOGIN
   ============================================================================= */
async function doLogin() {
  const emailInput = document.getElementById('login-email');
  const pwdInput   = document.getElementById('login-password');
  const errorEl    = document.getElementById('login-error');
  const loginBtn   = document.querySelector('#auth-login-form .btn-primary');

  if (!emailInput || !pwdInput || !errorEl) return;

  const email    = emailInput.value.trim().toLowerCase();
  const password = pwdInput.value;

  if (!email || !password) {
    errorEl.textContent   = 'Please enter your email and password.';
    errorEl.style.display = 'block';
    return;
  }

  if (loginBtn) { loginBtn.disabled = true; loginBtn.textContent = 'Logging in…'; }

  try {
    const formData = new FormData();
    formData.append('email',    email);
    formData.append('password', password);

    const response = await fetch('login.php', { method: 'POST', body: formData });
    const data     = await response.json();

    if (data.success) {
      errorEl.style.display = 'none';
      showToast('Welcome back! Redirecting…');
      setTimeout(() => { window.location.href = data.redirect; }, 900);
    } else {
      errorEl.textContent   = data.message || 'Incorrect email or password.';
      errorEl.style.display = 'block';
      pwdInput.classList.add('input-shake');
      pwdInput.addEventListener('animationend', () => pwdInput.classList.remove('input-shake'), { once: true });
    }
  } catch (err) {
    errorEl.textContent   = 'Could not connect to server. Please try again.';
    errorEl.style.display = 'block';
  } finally {
    if (loginBtn) { loginBtn.disabled = false; loginBtn.textContent = 'Login'; }
  }
}


/* =============================================================================
   REGISTRATION
   ============================================================================= */
async function doRegister() {
  // ── Name fields (split into three) ──────────────────────────────────────────
  const firstName  = document.getElementById('reg-firstname')?.value.trim()  || '';
  const lastName   = document.getElementById('reg-lastname')?.value.trim()   || '';
  const middleName = document.getElementById('reg-middlename')?.value.trim() || ''; // optional

  // Build full name: First [Middle] Last  → e.g. "Liam Rafael Gariando Rait"
  const fullName = [firstName, middleName, lastName].filter(Boolean).join(' ');

  // ── Other fields ─────────────────────────────────────────────────────────────
  const email    = document.getElementById('reg-email')?.value.trim()   || '';
  const contact  = document.getElementById('reg-contact')?.value.trim() || '';
  const password = document.getElementById('reg-password')?.value       || '';
  const confirm  = document.getElementById('reg-confirm')?.value        || '';

  // Address fields from dropdowns + optional street
  const region      = document.getElementById('reg-region')?.value.trim()    || '';
  const province    = document.getElementById('reg-province')?.value.trim()  || '';
  const city        = document.getElementById('reg-city')?.value.trim()      || '';
  const barangay    = document.getElementById('reg-barangay')?.value.trim()  || '';
  const addressText = document.getElementById('reg-address')?.value.trim()   || '';

  // Compose full address string: Street, Barangay, City, Province, Region
  const fullAddress = [addressText, barangay, city, province, region].filter(Boolean).join(', ');

  clearRegisterErrors();
  let hasError = false;

  // ── Validation ───────────────────────────────────────────────────────────────
  if (!firstName) { showFieldError('reg-firstname', 'First name is required.');  hasError = true; }
  if (!lastName)  { showFieldError('reg-lastname',  'Last name is required.');   hasError = true; }
  // middleName is intentionally optional — no validation needed

  if (!region)   { showFieldError('reg-region',   'Please select your region.');            hasError = true; }
  if (!province) { showFieldError('reg-province', 'Please select your province.');          hasError = true; }
  if (!city)     { showFieldError('reg-city',     'Please select your city/municipality.'); hasError = true; }

  if (!email) {
    showFieldError('reg-email', 'Email address is required.'); hasError = true;
  } else if (!isValidEmail(email)) {
    showFieldError('reg-email', 'Please enter a valid email address.'); hasError = true;
  }

  if (!contact)  { showFieldError('reg-contact',  'Contact number is required.');                   hasError = true; }
  if (!password) { showFieldError('reg-password', 'Password is required.');                          hasError = true; }
  else if (password.length < 8) { showFieldError('reg-password', 'Password must be at least 8 characters.'); hasError = true; }
  if (!confirm)  { showFieldError('reg-confirm',  'Please confirm your password.');                  hasError = true; }
  else if (confirm !== password) { showFieldError('reg-confirm', 'Passwords do not match.');         hasError = true; }

  if (hasError) return;

  // ── Submit ───────────────────────────────────────────────────────────────────
  const regBtn = document.querySelector('#auth-register-form .btn-primary');
  if (regBtn) { regBtn.disabled = true; regBtn.textContent = 'Registering…'; }

  try {
    const formData = new FormData();
    formData.append('first_name',       firstName);
    formData.append('middle_name',      middleName);
    formData.append('last_name',        lastName);
    formData.append('full_name',        fullName);       // convenience: "First Middle Last"
    formData.append('address',          fullAddress);
    formData.append('barangay',         barangay);
    formData.append('city',             city);
    formData.append('province',         province);
    formData.append('region',           region);
    formData.append('email',            email);
    formData.append('phone',            contact);
    formData.append('password',         password);
    formData.append('confirm_password', confirm);

    const response = await fetch('register.php', { method: 'POST', body: formData });
    const data     = await response.json();

    if (data.success) {
      showToast('Account created! Please log in.');
      switchAuthTab('login');
      const loginEmail = document.getElementById('login-email');
      if (loginEmail) loginEmail.value = email;
    } else {
      if (data.errors && Array.isArray(data.errors)) showToast(data.errors[0]);
      else showToast(data.message || 'Registration failed. Please try again.');
    }
  } catch (err) {
    showToast('Could not connect to server. Please try again.');
  } finally {
    if (regBtn) { regBtn.disabled = false; regBtn.textContent = 'Register'; }
  }
}


/* =============================================================================
   PHILIPPINE ADDRESS AUTOCOMPLETE (kept for street/house-no field)
   ============================================================================= */

let addressSuggestions = [];
let suggestionTimeout  = null;

function initAddressAutocomplete() {
  const input     = document.getElementById('reg-address');
  const dropdown  = document.getElementById('address-suggestions');
  if (!input || !dropdown) return;

  input.addEventListener('input', function () {
    const query = this.value.trim();

    // Clear hidden structured fields when user types manually
    setHidden('reg-city',     '');
    setHidden('reg-province', '');
    setHidden('reg-region',   '');

    clearTimeout(suggestionTimeout);

    if (query.length < 3) { dropdown.style.display = 'none'; return; }

    suggestionTimeout = setTimeout(() => fetchAddressSuggestions(query), 400);
  });

  // Hide dropdown when clicking outside
  document.addEventListener('click', function (e) {
    if (!input.contains(e.target) && !dropdown.contains(e.target)) {
      dropdown.style.display = 'none';
    }
  });
}

async function fetchAddressSuggestions(query) {
  const dropdown = document.getElementById('address-suggestions');
  if (!dropdown) return;

  try {
    // Nominatim OSM — free, no API key, Philippines only
    const url = `https://nominatim.openstreetmap.org/search?` +
      `q=${encodeURIComponent(query + ', Philippines')}` +
      `&countrycodes=ph&format=json&addressdetails=1&limit=6`;

    const response = await fetch(url, {
      headers: { 'Accept-Language': 'en' }
    });
    const results = await response.json();

    if (!results.length) { dropdown.style.display = 'none'; return; }

    dropdown.innerHTML = '';
    results.forEach(place => {
      const addr     = place.address || {};
      const city     = addr.city || addr.municipality || addr.town || addr.village || addr.county || '';
      const province = addr.province || addr.state_district || addr.county || '';
      const region   = addr.state || '';
      const display  = place.display_name;

      const item = document.createElement('div');
      item.className   = 'address-suggestion-item';
      item.textContent = display;
      item.addEventListener('click', function () {
        document.getElementById('reg-address').value = display;
        setHidden('reg-city',     city);
        setHidden('reg-province', province);
        setHidden('reg-region',   region);
        dropdown.style.display = 'none';
      });
      dropdown.appendChild(item);
    });

    dropdown.style.display = 'block';

  } catch (err) {
    console.error('Address fetch error:', err);
    dropdown.style.display = 'none';
  }
}

function setHidden(id, value) {
  const el = document.getElementById(id);
  if (el) el.value = value;
}


/* =============================================================================
   REGISTER FORM HELPERS
   ============================================================================= */
function showFieldError(inputId, message) {
  const input = document.getElementById(inputId);
  if (!input) return;
  input.style.borderColor = 'var(--color-danger)';
  const parent = input.closest('.form-group') || input.parentElement;
  if (!parent.querySelector('.field-error')) {
    const errSpan = document.createElement('span');
    errSpan.className   = 'field-error';
    errSpan.textContent = message;
    errSpan.style.cssText = 'display:block;font-size:0.72rem;color:var(--color-danger);margin-top:0.3rem;padding-left:0.25rem;';
    parent.appendChild(errSpan);
  }
}

function clearRegisterErrors() {
  const registerForm = document.getElementById('auth-register-form');
  if (!registerForm) return;
  registerForm.querySelectorAll('.field-error').forEach(el => el.remove());
  registerForm.querySelectorAll('.form-input').forEach(input => { input.style.borderColor = ''; });
}

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}


/* =============================================================================
   PASSWORD TOGGLE
   ============================================================================= */
function togglePwd(inputId) {
  const input = document.getElementById(inputId);
  if (!input) return;
  const isHidden = input.type === 'password';
  input.type = isHidden ? 'text' : 'password';
  const icon = input.parentElement?.querySelector('.input-icon');
  if (icon) icon.textContent = isHidden ? '🙈' : '👁';
}


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


/* =============================================================================
   KEYBOARD SHORTCUTS
   ============================================================================= */
document.addEventListener('keydown', function (e) {
  if (e.key !== 'Enter') return;
  const loginForm = document.getElementById('auth-login-form');
  if (loginForm && loginForm.style.display !== 'none') { doLogin(); return; }
  const registerForm = document.getElementById('auth-register-form');
  if (registerForm && registerForm.style.display !== 'none') doRegister();
});


/* =============================================================================
   INIT
   ============================================================================= */
document.addEventListener('DOMContentLoaded', function () {
  // Clear field errors on typing
  const registerForm = document.getElementById('auth-register-form');
  if (registerForm) {
    registerForm.querySelectorAll('.form-input').forEach(input => {
      input.addEventListener('input', function () {
        this.style.borderColor = '';
        const parent  = this.closest('.form-group') || this.parentElement;
        const errSpan = parent?.querySelector('.field-error');
        if (errSpan) errSpan.remove();
      });
    });
  }

  const loginEmail = document.getElementById('login-email');
  const loginPwd   = document.getElementById('login-password');
  const loginErr   = document.getElementById('login-error');
  [loginEmail, loginPwd].forEach(input => {
    input?.addEventListener('input', () => { if (loginErr) loginErr.style.display = 'none'; });
  });

  // Init address autocomplete
  initAddressAutocomplete();
});