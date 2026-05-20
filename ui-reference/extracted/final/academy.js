/* ================================================================
   MAHARANI WEDDINGS ACADEMY — Shared front-end behaviour
   Loaded after every logged-in page. Vanilla JS, no dependencies.
   ================================================================ */
(function () {
  'use strict';

  // ---- Mobile drawer ----
  const drawer    = document.querySelector('[data-mw-drawer]');
  const backdrop  = document.querySelector('[data-mw-drawer-backdrop]');
  const openers   = document.querySelectorAll('[data-mw-drawer-open]');
  const closers   = document.querySelectorAll('[data-mw-drawer-close]');

  let lastFocused = null;

  function openDrawer() {
    if (!drawer) return;
    lastFocused = document.activeElement;
    drawer.classList.add('open');
    drawer.setAttribute('aria-hidden', 'false');
    if (backdrop) backdrop.classList.add('open');
    document.body.style.overflow = 'hidden';
    // Focus first link inside drawer
    const firstLink = drawer.querySelector('a, button');
    if (firstLink) firstLink.focus();
  }

  function closeDrawer() {
    if (!drawer) return;
    drawer.classList.remove('open');
    drawer.setAttribute('aria-hidden', 'true');
    if (backdrop) backdrop.classList.remove('open');
    document.body.style.overflow = '';
    if (lastFocused && lastFocused.focus) lastFocused.focus();
  }

  openers.forEach(b => b.addEventListener('click', openDrawer));
  closers.forEach(b => b.addEventListener('click', closeDrawer));
  if (backdrop) backdrop.addEventListener('click', closeDrawer);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && drawer && drawer.classList.contains('open')) {
      closeDrawer();
    }
  });

  // ---- Password show/hide toggles ----
  // Mark up: <button data-toggle-pwd="password-input-id">
  document.querySelectorAll('[data-toggle-pwd]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-toggle-pwd');
      const input = document.getElementById(id);
      if (!input) return;
      const showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      // Keep aria-label and pressed state in sync
      btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
      btn.setAttribute('aria-pressed', showing ? 'false' : 'true');
    });
  });

  // Initialise aria-pressed on toggles
  document.querySelectorAll('[data-toggle-pwd]').forEach((btn) => {
    if (!btn.hasAttribute('aria-pressed')) btn.setAttribute('aria-pressed', 'false');
  });

})();
