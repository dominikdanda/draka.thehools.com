# TheHools 2.0 / Draka

## Scope and safety

Implement complete features from their specification, with regression tests. Report results in Polish.
Never change economy or gameplay incidentally. Clarify materially different game-design outcomes.
Never deploy from a workstation, weaken authentication/payment/security boundaries, or touch real databases.
After the owner approves and activates the mechanism in `docs/deployment.md`, ordinary changes may
use GitHub native auto-merge and CI-gated production deployment. Never bypass required checks,
reviews or environments. The initial automation PR for Issue #3 must not be auto-merged or deployed.
Destructive migrations, login/authentication, authorization, economy and data deletion always need
the owner's explicit approval for the current change. Do not infer that approval from green CI.
Read `docs/development.md` before running setup, tests or database commands.
Do not read or print `.env` credentials. Do not use `composer setup`, migrate, seed, migrate:fresh,
or legacy export commands against the developer's configured database.

At the start, inspect `git status --short`, current branch and applicable instructions. Preserve all
existing changes; never reset, discard or stage unrelated work. Use explicit paths for staging.
`public/mysql-8.4` contains tracked live database files: do not read, copy, edit, stage, delete or run
them. The `new/` directory contains legacy/reference material, not the Laravel application.

## Architecture verified against code

- PHP ^8.2, Laravel ^12, PHPUnit 11; Composer lock is authoritative. CI runs PHP 8.3.
- `routes/web.php`: server-rendered Blade game, session authentication, POST/PUT mutations.
- `app/Http/Controllers` coordinate requests; `app/Http/Requests` validate user input.
- `app/Actions` implement login, captain recruitment and career completion.
- `app/Services/Player`, `Career`, `Game` contain progression, access, regeneration and XP logic.
- `app/Models/Player.php` is the authenticated game account (see `config/auth.php`), separate from
  the scaffold User. Stats, resources, career inventory/progress and captains belong to Player.
- Game tables use the default connection. `LobbyLoginAction` explicitly uses `lobby` for
  `accounts_servers`; that external schema is not managed by the game migrations.
- `database/migrations`, factories, seeders and `database/data/classic_campaign.json` define local
  schema and classic campaign data. Tests create their own fixtures.
- `config/thehools.php` defines formulas, world identity and module availability.
- `resources/views/layouts/app.blade.php` loads `public/assets/css/thehools.css` and
  `public/assets/js/thehools.js`. Vite builds `resources/css/app.css` and `resources/js/app.js`;
  do not assume Vite publishes the standalone game assets. JavaScript tests read public JS.
- `tests/Unit`, `tests/Feature`: PHPUnit; `tests/JavaScript/*.test.cjs`: Node's built-in test runner.

## Domain rules (current implementation, not a new design specification)

- Lobby login requires a 40-character lowercase hex token, configured positive world server ID,
  matching server and login newer than 20 seconds. Successful login clears the token and
  regenerates the session (`LobbyLoginAction`, `LobbyLoginController`, routes).
- Protected game routes apply auth, RequireActiveCaptain, player regeneration and module
  availability. Disabled modules are placeholders, not implemented systems.
- `CurrentCaptainService` accepts only owned active captains; auto-selects the sole active captain.
  Recruitment locks the player and enforces the configured active limit (currently 3).
- Career is campaign -> chapter -> stage. Chapter access checks player level and mastery of a
  prerequisite chapter. Stage n > 1 requires 10 completions of stage n-1 (`StageAccessService`).
- `CompleteStageAction` checks access, energy and required inventory, then applies rewards,
  energy cost, mastery and XP in a transaction. Preserve server-side checks on write endpoints.
- XP carries its remainder across levels; each level grants configured skill points.
- Regeneration is applied on requests, with separate energy/health timestamps and premium rates.
  `PlayerRegenerationService` documents lock order: Player, PlayerStats, PlayerResources.
  Preserve this order in writers; SQLite tests do not prove MySQL lock correctness.
- Formula defaults currently include level 1, 4 initial skill points, 4 per level, base energy 40,
  health 100, energy regeneration 60s/45s premium, health 120s/90s premium. Read config rather
  than duplicating constants or treating these values as permission to rebalance.

## Uncertainties / limits

The working tree at setup contains unfinished captain resource changes, including an untracked
migration and tests. Player-level and captain-level resource ownership must not be assumed to be
the final intended design. Inspect the feature specification before extending it.
Cooldown/relocation settings exist in config; their presence does not prove full implementation.
MySQL-specific concurrency, live Lobby compatibility and production deployment are not verified
by SQLite CI. No production database, credentials or deployment procedure is required for CI.

## Feature workflow and definition of done

1. Read the specification and acceptance criteria; inspect relevant code/tests and Git status.
2. Plan internally. Ask only about material design ambiguity, destructive/production actions,
   weakened security boundaries or external deployment approval.
3. Create a `feature/<short-name>` (or `fix/`, `chore/`) branch. Never overwrite an existing branch.
4. Add a meaningful failing test for the acceptance criteria, implement the complete behavior,
   and test negative cases, ownership, validation and boundary values where relevant.
5. Run targeted tests, then the full PHP/JS suites and build. Fix introduced failures; report
   pre-existing failures explicitly. Check formatting on touched PHP files without rewriting
   unrelated code. Update documentation and include migrations when the feature needs them.
6. Review the diff for secrets, unrelated changes and gameplay/economy drift. Commit only owned
   changes using explicit paths. Push and open a PR when authentication/tools permit; never
   invent a link, successful run or approval. Classify risk in the PR. For ordinary changes only,
   after the owner has activated the mechanism, enable native auto-merge subject to main's rules.
   Sensitive changes require owner approval at the current revision; CODEOWNERS deliberately
   requires review for all paths except ordinary docs and stylesheets. Do not relax these rules
   yourself, approve your own PR, call a direct merge API or invoke SSH deployment manually.
7. Report in Polish: behavior, tests/results, manual verification, limitations, branch, commits,
   PR and actual CI status. Distinguish local results from a GitHub Actions run.

Done means all acceptance criteria are covered, relevant regression tests pass, full checks have
been run with failures resolved or explicitly explained, reviewable commits and PR (or a concrete
tooling blocker) exist, manual verification is documented, and no unrelated work or real data was
modified. Green remote CI can only be claimed after inspecting the run for the exact pushed commit.
