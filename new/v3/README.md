# TheHools 2.0 — UI Prototype

Statyczny prototyp dashboardu TheHools 2.0.

## Pliki

- `index.html` — struktura strony
- `style.css` — cały wygląd i responsywność
- `app.js` — drobna interakcja: mobile menu, aktywny sidebar, licznik

## Jak odpalić

Otwórz `index.html` w przeglądarce.

## Uwagi

To jest prototyp statyczny. Docelowo można go przenieść do Laravela:

- HTML → `resources/views/start.blade.php`
- CSS → `resources/css/app.css` albo osobny plik
- dane statyczne → dane z kontrolera / modeli Eloquent
- akcje buttonów → route'y Laravel
