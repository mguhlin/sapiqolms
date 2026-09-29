/* Sapiqo LMS — unobtrusive behaviors (CSP-safe: no inline handlers).
   Replaces inline on* attributes with delegated listeners so we can run a
   strict Content-Security-Policy (script-src 'self' 'nonce-…', no unsafe-inline). */
(function () {
  'use strict';

  // Confirm dialogs: <form data-confirm="…"> and <button/a data-confirm="…">.
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f.matches && f.matches('[data-confirm]') && !window.confirm(f.getAttribute('data-confirm'))) {
      e.preventDefault();
    }
  });

  document.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target.closest('[data-confirm],[data-print],[data-nav-toggle],[data-reply-toggle]') : null;
    if (!t) return;

    // Buttons/links with their own confirm (not inside a data-confirm form).
    if (t.hasAttribute('data-confirm') && t.tagName !== 'FORM' && !t.closest('form[data-confirm]')) {
      if (!window.confirm(t.getAttribute('data-confirm'))) { e.preventDefault(); return; }
    }
    // Print the page.
    if (t.hasAttribute('data-print')) { e.preventDefault(); window.print(); return; }
    // Toggle the mobile nav.
    if (t.hasAttribute('data-nav-toggle')) {
      var n = document.getElementById(t.getAttribute('data-nav-toggle') || 'topnav');
      if (n) { var open = n.classList.toggle('is-open'); t.setAttribute('aria-expanded', open ? 'true' : 'false'); }
      return;
    }
    // Forum: toggle the reply box for a post.
    if (t.hasAttribute('data-reply-toggle')) {
      var body = t.closest('.fp-body');
      var box = body && body.querySelector('.fp-reply');
      if (box) box.classList.toggle('open');
      return;
    }
  });

  // Auto-submit selects: <select data-autosubmit> submits its form on change.
  document.addEventListener('change', function (e) {
    var s = e.target;
    if (s.matches && s.matches('[data-autosubmit]') && s.form) s.form.submit();
  });

  // Select-all-on-focus for read-only "copy this" inputs: <input data-select-all>.
  document.addEventListener('focus', function (e) {
    if (e.target.matches && e.target.matches('[data-select-all]')) e.target.select();
  }, true);
})();
