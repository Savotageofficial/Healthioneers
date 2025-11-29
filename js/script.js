const transition_el = document.querySelector('.transition');
const anchors = document.querySelectorAll('a');

window.onload = () => {
  transition_el.classList.remove('active');
};

for (let i = 0; i < anchors.length; i++) {
  const anchor = anchors[i];

  anchor.addEventListener('click', e => {
    const target = anchor.href;

    if (target.includes(window.location.origin)) {
      e.preventDefault();
      transition_el.classList.add('active');

      setTimeout(() => {
        window.location.href = target;
      }, 500);
    }
  });
}
