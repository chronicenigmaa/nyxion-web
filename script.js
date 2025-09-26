/* ============================
   Nyxion Labs — Optimized site script.js
   ============================ */

/* ---------- 0) Performance & Utilities ---------- */
function $(sel, root) { return (root || document).querySelector(sel); }
function $all(sel, root) { return Array.from((root || document).querySelectorAll(sel)); }

// Throttle function for scroll events
function throttle(func, limit) {
  let inThrottle;
  return function() {
    const args = arguments;
    const context = this;
    if (!inThrottle) {
      func.apply(context, args);
      inThrottle = true;
      setTimeout(() => inThrottle = false, limit);
    }
  }
}

// Debounce function for resize events
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

/* ---------- 1) Enhanced sticky header ---------- */
(function stickyHeader() {
  const header = $('.site-header');
  if (!header) return;

  const SCROLLED = 'is-stuck';
  let lastScroll = 0;
  
  const onScroll = throttle(() => {
    const currentScroll = window.scrollY;
    
    // Add stuck class when scrolled
    if (currentScroll > 20) {
      header.classList.add(SCROLLED);
    } else {
      header.classList.remove(SCROLLED);
    }
    
    // Optional: Hide header when scrolling down, show when scrolling up
    if (currentScroll > lastScroll && currentScroll > 100) {
      header.style.transform = 'translateY(-100%)';
    } else {
      header.style.transform = 'translateY(0)';
    }
    
    lastScroll = currentScroll;
  }, 16); // ~60fps
  
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });
})();

/* ---------- 2) Enhanced mobile navigation ---------- */
(function mobileNav() {
  const header = $('.site-header') || document.body;
  const nav = $('nav', header) || $('nav');
  if (!nav) return;

  const links = $('#navLinks', nav) || $('.nav-links', nav);
  let toggle = $('#navToggle', nav) || $('.nav-toggle', nav);
  
  // Create toggle if it doesn't exist
  if (!toggle) {
    toggle = document.createElement('button');
    toggle.id = 'navToggle';
    toggle.className = 'nav-toggle';
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-controls', 'navLinks');
    toggle.setAttribute('aria-label', 'Toggle navigation menu');
    toggle.innerHTML = '<span></span><span></span><span></span>';
    nav.insertBefore(toggle, links);
  }

  let backdrop = null;
  let isAnimating = false;
  
  const isMobile = () => window.matchMedia('(max-width: 980px)').matches;

  function createBackdrop() {
    if (backdrop) return backdrop;
    backdrop = document.createElement('div');
    backdrop.className = 'nav-backdrop';
    backdrop.addEventListener('click', closeMenu);
    document.body.appendChild(backdrop);
    return backdrop;
  }

  function openMenu() {
    if (!isMobile() || isAnimating) return;
    isAnimating = true;
    
    links.classList.add('open');
    toggle.classList.add('active');
    toggle.setAttribute('aria-expanded', 'true');
    document.documentElement.classList.add('nav-open');
    
    const bd = createBackdrop();
    requestAnimationFrame(() => {
      bd.classList.add('show');
      isAnimating = false;
    });
    
    // Focus management for accessibility
    const firstLink = $('a', links);
    if (firstLink) firstLink.focus();
  }

  function closeMenu() {
    if (isAnimating) return;
    isAnimating = true;
    
    links.classList.remove('open');
    toggle.classList.remove('active');
    toggle.setAttribute('aria-expanded', 'false');
    document.documentElement.classList.remove('nav-open');
    
    if (backdrop) {
      backdrop.classList.remove('show');
      setTimeout(() => { 
        if (backdrop) {
          backdrop.remove();
          backdrop = null;
        }
        isAnimating = false;
      }, 300);
    } else {
      isAnimating = false;
    }
    
    // Return focus to toggle
    toggle.focus();
  }

  function toggleMenu(e) { 
    e && e.preventDefault(); 
    links.classList.contains('open') ? closeMenu() : openMenu(); 
  }

  // Event listeners
  toggle.addEventListener('click', toggleMenu);
  $all('a', links).forEach(a => {
    a.addEventListener('click', (e) => {
      // Only close menu if clicking internal links
      if (a.getAttribute('href').startsWith('#') || 
          a.getAttribute('href').startsWith('/') ||
          a.getAttribute('href').includes(window.location.hostname)) {
        closeMenu();
      }
    });
  });

  // Keyboard navigation
  window.addEventListener('keydown', (e) => { 
    if (e.key === 'Escape' && links.classList.contains('open')) {
      closeMenu();
    }
  });

  // Close when leaving mobile breakpoint
  const mq = window.matchMedia('(max-width: 980px)');
  const onChange = (e) => { if (!e.matches) closeMenu(); };
  if (mq.addEventListener) mq.addEventListener('change', onChange); 
  else mq.addListener(onChange);
})();

/* ---------- 3) Enhanced contact form handling ---------- */
(function contactForm() {
  const forms = $all('form[action*="php"], form[action*="contact"], #contactForm, .contact-form, .quick-assessment-form');
  
  forms.forEach(form => {
    const btn = $('button[type="submit"]', form);
    const successUrl = $('input[name="redirect_success"]', form)?.value || 'thank-you.html';
    const errorUrl = $('input[name="redirect_error"]', form)?.value || 'error.html';

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      // Client-side validation
      const requiredFields = $all('[required]', form);
      let hasErrors = false;
      
      requiredFields.forEach(field => {
        field.classList.remove('error');
        if (!field.value.trim()) {
          field.classList.add('error');
          hasErrors = true;
        }
      });

      // Email validation
      const emailFields = $all('input[type="email"]', form);
      emailFields.forEach(field => {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (field.value && !emailRegex.test(field.value)) {
          field.classList.add('error');
          hasErrors = true;
        }
      });

      if (hasErrors) {
        const firstError = $('.error', form);
        if (firstError) {
          firstError.focus();
          firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return;
      }

      // Hide previous messages
      const okMsg = $('.form-success', form);
      const badMsg = $('.form-error', form);
      if (okMsg) okMsg.hidden = true;
      if (badMsg) badMsg.hidden = true;

      // UI feedback
      if (btn) { 
        btn.disabled = true; 
        btn.dataset.originalText = btn.textContent; 
        btn.textContent = 'Sending...';
        btn.classList.add('loading');
      }

      try {
        const formData = new FormData(form);
        
        // Add honeypot and CSRF protection
        formData.append('hp', ''); // honeypot field
        formData.append('timestamp', Date.now());
        formData.append('form_url', window.location.href);

        const response = await fetch(form.action || 'logic.php', {
          method: form.method || 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        });

        if (response.ok) {
          // Success - show message briefly then redirect
          if (okMsg) {
            okMsg.hidden = false;
            okMsg.textContent = 'Message sent successfully! Redirecting...';
          }
          
          setTimeout(() => {
            window.location.href = successUrl;
          }, 1500);
        } else {
          throw new Error('Server error');
        }
      } catch (error) {
        console.error('Form submission error:', error);
        
        if (badMsg) {
          badMsg.hidden = false;
          badMsg.innerHTML = 'Something went wrong. Please try again or email us directly at <a href="mailto:info@nyxionlabs.com">info@nyxionlabs.com</a>';
        }
        
        setTimeout(() => {
          if (badMsg) badMsg.hidden = true;
        }, 10000);
      } finally {
        if (btn) { 
          btn.disabled = false; 
          btn.textContent = btn.dataset.originalText || 'Send message';
          btn.classList.remove('loading');
        }
      }
    });

    // Real-time validation feedback
    const inputs = $all('input, textarea, select', form);
    inputs.forEach(input => {
      input.addEventListener('blur', () => {
        input.classList.remove('error');
        if (input.hasAttribute('required') && !input.value.trim()) {
          input.classList.add('error');
        }
        if (input.type === 'email' && input.value) {
          const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          if (!emailRegex.test(input.value)) {
            input.classList.add('error');
          }
        }
      });
    });
  });
})();

/* ---------- 4) Optimized reveal animations ---------- */
(function revealAnimations() {
  const targets = $all('.fade-up, .banner, .process-card, .case-card, .plan-card, .faq-item, .value-prop');
  
  if (!targets.length) return;

  // Respect user preferences
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (prefersReducedMotion) {
    targets.forEach(el => el.classList.add('in-view'));
    return;
  }

  // Use Intersection Observer for better performance
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting || entry.intersectionRatio > 0) {
          entry.target.classList.add('in-view');
          observer.unobserve(entry.target);
        }
      });
    }, { 
      rootMargin: '0px 0px -50px 0px', 
      threshold: [0, 0.1] 
    });

    targets.forEach(el => observer.observe(el));
  } else {
    // Fallback for older browsers
    targets.forEach(el => el.classList.add('in-view'));
  }
})();

/* ---------- 5) Enhanced Calendly integration ---------- */
(function calendlyIntegration() {
  const CALENDLY_URL = 'https://calendly.com/nyxionlabs/';
  
  function openCalendly(url) {
    const targetUrl = url || CALENDLY_URL;
    
    // Check if Calendly widget is loaded
    if (window.Calendly && typeof Calendly.initPopupWidget === 'function') {
      try {
        Calendly.initPopupWidget({ 
          url: targetUrl,
          utm: {
            utmSource: 'website',
            utmMedium: 'cta',
            utmCampaign: 'consultation'
          }
        });
        
        // Track the opening
        if (typeof gtag !== 'undefined') {
          gtag('event', 'calendly_open', {
            event_category: 'engagement',
            event_label: 'consultation_booking'
          });
        }
      } catch (error) {
        console.warn('Calendly widget failed, redirecting:', error);
        window.open(targetUrl, '_blank', 'noopener,noreferrer');
      }
    } else {
      // Fallback: open in new tab
      window.open(targetUrl, '_blank', 'noopener,noreferrer');
    }
  }

  function bindCalendlyLinks() {
    $all('[data-calendly], [href*="calendly.com"]').forEach(el => {
      if (el.__calendlyBound) return;
      el.__calendlyBound = true;
      
      el.addEventListener('click', (e) => {
        e.preventDefault();
        
        // Get URL from data attribute or href
        const url = el.getAttribute('data-calendly') || 
                   el.getAttribute('href') || 
                   CALENDLY_URL;
        
        openCalendly(url);
      });
    });
  }

  // Bind initial links
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindCalendlyLinks);
  } else {
    bindCalendlyLinks();
  }
  
  // Watch for dynamically added content
  if ('MutationObserver' in window) {
    const observer = new MutationObserver(bindCalendlyLinks);
    observer.observe(document.body, { childList: true, subtree: true });
  }

  // Listen for Calendly events
  window.addEventListener('message', (e) => {
    if (!e.data || typeof e.data.event !== 'string') return;
    
    const event = e.data.event;
    if (event.indexOf('calendly.') === 0) {
      console.log('Calendly event:', event);
      
      // Track important events
      if (typeof gtag !== 'undefined') {
        if (event === 'calendly.event_scheduled') {
          gtag('event', 'consultation_scheduled', {
            event_category: 'conversion',
            event_label: 'calendly_booking'
          });
        }
      }
    }
  });
})();

/* ---------- 6) Smooth scrolling for anchor links ---------- */
(function smoothScrolling() {
  $all('a[href^="#"]').forEach(link => {
    link.addEventListener('click', (e) => {
      const targetId = link.getAttribute('href').substring(1);
      if (!targetId) return;
      
      const target = $('#' + targetId);
      if (target) {
        e.preventDefault();
        
        const headerHeight = $('.site-header')?.offsetHeight || 0;
        const offset = target.offsetTop - headerHeight - 20;
        
        window.scrollTo({
          top: offset,
          behavior: 'smooth'
        });
        
        // Update URL without triggering scroll
        if (history.pushState) {
          history.pushState(null, null, '#' + targetId);
        }
      }
    });
  });
})();

/* ---------- 7) Package selection tracking ---------- */
(function packageTracking() {
  const urlParams = new URLSearchParams(window.location.search);
  const selectedPackage = urlParams.get('package');
  
  if (selectedPackage) {
    // Pre-fill forms with package info
    const packageInputs = $all('input[name="package"], select[name="package"]');
    packageInputs.forEach(input => {
      input.value = selectedPackage;
    });
    
    // Add package info to Calendly URLs
    $all('[data-calendly], [href*="calendly.com"]').forEach(el => {
      const currentUrl = el.getAttribute('data-calendly') || el.getAttribute('href');
      if (currentUrl && !currentUrl.includes('utm_content')) {
        const separator = currentUrl.includes('?') ? '&' : '?';
        const newUrl = currentUrl + separator + 'utm_content=' + encodeURIComponent(selectedPackage);
        
        if (el.hasAttribute('data-calendly')) {
          el.setAttribute('data-calendly', newUrl);
        } else {
          el.setAttribute('href', newUrl);
        }
      }
    });
    
    // Track package selection
    if (typeof gtag !== 'undefined') {
      gtag('event', 'package_interest', {
        event_category: 'engagement',
        event_label: selectedPackage
      });
    }
  }
})();

/* ---------- 8) Performance optimizations ---------- */
(function performanceOptimizations() {
  // Lazy load images
  if ('IntersectionObserver' in window) {
    const imageObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const img = entry.target;
          if (img.dataset.src) {
            img.src = img.dataset.src;
            img.removeAttribute('data-src');
          }
          imageObserver.unobserve(img);
        }
      });
    });

    $all('img[data-src]').forEach(img => imageObserver.observe(img));
  }

  // Preload critical resources
  function preloadResource(href, as, type) {
    const link = document.createElement('link');
    link.rel = 'preload';
    link.href = href;
    link.as = as;
    if (type) link.type = type;
    document.head.appendChild(link);
  }

  // Preload fonts
  if (!document.querySelector('link[rel="preload"][href*="fonts.googleapis.com"]')) {
    preloadResource('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Space+Grotesk:wght@600;700;800&display=swap', 'style');
  }
})();

/* ---------- 9) Error handling and monitoring ---------- */
(function errorHandling() {
  // Global error handler
  window.addEventListener('error', (e) => {
    console.error('Global error:', e.error);
    
    // Track errors if analytics is available
    if (typeof gtag !== 'undefined') {
      gtag('event', 'exception', {
        description: e.error?.message || 'Unknown error',
        fatal: false
      });
    }
  });

  // Unhandled promise rejection handler
  window.addEventListener('unhandledrejection', (e) => {
    console.error('Unhandled promise rejection:', e.reason);
    
    if (typeof gtag !== 'undefined') {
      gtag('event', 'exception', {
        description: 'Unhandled promise rejection: ' + (e.reason?.message || e.reason),
        fatal: false
      });
    }
  });
})();

/* ---------- 10) Accessibility enhancements ---------- */
(function accessibilityEnhancements() {
  // Skip to main content link
  const skipLink = document.createElement('a');
  skipLink.href = '#main';
  skipLink.textContent = 'Skip to main content';
  skipLink.className = 'skip-link';
  skipLink.style.cssText = `
    position: absolute;
    top: -40px;
    left: 6px;
    background: var(--primary);
    color: white;
    padding: 8px;
    text-decoration: none;
    border-radius: 4px;
    z-index: 1000;
    transition: top 0.3s;
  `;
  
  skipLink.addEventListener('focus', () => {
    skipLink.style.top = '6px';
  });
  
  skipLink.addEventListener('blur', () => {
    skipLink.style.top = '-40px';
  });
  
  document.body.insertBefore(skipLink, document.body.firstChild);

  // Add main landmark if it doesn't exist
  if (!$('main')) {
    const main = document.createElement('main');
    main.id = 'main';
    const content = $('.container') || document.body;
    content.parentNode.insertBefore(main, content);
    main.appendChild(content);
  }

  // Improve focus visibility
  const style = document.createElement('style');
  style.textContent = `
    .focus-visible {
      outline: 2px solid var(--primary) !important;
      outline-offset: 2px !important;
    }
    
    input.error, select.error, textarea.error {
      border-color: #ef4444 !important;
      box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
    }
    
    .btn.loading {
      opacity: 0.7;
      cursor: not-allowed;
    }
    
    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
      }
    }
  `;
  document.head.appendChild(style);
})();

/* ---------- 11) Initialize everything ---------- */
(function initialize() {
  // Set up any remaining functionality when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      console.log('Nyxion Labs website initialized');
    });
  } else {
    console.log('Nyxion Labs website initialized');
  }

  // Add resize handler for responsive adjustments
  const handleResize = debounce(() => {
    // Recalculate any layout-dependent features
    const vh = window.innerHeight * 0.01;
    document.documentElement.style.setProperty('--vh', `${vh}px`);
  }, 100);

  window.addEventListener('resize', handleResize);
  handleResize(); // Initial call
})();