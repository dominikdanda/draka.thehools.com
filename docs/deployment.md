# Bezpieczne CI, auto-merge i wdrożenia (Issue #3)

Status: mechanizm przygotowany do przeglądu. Nie uruchamiać wdrożeń ani auto-merge tego pierwszego
PR. Brak zmiennej repozytorium `DRAKA_DEPLOY_ENABLED=true` wyłącza wdrożenia. Nie ustawiono jej
w ramach tej zmiany. Nie zmieniono VPS, bazy, danych graczy ani sekretów.

## Przepływ i granice zaufania

Codex tworzy osobną gałąź, test regresyjny, implementację, poprawia błędy, wykonuje pełne kontrole
i otwiera PR do `main`. W PR opisuje ryzyko i wyniki. Zwykłe zmiany mogą korzystać z natywnego
GitHub auto-merge, po jednorazowym zatwierdzeniu mechanizmu przez właściciela. Nie używamy bota
wywołującego bezpośredni merge, PAT w workflow ani `pull_request_target`.

CODEOWNERS przyjmuje konserwatywnie, że kod wymaga zgody właściciela. Wyjątek to zwykła dokumentacja
i CSS; dokumentacja operacyjna nadal wymaga przeglądu. To świadomie węższy zakres automatyzacji:
CI nie rozpoznaje semantycznie zmian ekonomii czy logowania. Codex musi też klasyfikować treść:
ukrywanie elementów zgody/logowania przez CSS nie jest zwykłą zmianą. Destrukcyjne migracje,
logowanie, uprawnienia, ekonomia i usuwanie danych zawsze wymagają wyraźnej zgody właściciela
na aktualną wersję PR. Zgoda na poprzedni commit nie wystarcza.

CI działa dla PR, push i `merge_group`. PHP i JS/build uruchamiają się bez sekretów i bez sieci
produkcyjnej. `CI gate` wykonuje się przez `always()` i akceptuje wyłącznie oba wyniki `success`.
PHPUnit odrzuca skipped/incomplete/risky; runner JS odrzuca skipped/todo/cancelled i błędy.
Brak uruchomionego CI nie tworzy zgody na merge. Wymagamy bramki, ponieważ GitHub dopuszcza
pominięte zadania jako spełnienie niektórych wymaganych kontroli.

Po scaleniu commit na `main` przechodzi własne CI: wynik PR lub syntetycznego commita merge queue
nie jest używany do wdrożenia innego SHA. Dopiero zadanie zależne od PHP, JS i bramki wywołuje
`deploy.yml`, przekazując `github.sha`. Ten workflow ma wyłącznie `workflow_call`, sprawdza push
na main, zgodność SHA i flagę aktywacji. Nie pobiera kodu aplikacji, artefaktów ani cache PR.
Sekrety dostępne są tylko w osobnym zadaniu `production`, nigdy w testach.

Push na main nie anuluje aktywnego workflow. Wdrożenia mają wspólną grupę `draka-production`,
bez anulowania aktywnego zadania. GitHub może zastąpić oczekujący przebieg nowszym i nie zapewnia
FIFO. Nie obiecujemy wdrożenia każdego commita. Każdy wdrażany SHA musi mieć własne zielone CI;
VPS dodatkowo blokuje równoległe aktywacje i cofnięcie do starszego commita.

## GitHub: konfiguracja właściciela przed aktywacją

1. Pozostawić `DRAKA_DEPLOY_ENABLED` nieustawione lub `false`. Sprawdzić ten pierwszy PR ręcznie;
   nie włączać dla niego auto-merge. Ustawienia nie są zmieniane przez ten PR.
2. W ruleset/branch protection dla `main` wymagać PR, `PHP tests`, `JavaScript tests and build`
   oraz `CI gate` ze źródłem GitHub Actions. Włączyć aktualność gałęzi albo merge queue (workflow
   obsługuje `merge_group`). Zablokować bezpośrednie push, force-push i usuwanie gałęzi; brak bypass
   dla Codex/bota i administratorów. Wymagać review CODEOWNERS i odrzucać stare approvals po push.
   Dla zwykłych zmian bez właściciela ścieżki ogólna liczba wymaganych approvals może wynosić 0;
   zmiany w chronionych ścieżkach nadal wymagają właściciela. Jeśli plan GitHuba nie obsługuje
   tych reguł, pozostawić automatyzację wyłączoną i stosować ręczny przegląd.
3. Włączyć repozytoryjne „Allow auto-merge”. Codex może włączyć oczekiwanie na auto-merge dla
   zwykłego PR; wymagane kontrole i review muszą zostać spełnione. Przy merge queue korzystać
   z kolejki GitHuba. Nie dodawać konta automatyzacji do listy bypass. PR wymagający zgody musi
   mieć autora innego niż zatwierdzający właściciel: GitHub nie pozwala zatwierdzać własnego PR.
   Użyć oddzielnej tożsamości automatyzacji z ograniczonymi uprawnieniami; jej poświadczenia
   skonfigurować poza repozytorium i nigdy nie udostępniać w zadaniach testowych.
4. Utworzyć środowisko `production` dopuszczające wyłącznie gałąź `main` (żadnych tagów ani PR).
   Przenieść istniejące `DRAKA_SSH_PRIVATE_KEY`, `DRAKA_SSH_HOST`, `DRAKA_SSH_HOST_KEY` do sekretów
   tego środowiska i usunąć kopie repozytoryjne/organizacyjne dostępne temu repo. Nie wyświetlać
   wartości w logach. Samo utworzenie środowiska bez ograniczenia gałęzi nie wystarcza.
5. Początkowo wymagać ręcznego zatwierdzenia środowiska. Po akceptacji mechanizmu i testach VPS
   właściciel może usunąć ten wymóg dla zwykłych wdrożeń: zgody na wrażliwe zmiany pozostają na PR.
6. Dopiero po uzgodnieniu i wdrożeniu poniższego kontraktu VPS ustawić repozytoryjną zmienną
   `DRAKA_DEPLOY_ENABLED=true`. To oddzielna czynność właściciela, poza tym PR.

## VPS: wymagany kontrakt, do osobnej akceptacji

Projekt implementacji, wymagania systemowe, proponowana polityka migracji i szczegółowy plan
przejścia/rollbacku są w [ops/deploy/README.md](../ops/deploy/README.md). To projekt do audytu
po odczycie starego skryptu, nie potwierdzenie gotowości VPS. Żadnej instalacji nie wykonano.

Nie zmieniono ani nie uruchomiono skryptu VPS. Dotychczasowe `ssh ... deploy` nie dowodzi obsługi
SHA. **Nie wolno aktywować workflow ze starym wrapperem, który ignoruje argumenty i pobiera HEAD.**
Nawet brak prawidłowego potwierdzenia wykryty po SSH nie cofnie błędnego wdrożenia.

Właściciel powinien najpierw dostarczyć skrypt do przeglądu; jego aktualizacja wymaga osobnej
zgody i uzgodnienia okna synchronizacji. Wrapper forced-command ma spełniać następujący kontrakt:

- Akceptuje wyłącznie `SSH_ORIGINAL_COMMAND` o formacie `deploy [0-9a-f]{40}`, bez `eval`,
  dodatkowych argumentów i fallback do `main`/HEAD. Każde inne polecenie odrzuca przed zmianami.
- Zachowuje konto `draka-deploy`, forced-command i `restrict`/zakaz PTY, agent forwarding,
  port forwarding, X11 oraz interaktywnego shella. Klucz do pobierania repo pozostaje read-only.
  Brak roota, szerokiego sudo i możliwości nadpisania wrappera przez konto deploy/aplikację.
- Pod blokadą systemową obejmującą całe przygotowanie i aktywację pobiera repozytorium,
  sprawdza istnienie SHA i jego przynależność do historii main. Sprawdza ostatni wdrożony SHA:
  nowy musi być jego potomkiem; starszy lub rozbieżny commit odrzuca bez aktywacji. Ponowienie
  tego samego SHA jest bezpieczne i idempotentne. Pierwszy SHA bazowy musi ustalić właściciel.
- Buduje wydanie z dokładnego SHA, nie wykonuje późniejszego `git pull` ani checkout najnowszego
  main. Przed atomowym przełączeniem wydania sprawdza rzeczywisty SHA i health check; zapisuje
  ostatni wdrożony SHA atomowo pod tą samą blokadą. Błąd pozostawia dotychczasowe wydanie.
- Domyślnie nie uruchamia migracji; opcjonalne nowe migracje additive wymagają osobnej zgody,
  root-managed permitu dla dokładnego SHA/hashów i jawnego włączenia trybu z powyższego runbooka.
  Nigdy nie uruchamia seedowania, eksportów, skryptów Composer zmieniających
  bazę ani operacji na danych graczy. Nie kopiuje plików `public/mysql-8.4` i `new/` do wydania.
  Używa stałego bezpiecznego procesu instalacji zależności, bez wykonywania hooków aplikacji
  z uprawnieniami do zmiany bazy. Ewentualne migracje mają osobny zatwierdzany plan.
- Sukces zwraca kod 0 i dokładnie jedną linię stdout `deployed <SHA>` dopiero po potwierdzonej
  aktywacji tego SHA. Logi diagnostyczne kieruje na stderr. Błędy zwracają kod niezerowy.
  Log serwera zawiera SHA, czas i wynik; podsumowanie Actions zawiera SHA, run ID i attempt.

## Weryfikacja i uruchomienie

Lokalnie: `npm test`, `npm run build`, `composer validate --strict`,
`composer test -- --fail-on-skipped --fail-on-incomplete --fail-on-risky`.
Test regresyjny `tests/JavaScript/deployment.test.cjs` sprawdza odmowę dla nieudanych,
anulowanych i pominiętych zadań, uruchamia rzeczywiste małe zestawy JS z failure/skip/todo,
oraz kontroluje połączenia workflow, SHA, ochronę SSH i izolację środowiska.
Nie zastępuje to walidacji Actions ani integracji z rzeczywistym wrapperem.

Przed aktywacją przetestować na osobnym środowisku z fikcyjnym celem SSH i bez sekretów produkcji:

| Przypadek | Oczekiwany wynik |
| --- | --- |
| PR lokalny/fork, także zielony | Nigdy nie wywołuje SSH ani środowiska production |
| Błąd PHP albo JS/build | Czerwona bramka, brak wdrożenia |
| Pominięte zadanie/test, todo, anulowanie, timeout | Brak wdrożenia |
| Push z `[skip ci]` | Brak wdrożenia i brak wymaganej zielonej bramki |
| Zielony main przy wyłączonej fladze | Deploy pominięty |
| Zielony main SHA A, potem push B bez zielonego CI | Możliwe wyłącznie wydanie A, nigdy B |
| Zielone A/B, kolejność B przed A, ponowienie starego run | VPS odrzuca A, nie cofa B |
| Dwa równoczesne wdrożenia | Jedna aktywacja pod blokadą; SHA zgodny z własnym CI |
| Nieprawidłowe SHA/polecenie SSH | Odrzucone przed zmianami |
| Zerwane SSH, błąd build/health check, błędne potwierdzenie | Zadanie nie jest zielone; sprawdzić log VPS |
| Wrażliwy PR bez nowej zgody właściciela | Ruleset blokuje merge niezależnie od CI |

Pierwsze ręczne scalenie mechanizmu ma pozostawić flagę wyłączoną. Następnie wykonać powyższe
testy, zaakceptować osobno wrapper i konfigurację, a dopiero potem aktywować przyszłe wdrożenia.
Wyłączenie flagi blokuje nowe zadania; nie zatrzymuje rozpoczętego SSH. Nie anulować go jako
metody rollbacku. Cofnięcie wydania i naprawy bazy wymagają osobnego planu oraz zgody.

Podstawy: [zależności i warunki zadań](https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-syntax),
[współbieżność](https://docs.github.com/en/actions/how-tos/write-workflows/choose-when-workflows-run/control-workflow-concurrency),
[wymagane kontrole](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-protected-branches/about-protected-branches),
[CODEOWNERS](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/customizing-your-repository/about-code-owners).

## Wyniki lokalne przygotowania (2026-11-09)

- PHP 8.3.16: 93 testy, 863 asercje, bez błędów, także z restrykcyjnymi flagami PHPUnit.
  Pierwszy przebieg zablokowały uprawnienia `bootstrap/cache`. Udany przebieg korzystał wyłącznie
  z osobnego cache testowego w `storage/framework/draka-ci-validation` przez zmienne
  `APP_PACKAGES_CACHE`, `APP_SERVICES_CACHE` i `VIEW_COMPILED_PATH`; nie zmieniono bootstrapu.
- Node 22: 13/13 testów; w tym rzeczywiste odmowy dla failure, skip, todo i anulowanego testu.
- Vite build i `composer validate --strict`: sukces. Pint: trzy pliki infrastruktury testowej OK.
- Oba workflow sparsowane przez Symfony YAML; regresje sprawdzają powiązania i zabezpieczenia.
  Nie uruchomiono actionlint ani zdalnego CI dla tych zmian.
- Weryfikacja ręczna: przegląd DAG i kontraktu, brak checkout/artifact/cache w zadaniu produkcji,
  brak ścieżki wdrażania z PR. Integracja SSH/VPS i test kolejki na GitHubie pozostają niewykonane.
- Pierwszą próbę publikacji zablokowała polityka uprawnień sesji. Po ponowieniu pracy
  z zatwierdzeniem dostępu do `.git` utworzono gałąź `feature/safe-ci-deploy`, zachowując
  wszystkie przygotowane zmiany. Wyniki powyżej dotyczą kontroli lokalnych; aktualny wynik
  zdalnego CI należy sprawdzić w PR dla dokładnego opublikowanego SHA.
