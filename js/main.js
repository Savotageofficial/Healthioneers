// Smooth page transitions + reveal on scroll
(function(){
  const overlay = document.getElementById('page-transition');

  // Smooth navigation
  document.querySelectorAll('a[data-link]').forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      const href = link.getAttribute('href');
      overlay.classList.add('show');
      setTimeout(() => { window.location.href = href; }, 600);
    });
  });

  window.addEventListener('load', () => {
    setTimeout(()=> overlay.classList.remove('show'), 100);
  });

  // Reveal on scroll
  const observer = new IntersectionObserver(entries=>{
    entries.forEach(entry=>{
      if(entry.isIntersecting){
        entry.target.classList.add('visible');
        observer.unobserve(entry.target);
      }
    });
  }, {threshold:0.15});

  document.querySelectorAll('.reveal').forEach(el=> observer.observe(el));
})();
