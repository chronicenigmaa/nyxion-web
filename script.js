// -------------------------
// Mobile navigation toggle
// -------------------------
const navToggle = document.getElementById('navToggle');
const navLinks = document.getElementById('navLinks');

if (navToggle && navLinks) {
  navToggle.addEventListener('click', () => {
    const open = navLinks.classList.toggle('open');
    navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
}

// -------------------------
// Scroll animations
// -------------------------
const io = new IntersectionObserver(
  (entries) => {
    entries.forEach((e) => {
      if (e.isIntersecting) e.target.classList.add('animate');
    });
  },
  { threshold: 0.2 }
);

document.querySelectorAll('.fade-up, .banner').forEach((el) => io.observe(el));

// -------------------------
// Word reveal animation (fixes spacing issue)
// -------------------------
function setupWordReveal(el) {
  const original = el.textContent.trim();
  el.textContent = "";

  // Split words but keep spaces
  const parts = original.split(/(\s+)/);

  parts.forEach((part, i) => {
    if (/\s+/.test(part)) {
      // Preserve actual spaces
      el.appendChild(document.createTextNode(part));
    } else if (part.length) {
      const span = document.createElement("span");
      span.className = "word";
      span.style.setProperty("--i", i);
      span.textContent = part;
      el.appendChild(span);
    }
  });
}

document.querySelectorAll(".reveal-words").forEach(setupWordReveal);

const ioWords = new IntersectionObserver(
  (entries) => {
    entries.forEach((e) => {
      if (e.isIntersecting) e.target.classList.add("animate");
    });
  },
  { threshold: 0.4 }
);

document.querySelectorAll(".reveal-words").forEach((el) =>
  ioWords.observe(el)
);

// -------------------------
// Header shadow on scroll
// -------------------------
const header = document.getElementById('site-header');
if (header) {
  window.addEventListener('scroll', () => {
    if (window.scrollY > 4) {
      header.style.boxShadow = 'var(--shadow)';
      header.style.background = 'rgba(255,255,255,.96)';
    } else {
      header.style.boxShadow = 'none';
      header.style.background = 'rgba(255,255,255,.9)';
    }
  });
}
