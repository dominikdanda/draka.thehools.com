# Projekt kontraktu VPS — NIE instalować bez przeglądu obecnego serwera

To kompletny projekt nowego entry pointu `/home/draka-deploy/deploy-draka.sh` i silnika Python,
nie kopia ani modyfikacja istniejącego skryptu. Jego treści ani układu VPS nie odczytano.
Ścieżka `/srv/draka` jest **propozycją nowego układu**, nie stwierdzeniem o produkcji.
Przykładowa konfiguracja ma oba przełączniki wyłączone. Żaden plik z tego katalogu nie jest
samoczynnie instalowany przez GitHub Actions ani aplikację.

## Jedyna potrzebna teraz czynność na VPS

Z istniejącej, zaufanej **sesji administracyjnej VPS**, nie przez klucz forced-command, wykonać:

```sh
sudo -n cat -- /home/draka-deploy/deploy-draka.sh
```

Przed przekazaniem wyniku sprawdzić lokalnie, czy skrypt nie ma wpisanych haseł/tokenów; takie
wartości zastąpić `[REDACTED]`. Nie przekazywać `.env`, kluczy, `auth.json`, `hosts.yml` ani tokenów.
Nie wysyłać `cat` przez stare konto `draka-deploy`: stary forced-command może zignorować argumenty
i uruchomić wdrożenie. Nie znamy hosta/konta administracyjnego i nie zgadujemy danych dostępu.

## Co gwarantuje projekt

- SSH akceptuje tylko `deploy <40 małych znaków hex>`, jedną spację, bez dodatkowych argumentów,
  nowych linii, `eval`, shella, rollbacku ani fallback do HEAD. Wrapper czyści środowisko i uruchamia
  Python w trybie izolowanym. Silnik odmawia działania jako root lub konto inne niż `draka-deploy`.
- Root musi posiadać wrapper, cały jego katalog nadrzędny `/home/draka-deploy`, silnik, konfigurację
  i pliki kontroli. Sama własność pliku nie wystarcza, jeśli deploy może zastąpić go przez zapis do
  katalogu nadrzędnego. Te warunki są sprawdzane przed działaniem; nie są automatycznie naprawiane.
- Kernelowy `flock` obejmuje fetch, dowód CI, build, ewentualne zatwierdzone DDL, aktywację, health
  i zapis stanu. Oczekiwanie 120 s kończy się błędem; brak kolejki FIFO nie może cofnąć wdrożenia.
  Wszystkie inne procesy administracyjne/migracje muszą respektować ten sam lock. To projekt dla
  **jednego VPS**; wiele hostów wymaga osobnej blokady rozproszonej i planu migracji.
- Fetch dotyczy main z ustalonego repozytorium przez read-only key. Kandydat musi być przodkiem
  pobranego main i potomkiem `high_water_sha`. Brak stanu bazowego, historia rozbieżna, stary SHA,
  niezgodność stanu z symlinkiem lub niedokończona transakcja blokują proces.
- GitHub CLI weryfikuje **najnowszy push run** ustalonego workflow ID dla konkretnego SHA/main,
  pochodzący z właściwego repo. PHP, JS/build i CI gate muszą mieć `completed/success` oraz ten sam
  SHA i `run_attempt`. Nieznane/zdublowane/pominięte zadania testowe oznaczają odmowę. PR, fork,
  workflow_dispatch, merge_group i poprzednia udana próba nie zastępują push CI na main.
  Lista zadań weryfikowana jest ponownie po buildzie, przed zmianami.
  Weryfikator korzysta z endpointu jobs konkretnej próby (pole run_attempt nie musi występować
  w pojedynczym job JSON), odświeża metadane próby i sprawdza również kluczowe kroki testów.
  Zmiana nazw kroków/zadań wymaga przeglądu i aktualizacji root-owned weryfikatora; brak zgodności
  blokuje deploy zamiast pomijać nową kontrolę.
- Cały run CI **może jeszcze trwać**, ponieważ zawiera właśnie zadanie deploy. Dlatego dowodem
  są trzy zakończone zadania testowe, a nie zakończenie całego workflow. Zakończony run musi mieć
  wynik success. Po nieudanym deployu użyć „Re-run all jobs”, nie tylko „Re-run failed jobs”:
  projekt celowo wymaga wszystkich dowodów z tej samej próby. Odwołanie zgody/przełącznika nie
  cofa już wykonanych operacji; przerwanie po rozpoczęciu wymaga sprawdzenia dziennika VPS.
- Eksport czyta wyłącznie dozwolone pliki wskazanego commita, bez worktree HEAD i bez filtrów
  smudge/hooków. Pomija przed odczytem blobów `public/mysql-8.4`, `new`, `.env*`, storage, vendor,
  node_modules i stare buildy/cache. Odrzuca symlinki oraz submoduły źródłowe. Mirror powinien być
  bare partial clone (`blob:none`) z GitHuba, aby nie pobierać niepotrzebnych blobów bazy.
- Composer instaluje lock z `--no-dev --no-scripts --no-plugins`; osobno sprawdza platformę.
  Npm używa `ci --ignore-scripts`, potem build z tego SHA. Brak manifestu zatrzymuje proces.
  Nie ma `composer setup`, key generation, seedów, exportów ani `git pull`.
- Po buildzie wiązany jest plik środowiska (bez odczytywania/drukowania przez orkiestrator)
  i trwałe app/logs/sessions/file cache. `bootstrap/cache` i skompilowane widoki są **osobne dla
  każdego wydania**. Wykonywane są jawnie `package:discover`, `config:cache`, `route:cache`,
  `view:cache`; nigdy `optimize:clear`/`cache:clear`, które mogłyby dotknąć współdzielonego cache.
- `current` jest podmieniane atomowo na tym samym filesystemie. Unikalny endpoint PHP dla SHA
  uruchamia kernel Laravel z trasą `/up` i odpowiada wyłącznie `healthy <SHA>` lub ogólnym 503.
  Curl sprawdza przez loopback prawdziwe HTTPS, certyfikat i FPM; bez przekierowań, proxy i `-k`.
  Endpoint nie pyta o dane graczy ani Lobby. Nie jest to pełny test funkcjonalny MySQL.
- Awaria health przywraca poprzedni symlink i sprawdza jego kernel. Może wystąpić krótki okres
  obsługi przez niesprawne wydanie: podmiana symlinka nie jest transakcją z HTTP. Awaria rollbacku
  również blokuje automat. Po rozpoczęciu migracji/aktywacji pozostaje `transaction.json` aż do
  pełnego sukcesu lub ręcznego wyjaśnienia. SIGKILL/awaria zasilania nie wymagają zgadywania stanu.
- `state.json` przechowuje `active_sha`, `high_water_sha` i katalog wydania. Atomowy zapis z fsync
  i dziennik wykrywają przerwanie pomiędzy aktywacją a zapisem. Znacznik high-water nie jest
  cofany nawet po awarii po zapisie nowego stanu ani przy późniejszym rollbacku operatora.
- Stdout sukcesu to wyłącznie `deployed <SHA>`. Błędy są niezerowe. Audit JSONL (0600) zawiera czas,
  SHA, run/attempt, etapy i wynik; nigdy treść środowiska lub output Composer/Artisan/GitHub.
  Output procesów jest odrzucany, aby wyjątek aplikacji nie ujawnił sekretów przez SSH. Błędy
  diagnozuje administrator w kontrolowanych warunkach. Nie ma `set -x` ani drukowania komend.
- Procesy mają limity czasu i własne grupy; timeout/przerwanie zabija całą grupę potomną.
  Lease serwera 12 min jest krótszy od 15-min limitu Actions i zostawia czas na odtworzenie symlinka.
  Nie ma automatycznego usuwania wydań, logów ani danych; retencję zatwierdza operator.

## Warunki instalacji — do potwierdzenia po odczycie starego skryptu

1. Oddzielne konta Unix: deploy i FPM; zaufany współdzielony **primary group dla wydań** pozwala
   FPM czytać pliki 0640/katalogi 0750. FPM nie może zapisywać kodu, state, repo ani konfiguracji.
   `bootstrap/cache` i lokalne views mają 0770; wspólne katalogi storage powinny mieć setgid/2770.
   Root-managed shared `.env` to zwykły plik 0640; nie jest dostępny z document root.
2. Root instaluje po przeglądzie wrapper pod wskazaną ścieżką, `deploy.py` w
   `/usr/local/lib/draka-deploy/deploy.py` oraz JSON w `/etc/draka-deploy/config.json`.
   Wrapper 0755, silnik 0644, root-owned katalogi 0755 lub 0750 bez group/other write.
   Kod aplikacji nie może aktualizować tych plików. Domyślne `enabled=false` zostaje do testów.
3. Proponowany układ (wszystko poza shared storage musi być wcześniej utworzone przez operatora):

   ```text
   /srv/draka/                   deploy-owned 0750, wspólna grupa do odczytu FPM
     repo.git/                  deploy-owned 0700, bare/promisor mirror, origin SSH read-only
     releases/<sha>-<id>/        osobne niezmienne wydania; FPM nie zapisuje kodu
     shared/.env                root-owned 0640, istniejące wartości, bez ich pokazywania
     shared/storage/app/public/ trwałe uploady
     shared/storage/logs/       trwałe logi aplikacji
     shared/storage/framework/sessions/
     shared/storage/framework/cache/data/
     state/                     deploy-owned 0700
       cache/                   lokalny cache Composer/npm, bez credentials
       state.json               deploy-owned 0600, ustawiony ręcznie znany baseline
       deploy.lock              0600
       audit.jsonl              0600
       transaction.json         tylko w trakcie lub po przerwanym wdrożeniu
     current -> releases/<sha>-<id>
   ```

   Mirrora nie tworzy się z lokalnego repo dewelopera ani przez kopiowanie live MySQL. Operator
   inicjalizuje pusty bare/promisor mirror z origin `git@github.com:dominikdanda/draka.thehools.com.git`
   i pobiera main przez `--filter=blob:none`; ustawia `remote.origin.promisor=true` oraz
   `remote.origin.partialclonefilter=blob:none`. Potwierdza pełną historię potrzebną do ancestry.
4. Dedykowany outbound key **read-only**, ustalony known_hosts GitHuba i root-owned
   `/etc/draka-deploy/git-ssh.conf`: Host github.com, BatchMode yes, IdentitiesOnly yes,
   StrictHostKeyChecking yes, ForwardAgent no, IdentityFile i UserKnownHostsFile do zatwierdzonych
   plików. Bez `accept-new`, tokena w URL i odziedziczonego SSH_AUTH_SOCK.
5. `gh` wymaga oddzielnego poświadczenia GitHuba tylko do odczytu metadata/Actions tego repo,
   skonfigurowanego przez właściciela w `/etc/draka-deploy/github/hosts.yml` (root-owned, bez
   group/other write, odczyt tylko dla deploy, nie FPM). Silnik sprawdza własność, nie czyta tokena.
   Poświadczenie nie może mieć contents write, admin ani możliwości zatwierdzania/mergowania PR.
   Brak dostępu do API lub wyczerpanie limitu kończą się odmową; nie ma trybu „zaufaj SHA”.
6. PHP 8.3 z rozszerzeniami locka, Composer 2, Node 22/npm, Python >=3.10, Git z partial clone,
   GitHub CLI i curl. Zweryfikować platformę, wersje i odtwarzalność builda na kopii staging.
   `state/cache` musi być przygotowany do zapisu deploy; nie przenosić credentials Composer/npm.
7. Nginx/Apache document root ma wskazywać wyłącznie `current/public`. Zezwolić na obsługę PHP
   dla `/.well-known/draka-release-<sha>.php` przez właściwy pool FPM, bez cache/CDN; blokować
   `.env`, pozostałe pliki ukryte i wszystkie pozostałe katalogi. Zweryfikować loopback TLS/SNI,
   brak stale realpath/opcache oraz dostępność nowego endpointu przed akceptacją.
8. Ten projekt **nie dodaje sudo ani restartu usług**. Jeśli pool FPM wymaga reloadu (np.
   opcache.validate_timestamps=0 albo problem z realpath), obecny projekt ma fail-closed przez
   unikalny probe; przed instalacją trzeba uzgodnić osobny minimalny root-managed mechanizm
   reloadu. Nie dawać deploy ogólnego `sudo systemctl`. Kolejki/Horizon/Octane/scheduler wymagają
   osobnego potwierdzonego planu graceful restart/drain; nie zgadujemy ich stanu na obecnym VPS.
9. W inbound authorized_keys zachować opcje `restrict,command="/home/draka-deploy/deploy-draka.sh"`
   (lub równoważne jawne zakazy PTY/forwarding/X11/agent). Authorized_keys i nadrzędne katalogi
   muszą być zarządzane przez root, bez zapisu przez deploy. Konto nie otrzymuje shella ani roota.

Wykonywanie builda i Artisan oznacza wykonywanie **zaufanego, zatwierdzonego kodu main** jako
deploy. Samo usunięcie GH/SSH zmiennych ze środowiska builda nie jest sandboxem: ten sam Unix UID
ma dostęp do własnych plików. Nie dopuszczać niezaufanych PR do tego procesu. Jeżeli wymagane jest
również odseparowanie poświadczeń deploy od zaufanego kodu aplikacji, potrzebny będzie osobny
build worker/kontener i podpisany artefakt — to osobna decyzja przed aktywacją, nie obecna gwarancja.

## Polityka migracji — proponowana, jeszcze nieaktywna

Domyślnie `allow_additive_migrations=false`: jakakolwiek nowa migracja blokuje deploy **przed
buildem i przed dotknięciem bazy**. Modyfikacja/usunięcie starej migracji zawsze blokuje automat.
Nie ma analizatora PHP, który uznaje migrację za bezpieczną tylko na podstawie nazwy lub `up()`.

Opcjonalny tryb po osobnej akceptacji właściciela:

- Właściciel sprawdza dokładną treść nowej migracji, czasy/locki na staging MySQL, zgodność
  starego i nowego kodu i możliwość rollbacku samego kodu. Dopuszczalne są wyłącznie operacje
  expand: np. nowa niezależna tabela; nullable column/index tylko po potwierdzeniu rzeczywistej
  wersji MySQL i sposobu wykonania bez blokującego rebuilda. Samo „nie usuwa danych” nie wystarcza.
- Drop/rename, zwężanie typów, NOT NULL bez strategii, zmiana ekonomii/uprawnień, backfill,
  UPDATE/DELETE/TRUNCATE, operacje na kontach/danych graczy, duże indeksy i naprawy danych wymagają
  osobnego zatwierdzonego okna, backupu/PITR i runbooka. Nie przechodzą przez ten tryb.
- Zgoda jest poza repozytorium: root-owned `/etc/draka-deploy/migration-permits/<SHA>.json`,
  związana z dokładnym SHA, poprzednim `high_water_sha` i SHA-256 **każdego** nowego pliku.
  Katalog root-owned bez zapisu deploy/web uniemożliwia samodzielne zatwierdzenie przez PR.

  ```json
  {
    "sha": "<40 hex>",
    "base_sha": "<40 hex>",
    "classification": "additive",
    "rollback_compatible": true,
    "approved_by": "<właściciel / ticket zgody>",
    "backup_reference": "<sprawdzony backup/PITR i próba odtworzenia>",
    "migrations": {"database/migrations/<dokładna_nazwa>.php": "<64 hex SHA-256>"}
  }
  ```

- Operator potwierdza, że baseline nie ma zaległych migracji, i dokumentuje backup. Program
  weryfikuje zgodność manifestu, **nie istnienie ani odtwarzalność backupu** — za to odpowiada
  osoba wydająca permit. Taki permit nie upoważnia do innych zmian niż wskazane pliki.
- Po włączeniu trybu i obecności permitu skrypt sam wykonuje wyłącznie wskazane nowe ścieżki
  przez `php artisan migrate --force --no-interaction --path=...` w kolejności nazw, pod lockiem.
  Nie wykonuje ogólnego `migrate`, który mógłby podjąć zaległe/niezatwierdzone pliki.
- Implementacja używa bieżącego połączenia Laravel. **Nie zwiększać uprawnień roli aplikacji**,
  aby włączyć ten tryb. Jeśli aplikacja nie ma DDL, pozostawić go wyłączonym; preferowany osobny
  migrator z oddzielnym, minimalnym poświadczeniem wymaga osobnego projektu i akceptacji.
  Odczyt starego skryptu i istniejących granic uprawnień zdecyduje, czy ten opcjonalny tryb
  w ogóle będzie dopuszczony. Nie dostarczono ani nie zmieniono żadnych poświadczeń bazy.
- Błąd/timeout DDL pozostawia journal i stary kod. MySQL może częściowo zatwierdzić DDL; nigdy
  nie uruchamiać automatycznie `migrate:rollback`, `down`, seed ani retry po usunięciu markeru
  bez sprawdzenia rzeczywistego schematu. Właściciel rozstrzyga naprawę do przodu lub restore.

## Przejście ze starego wdrożenia — wyłącznie po osobnej zgodzie

1. Najpierw uzyskać odczyt starego skryptu i potwierdzić: ścieżki repo/document root/shared,
   konta/grupy/uprawnienia, wersje narzędzi, kolejki/cron/FPM, sposób migracji/cache, dostęp do GH,
   aktywny SHA i jego zgodność z main. Nie odczytywać wartości sekretów. Zanotować drift kodu.
2. Właściciel zatwierdza plan po różnicy stare/nowe, wybiera politykę migracji (zalecane na start:
   całkowicie wyłączone) i model credentials. Nadal nie scalać PR #4 ani nie zmieniać VPS.
3. Najpierw osobny staging: fikcyjne SSH, własna jednorazowa baza i dane syntetyczne. Przetestować
   macierz z docs/deployment.md, poniższe testy awarii i produkcyjny build bez hooków; przegląd
   providerów Artisan pod kątem efektów ubocznych. Dopiero potem poprosić o zgodę na instalację.
4. Przed zatwierdzonym oknem wyłączyć repo flagę i nowe `enabled`; wstrzymać stare webhooki,
   Actions/cron wdrożeniowe, odczekać ich zakończenie. Stary i nowy skrypt muszą nigdy nie działać
   równolegle. Wykonać sprawdzony backup/PITR, utrwalić konfigurację web/FPM i kopię starego
   wrappera bez publikowania sekretów. Nie kopiować katalogu live MySQL jako backupu aplikacji.
5. W uzgodnionym oknie zamrozić zapisy/obsługę jobów na czas migracji struktury storage i spójnego
   przeniesienia uploadów/sesji; nie zgadywać downtime. Przygotować root-owned control plane,
   partial mirror, wymagane katalogi i runtime uprawnienia. Zachować istniejący APP_KEY i sekrety.
6. Przygotować/importować znane obecne wydanie aplikacji **bez** live DB, legacy, sekretów, starych
   cache i vendor z innej platformy; powiązać istniejące środowisko i storage. Zweryfikować jego
   SHA i schemat. Dla baseline potrzebny jest także probe generowany przez `Engine.write_probe`.
   Root/operator ustawia `current` oraz jednorazowy `state.json`, np. active/high_water = znany
   SHA, release = `<SHA>-baseline`, chmod 0600 i właściciel deploy. Skrypt nie ma auto-init stanu.
   Jeśli stan kodu lub migracji jest nieznany, przerwać; nie ustawiać fikcyjnego baseline.
7. Przełączyć docroot, sprawdzić health/identity, TLS, uprawnienia, logowanie i sesje syntetycznego
   użytkownika staging. Produkcyjny smoke test wymaga osobnej zgody; nie używać danych graczy.
   Podmienić forced-command na zatwierdzony wrapper **z enabled=false**. Próba starego `deploy`
   i wszelkich innych komend ma być odrzucana; nie testować starego wrappera z `cat`.
8. Po osobnym potwierdzeniu gotowości scalić PR ręcznie z repo flagą nadal wyłączoną. Pierwszy
   nowy commit main musi przejść własne CI. Właściciel dopiero wtedy zezwala na włączenie config,
   środowiska GitHub i repo flagi. Test pierwszego rzeczywistego deployu to oddzielna zgoda;
   ten runbook ani zielone testy nie są taką zgodą.

## Rollback i odzyskiwanie po awarii

- Przed aktywacją: dotychczasowe wydanie działa; nie usuwać poprzednich wydań ani shared data.
  Kandydat z błędem builda pozostaje nieaktywny do ręcznej retencji. Nie powtarzać bez diagnozy.
- Po nieudanym health: automat próbuje odtworzyć poprzedni symlink; journal blokuje kolejne
  uruchomienia. Operator najpierw wyłącza flagi, pobiera lock i sprawdza current, state, audit,
  journal, procesy, schema/migrations i health. Nie usuwa markeru „żeby przeszło”.
- Po utracie zasilania/SIGKILL nie ma automatycznego odgadywania. Pod tym samym lockiem odtworzyć
  wybrane znane wydanie albo dokończyć aktywację po kontroli schematu; zapisać spójny state.
  Usunąć journal dopiero po zatwierdzonym recovery i sprawdzonym HTTP identity/health.
- Świadomy rollback po sukcesie odbywa się **poza SSH deploy key**, przez administratora w
  uzgodnionym oknie, z wyłączonym automatem, drenowaniem jobów i tym samym lockiem. Atomowo
  przełączyć symlink na zachowane znane wydanie, zweryfikować jego probe, ustawić `active_sha`
  i `release` atomowo/fsync, ale zachować najwyższy `high_water_sha`. Kolejny automatyczny deploy
  wymaga nowego zatwierdzonego potomka high-water; stary run nie może odwrócić rollbacku operatora.
- Baza nie jest cofana razem z kodem. Additive migrations muszą być zgodne ze starym kodem.
  Ryzykowny restore wymaga osobnej zgody, planu utraty/odtworzenia zapisów, maintenance i próby
  PITR. Nie ma automatycznego `down`, kasowania tabel ani zmiany danych graczy.
- Rollback samego mechanizmu: zatrzymać automat i zachować stan/lock/audit. Przywrócenie starego
  wrappera ignorującego SHA z aktywnym Actions jest zabronione. Bezpieczny stan awaryjny to
  wrapper odrzucający wszystkie polecenia i ręczne zatwierdzone operacje administratora.

## Testy i granice dowodu

`python -m unittest discover -s tests/Deployment -v` uruchamia syntetyczne testy polityki,
eksportu i transakcji, w tym build/CI/health/DDL/audit failure, idempotencję, stary run oraz
high-water po rollbacku. Nie uruchamia Composer, Laravel, SSH, GitHub API ani bazy.
`python3 tests/Deployment/posix_contract.py` na jednorazowym Linuxie sprawdza prawdziwe flock
między procesami, fsync/atomowe symlinki, recovery i zabijanie procesu po timeout.
CI dodaje te kontrole po istniejącym JS/build; działających testów PHP/JS i izolacji nie zmieniono.

Przed instalacją nadal wymagane są staging z rzeczywistym wrapperem, test read-only GitHub/SSH,
pakietów i cache, FPM/opcache, uprawnień, równoległych pushów/ponowień i przerwania procesu w
każdym etapie. Mocks oraz zielone CI nie potwierdzają układu produkcji ani poprawności MySQL DDL.

Referencje: [Laravel 12 deployment](https://laravel.com/docs/12.x/deployment),
[GitHub workflow runs](https://docs.github.com/en/rest/actions/workflow-runs),
[GitHub workflow jobs](https://docs.github.com/en/rest/actions/workflow-jobs).
