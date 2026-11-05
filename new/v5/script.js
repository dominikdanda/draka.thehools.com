const $ = (selector, scope = document) => scope.querySelector(selector);
const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];

function setActive(buttons, clicked) {
  buttons.forEach((button) => button.classList.remove('is-active'));
  clicked.classList.add('is-active');
}

$$('.topnav__item').forEach((button) => {
  button.addEventListener('click', () => setActive($$('.topnav__item'), button));
});

$$('.sidenav__item').forEach((button) => {
  button.addEventListener('click', () => setActive($$('.sidenav__item'), button));
});

$$('.action-card, .outline-btn, .news-item').forEach((button) => {
  button.addEventListener('pointerdown', () => button.style.transform = 'translateY(1px)');
  button.addEventListener('pointerup', () => button.style.transform = '');
  button.addEventListener('pointerleave', () => button.style.transform = '');
});

function formatTime(seconds) {
  const h = Math.floor(seconds / 3600).toString().padStart(2, '0');
  const m = Math.floor((seconds % 3600) / 60).toString().padStart(2, '0');
  const s = Math.floor(seconds % 60).toString().padStart(2, '0');
  return `${h}:${m}:${s}`;
}

const timer = $('.timer[data-countdown]');
if (timer) {
  let remaining = Number(timer.dataset.countdown || 0);
  timer.textContent = formatTime(remaining);

  setInterval(() => {
    remaining = Math.max(0, remaining - 1);
    timer.textContent = formatTime(remaining);
  }, 1000);
}

const showMore = $('.show-more');
if (showMore) {
  showMore.addEventListener('click', () => {
    showMore.textContent = showMore.textContent.includes('więcej') ? 'Schowaj ⌃' : 'Pokaż więcej⌄';
    $('.news-list')?.classList.toggle('is-expanded');
  });
}
