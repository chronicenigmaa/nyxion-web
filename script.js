/* ============================
   Nyxion Labs – site script.js
   ============================ */

/* ---------- 0) Helpers ---------- */
function $(sel, root) { return (root || document).querySelector(sel); }
function $all(sel, root) { return Array.from((root || document).querySelectorAll(sel)); }

/* ---------- 1) Sticky header (shadow while scrolling) ---------- */
(function stickyHeader() {
  const header = $('.site-header');
  if (!header) return;

  const SCROLLED = 'is-stuck';
  const onScroll = () => {
    if (window.scrollY > 8) header.classList.add(SCROLLED);
    else header.classList.remove(SCROLLED);
  };
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });
})();

/* ---------- 2) Mobile nav (burger ⇄ close, backdrop, scroll lock) ---------- */
(function mobileNav() {
  const header = $('.site-header') || document.body;
  const nav = $('nav', header) || $('nav');
  if (!nav) return;

  const links = $('#navLinks', nav) || $('.nav-links', nav);
  if (!links) return;

  // Find or create the toggle button so every page works
  let toggle = $('#navToggle', nav) || $('#menuToggle', nav) || $('.nav-toggle', nav);
  if (!toggle) {
    toggle = document.createElement('button');
    toggle.id = 'navToggle';
    toggle.className = 'nav-toggle';
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Open menu');
    toggle.innerHTML = '<span></span><span></span><span></span>';
    nav.insertBefore(toggle, links);
  }

  let backdrop = null;
  const isMobile = () => window.matchMedia('(max-width: 980px)').matches;

  function ensureBackdrop() {
    if (backdrop) return backdrop;
    backdrop = document.createElement('div');
    backdrop.id = 'navBackdrop';
    backdrop.addEventListener('click', closeMenu);
    document.body.appendChild(backdrop);
    return backdrop;
  }

  function openMenu() {
    if (!isMobile()) return; // never open overlay on desktop
    links.classList.add('open');
    toggle.classList.add('active');
    toggle.setAttribute('aria-expanded', 'true');
    document.documentElement.classList.add('nav-open');
    const bd = ensureBackdrop();
    requestAnimationFrame(() => bd.classList.add('show'));
  }

  function closeMenu() {
    links.classList.remove('open');
    toggle.classList.remove('active');
    toggle.setAttribute('aria-expanded', 'false');
    document.documentElement.classList.remove('nav-open');
    if (backdrop) {
      backdrop.classList.remove('show');
      setTimeout(() => { backdrop && backdrop.remove(); backdrop = null; }, 160);
    }
  }

  function toggleMenu(e) { e && e.preventDefault(); links.classList.contains('open') ? closeMenu() : openMenu(); }

  // Wire events
  toggle.addEventListener('click', toggleMenu);
  $all('a', links).forEach(a => a.addEventListener('click', closeMenu));
  window.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeMenu(); });

  // Close when leaving mobile breakpoint
  const mq = window.matchMedia('(max-width: 980px)');
  const onChange = (e) => { if (!e.matches) closeMenu(); };
  if (mq.addEventListener) mq.addEventListener('change', onChange); else mq.addListener(onChange);
})();

/* ---------- 3) Calendly popup handler ---------- */
/* Replace this with your real event link */
const CALENDLY_FALLBACK_URL = 'https://calendly.com/your-handle/free-consultation-20min';

(function calendlyPopup() {
  function openCalendly(url) {
    const target = url || CALENDLY_FALLBACK_URL;
    if (window.Calendly && typeof Calendly.initPopupWidget === 'function') {
      Calendly.initPopupWidget({ url: target });
    } else {
      // Fallback: navigate directly
      window.location.href = target;
    }
  }

  function bind() {
    $all('[data-calendly]').forEach(el => {
      if (el.__calBound) return;
      el.__calBound = true;
      el.addEventListener('click', (e) => {
        e.preventDefault();
        openCalendly(el.getAttribute('data-calendly') || CALENDLY_FALLBACK_URL);
      });
    });
  }

  document.addEventListener('DOMContentLoaded', bind);
  // In case content is injected later:
  const mo = new MutationObserver(bind);
  mo.observe(document.documentElement, { childList: true, subtree: true });

  // Optional: simple event logging hook
  window.addEventListener('message', (e) => {
    if (!e.data || !e.data.event || String(e.data.event).indexOf('calendly.') !== 0) return;
    // Example: console.log('Calendly:', e.data.event);
    // Hook analytics here if needed.
  });
})();

/* ---------- 4) Contact form: submit via fetch, then redirect ---------- */
(function contactForm() {
  const form = $('#contactForm');
  if (!form) return;

  const btn = $('button[type="submit"]', form);
  const successUrl = $('input[name="redirect_success"]', form)?.value || 'thank-you.html';
  const errorUrl   = $('input[name="redirect_error"]', form)?.value || 'error.html';

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    // Inline messages (progressive enhancement)
    const okMsg  = $('.form-success', form);
    const badMsg = $('.form-error', form);
    if (okMsg)  okMsg.hidden  = true;
    if (badMsg) badMsg.hidden = true;

    // UI: sending state
    if (btn) { btn.disabled = true; btn.dataset.label = btn.textContent; btn.textContent = 'Sending…'; }

    try {
      const resp = await fetch(form.action || 'logic.php', {
        method: form.method || 'POST',
        body: new FormData(form)
      });
      // Redirect based on HTTP status
      window.location.href = resp.ok ? successUrl : errorUrl;
    } catch (err) {
      window.location.href = errorUrl;
    } finally {
      if (btn) { btn.disabled = false; btn.textContent = btn.dataset.label || 'Send message'; }
    }
  });
})();

/* ---------- 5) Small niceties ---------- */
// Smooth anchor offset is handled via CSS :target { scroll-margin-top } in styles.css
// ===== Reveal on scroll (Solutions / How We Work content) =====
(function revealOnScroll(){
  const targets = document.querySelectorAll('.fade-up, .banner, .process-card, .reveal');

  // If nothing matches, nothing to do
  if (!targets.length) return;

  // No IntersectionObserver? Just show everything.
  if (!('IntersectionObserver' in window)) {
    targets.forEach(el => el.classList.add('in-view'));
    return;
  }

  const io = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting || entry.intersectionRatio > 0) {
        entry.target.classList.add('in-view');
        io.unobserve(entry.target);
      }
    });
  }, { rootMargin: '0px 0px -10% 0px', threshold: 0.1 });

  targets.forEach(el => io.observe(el));
})();

