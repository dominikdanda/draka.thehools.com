(function () {
  const qs = (selector, scope = document) => scope.querySelector(selector);
  const qsa = (selector, scope = document) => [...scope.querySelectorAll(selector)];

  const toast = qs('#toast');
  let toastTimer;
  function showToast(message) {
    toast.textContent = message;
    toast.classList.add('is-visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('is-visible'), 2600);
  }

  qsa('.nav-link').forEach(link => {
    link.addEventListener('click', event => {
      qsa('.nav-link').forEach(item => item.classList.remove('is-active'));
      event.currentTarget.classList.add('is-active');
    });
  });

  qsa('.side-link').forEach(link => {
    link.addEventListener('click', event => {
      qsa('.side-link').forEach(item => item.classList.remove('is-active'));
      event.currentTarget.classList.add('is-active');
    });
  });

  const newsPanel = qs('.news');
  const toggleNews = qs('#toggleNews');
  const showAllNews = qs('#showAllNews');
  function toggleNewsList() {
    const open = newsPanel.classList.toggle('is-open');
    toggleNews.textContent = open ? 'Pokaż mniej' : 'Pokaż więcej';
    showToast(open ? 'Pokazano dodatkowe aktualności.' : 'Ukryto dodatkowe aktualności.');
  }
  toggleNews?.addEventListener('click', toggleNewsList);
  showAllNews?.addEventListener('click', toggleNewsList);

  const timer = qs('#eventTimer');
  let remaining = 2 * 60 * 60 + 45 * 60 + 18;
  function tickTimer() {
    remaining = Math.max(0, remaining - 1);
    const h = String(Math.floor(remaining / 3600)).padStart(2, '0');
    const m = String(Math.floor((remaining % 3600) / 60)).padStart(2, '0');
    const s = String(remaining % 60).padStart(2, '0');
    timer.textContent = `${h}:${m}:${s}`;
  }
  setInterval(tickTimer, 1000);

  const trainBtn = qs('#trainBtn');
  const energyValue = qs('#energyValue');
  const energyBar = qs('#energyBar');
  const energyCard = qs('.energy');
  trainBtn?.addEventListener('click', () => {
    const max = Number(energyCard.dataset.max || 2885);
    const current = Number(energyValue.textContent || max);
    const next = Math.min(max, current + 180);
    energyValue.textContent = next;
    energyBar.style.width = `${Math.round((next / max) * 100)}%`;
    trainBtn.animate([
      { transform: 'scale(1)' },
      { transform: 'scale(.98)' },
      { transform: 'scale(1)' }
    ], { duration: 260, easing: 'ease-out' });
    showToast(next === max ? 'Energia jest pełna.' : 'Odzyskano 180 punktów energii.');
  });

  qs('#attackBtn')?.addEventListener('click', () => {
    showToast('Przeciwnik został wybrany. To tylko akcja demonstracyjna w szablonie.');
  });
})();
