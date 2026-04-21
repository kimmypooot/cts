/**
 * preloader.js — IMIS Login Preloader
 * Dismisses the #preloader overlay once the page has fully loaded.
 * Mirrors the dismissal logic used in the CSC RO VIII portal.
 */
(function () {
  var preloader = document.getElementById('preloader');
  if (!preloader) return;

  function dismiss() {
    /* Trigger CSS fade-out */
    preloader.classList.add('preloader--hidden');
    preloader.setAttribute('aria-busy', 'false');

    /* Remove from DOM after the CSS transition ends (~600ms) */
    preloader.addEventListener('transitionend', function handler(e) {
      if (e.target !== preloader) return;          /* ignore child transitions */
      preloader.removeEventListener('transitionend', handler);
      if (preloader.parentNode) {
        preloader.parentNode.removeChild(preloader);
      }
    });

    /* Fallback: force-remove after 1.2s if transitionend never fires */
    setTimeout(function () {
      if (preloader.parentNode) {
        preloader.parentNode.removeChild(preloader);
      }
    }, 1200);
  }

  if (document.readyState === 'complete') {
    /* Already loaded (cached page) — brief pause so the animation is visible */
    setTimeout(dismiss, 300);
  } else {
    window.addEventListener('load', function () {
      /* Small buffer so the progress bar animation completes visually */
      setTimeout(dismiss, 400);
    });
  }
}());