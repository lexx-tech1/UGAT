/**
 * Landing.js — UGAT TrainTrack
 * Script for Landing.html (public-facing landing page).
 *
 * Responsibilities:
 *  - Mark the correct nav link as "active" based on current page filename
 *  - Sticky nav shadow on scroll
 *  - Redirect to Login when CTA buttons are clicked
 *  - Toast utility
 */


/* =============================================================================
   NAVIGATION — AUTO-ACTIVE LINK
   Reads the current filename from window.location and marks the matching
   nav link as active. This way all 5 landing pages share the same nav
   markup and the active state is set automatically.
   ============================================================================= */

(function markActiveNavLink() {
  // Get just the filename portion (e.g. "LandingAbout.html")
  const currentFile = window.location.pathname.split('/').pop() || 'Landing.html';

  // Map each filename to the href it corresponds to
  const pageMap = {
    'Landing.html':         'Landing.html',
    'LandingAbout.html':    'LandingAbout.html',
    'LandingFeatures.html': 'LandingFeatures.html',
    'LandingProducts.html': 'LandingProducts.html',
    'LandingContact.html':  'LandingContact.html',
  };

  document.querySelectorAll('.landing-nav .nav-link').forEach(link => {
    const href = link.getAttribute('href');
    link.classList.remove('active');
    if (href === currentFile) {
      link.classList.add('active');
    }
  });
})();


/* =============================================================================
   STICKY NAV — add shadow on scroll
   ============================================================================= */

window.addEventListener('scroll', function () {
  const nav = document.getElementById('landing-nav');
  if (!nav) return;
  if (window.scrollY > 10) {
    nav.style.boxShadow = '0 2px 16px rgba(0,0,0,0.10)';
  } else {
    nav.style.boxShadow = '0 1px 8px rgba(0,0,0,0.06)';
  }
});


/* =============================================================================
   CTA ROUTING
   ============================================================================= */

/**
 * Navigate to the Login page.
 * Called by "Create Account" and "Get Started" buttons.
 */
function goToLogin() {
  window.location.href = '../auth/Login.html';
}


/* =============================================================================
   TOAST NOTIFICATION
   ============================================================================= */

/**
 * Show a temporary toast message.
 * @param {string} message
 */
function showToast(message) {
  const toast = document.getElementById('toast');
  if (!toast) return;
  toast.textContent = message;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 3000);
}