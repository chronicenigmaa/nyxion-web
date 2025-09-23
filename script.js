// === Nyxion Labs — Mobile and animation helpers (2025-09-23) ===
(function(){
  // Mobile nav toggle
  const toggle = document.getElementById('navToggle');
  const links = document.getElementById('navLinks');
  if (toggle && links) {
    toggle.addEventListener('click', () => {
      const isOpen = links.classList.toggle('open');
      toggle.setAttribute('aria-expanded', String(isOpen));
    });
    // Close menu on link tap
    links.querySelectorAll('a').forEach(a => {
      a.addEventListener('click', () => {
        links.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  // Split words for reveal
  document.querySelectorAll('.reveal-words').forEach(el => {
    if (el.dataset.enhanced) return;
    const words = el.textContent.trim().split(/\s+/).map((w,i)=>`<span class="word" style="--i:${i}">${w}&nbsp;</span>`).join('');
    el.innerHTML = words;
    el.dataset.enhanced = '1';
  });

  // Intersection Observer for animations
  const io = new IntersectionObserver((entries)=>{
    entries.forEach(e=>{
      if(e.isIntersecting){
        e.target.classList.add('animate');
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.15 });

  document.querySelectorAll('.fade-up, .banner, .process-card, .reveal-words').forEach(el=>io.observe(el));

  // iOS video: ensure playsinline is respected
  document.querySelectorAll('video[autoplay]').forEach(v=>{
    v.setAttribute('muted','');
    v.setAttribute('playsinline','');
  });
})();
(function () {
  const toggle = document.getElementById('navToggle');
  const links  = document.getElementById('navLinks');
  if (!toggle || !links) return;
  toggle.addEventListener('click', () => {
    const open = links.classList.toggle('open');
    toggle.setAttribute('aria-expanded', String(open));
  });
  // Close menu after tapping a link
  links.querySelectorAll('a').forEach(a => {
    a.addEventListener('click', () => {
      links.classList.remove('open');
      toggle.setAttribute('aria-expanded', 'false');
    });
  });
})();

// 2) iOS: enforce inline video so it doesn’t push layout
document.querySelectorAll('video[autoplay]').forEach(v => {
  v.muted = true;
  v.setAttribute('playsinline', '');
});
