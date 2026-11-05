const openDrawerBtn = document.getElementById('openDrawer');
const closeDrawerBtn = document.getElementById('closeDrawer');
const drawer = document.getElementById('drawer');
const overlay = document.getElementById('overlay');

function openDrawer() {
    drawer.classList.add('show');
    overlay.classList.add('show');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

function closeDrawer() {
    drawer.classList.remove('show');
    overlay.classList.remove('show');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

if (openDrawerBtn) openDrawerBtn.addEventListener('click', openDrawer);
if (closeDrawerBtn) closeDrawerBtn.addEventListener('click', closeDrawer);
if (overlay) overlay.addEventListener('click', closeDrawer);

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeDrawer();
});

function formatTime(totalSeconds) {
    const sec = Math.max(0, Number(totalSeconds) || 0);
    const hours = Math.floor(sec / 3600);
    const minutes = Math.floor((sec % 3600) / 60);
    const seconds = sec % 60;
    if (hours > 0) {
        return [hours, minutes, seconds].map((v, i) => i === 0 ? String(v) : String(v).padStart(2, '0')).join(':');
    }
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

const timers = [...document.querySelectorAll('.timer[data-seconds]')];
timers.forEach(el => {
    let seconds = Number(el.dataset.seconds);
    el.textContent = formatTime(seconds);
    setInterval(() => {
        if (seconds > 0) seconds -= 1;
        el.textContent = formatTime(seconds);
    }, 1000);
});

const headerCaptain = document.getElementById('header-captain');
if (headerCaptain && headerCaptain.form && typeof headerCaptain.form.requestSubmit === 'function') {
    const captainForm = headerCaptain.form;
    const initialCaptain = headerCaptain.value;
    const fallbackButton = captainForm.querySelector('[data-captain-submit]');
    let switchingCaptain = false;
    let pendingCaptain = initialCaptain;

    captainForm.addEventListener('submit', (event) => {
        if (switchingCaptain || !headerCaptain.value || headerCaptain.value === initialCaptain) {
            event.preventDefault();
            return;
        }
        switchingCaptain = true;
        pendingCaptain = headerCaptain.value;
        captainForm.setAttribute('aria-busy', 'true');
        // Keep the select enabled so its value remains in the POST payload.
        headerCaptain.setAttribute('aria-disabled', 'true');
    });

    headerCaptain.addEventListener('change', () => {
        if (switchingCaptain) {
            headerCaptain.value = pendingCaptain;
            return;
        }
        if (headerCaptain.value && headerCaptain.value !== initialCaptain) {
            captainForm.requestSubmit();
        }
    });

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            switchingCaptain = false;
            headerCaptain.value = initialCaptain;
            captainForm.removeAttribute('aria-busy');
            headerCaptain.removeAttribute('aria-disabled');
        }
    });

    if (fallbackButton) fallbackButton.style.display = 'none';
}