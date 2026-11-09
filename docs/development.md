# Autonomous development

The repository uses Laravel 12 / PHP >=8.2, PHPUnit 11, Composer 2, Node and npm.
Use PHP 8.3 and Node 22 to match CI; PHP needs PDO SQLite, mbstring, DOM/XML and the
extensions checked by Composer. Install locked dependencies with `composer install
--no-scripts` and `npm ci`. No database server, Lobby access or `.env` is needed for tests.
Composer's normal setup script migrates the configured database: do not use it as test setup.

## Commands

| Purpose | Command |
| --- | --- |
| Full PHP suite | `composer test` |
| Specific PHP regression | `composer test -- --filter=TestDatabaseIsolationTest` |
| Direct PHP runner | `php vendor/phpunit/phpunit/phpunit` |
| JavaScript tests (reject failures, skips, cancellations and todo) | `npm test` |
| Asset build | `npm run build` |
| Vite development server | `npm run dev` |
| PHP formatting check (application directories) | `composer lint` |
| Formatting on touched PHP only | `php vendor/bin/pint --test path/to/file.php` |
| Validate Composer manifest/lock | `composer validate --strict` |

On PowerShell use `npm.cmd` if the execution policy prevents `npm.ps1`. For faster local
PHP runs when Xdebug is installed, set `$env:XDEBUG_MODE = 'off'` for that shell.
Do not run Pint without `--test` over unrelated files. Existing formatting debt is not silently
fixed by this infrastructure task; CI checks syntax throughout application PHP and Pint for the
three new/updated test infrastructure files. Future feature PRs should format their touched files.

## Database isolation

`phpunit.xml` boots `tests/bootstrap.php`. It forcibly overrides inherited test environment
values (including DB URL), supplies a test-only key and bypasses the normal config cache.
`Tests\TestCase` skips the developer `.env`, then replaces the entire connection list before
providers boot: only independent `sqlite` and `lobby` connections to `:memory:` remain.
Unknown connections fail instead of falling back to MySQL. Feature tests extend this base class;
plain PHPUnit unit tests must not bootstrap production configuration or connect to databases.
The isolation regression checks connection configuration and separation by executing SQLite DDL.
Never add raw PDO, remote HTTP calls or real credentials to tests. This is application-level
isolation, not a sandbox for malicious test code.

CI test jobs use ephemeral GitHub-hosted runners, no database service, no environment/production
secrets and a read-only GitHub token. Checkout excludes tracked live MySQL files and
legacy reference content. Composer installs with `--no-scripts`; tests bootstrap the app themselves.
Do not change test jobs to `pull_request_target`, attach a production environment, inject
database secrets, or switch to a runner with production network access.
SQLite verifies behavior but not MySQL row locking/collations; any future MySQL test job must use
an ephemeral service with synthetic fixtures and disposable credentials, never a shared server.

## Initial audit (2026-11-05)

- Started on `main`, HEAD `bec5a26`, origin `dominikdanda/TheHools-Laravel` on GitHub.
- No AGENTS.md, GitHub Actions workflow or PR template existed.
- Existing tests cover progression/XP, career access/mastery, Lobby login, profiles, regeneration,
  module availability and captains. Four Node tests cover the public captain selector script.
- Initial local modifications: `app/Models/Captain.php`, public/resources game CSS,
  career chapter and player-strip views, `tests/Feature/DashboardTest.php`, plus many tracked
  live MySQL files. Untracked: captain resources migration/test and a MySQL binlog.
  They are user work and must remain excluded from this infrastructure commit.
- `public/mysql-8.4` has 208 tracked files. Their presence under the web root needs a separate
  approved remediation and hosting review. This task does not move/delete live database files.
- There was no JS test npm script; Vite already had build/dev scripts and a lockfile.
- The original PHPUnit environment entries were not forced, and Lobby defaulted to MySQL.
- `composer test` previously cleared the developer's shared config cache; it now invokes PHPUnit
  directly and leaves that cache alone.

Local validation on the initial dirty working tree: PHPUnit ran 92 tests / 840 assertions with
one failure in `CaptainRecruitmentTest::test_invalid_names_are_rejected_and_errors_are_visible`.
The existing captain form echoes `old('name')` even when validation rejected an array, causing
`htmlspecialchars()` to throw while rendering the error response. This is a pre-existing view/test
defect outside this infrastructure task; it is not suppressed or marked as passing. The run also
reported one deprecation. JavaScript: 4/4 passed; Vite build and Composer validation passed.
Pint passed for all three test infrastructure PHP files; a broad pre-existing formatting check
reported failures and hit Composer's 300-second timeout before its scope was narrowed.
Remote CI results must be read from the actual PR run, which excludes the developer's uncommitted
captain resource work.

Verified GitHub Actions run for commit `55d710b`:
https://github.com/dominikdanda/TheHools-Laravel/actions/runs/41993446410
JavaScript tests and build passed. PHP dependency installation and isolation preflight passed;
PHPUnit ran 87 tests / 748 assertions and failed on the same existing captain form defect.
Subsequent syntax/formatting steps were skipped because the test step failed. The final local
rerun no longer reported the deprecation after the bootstrap supplied `APP_URL`.
Draft PR: https://github.com/dominikdanda/TheHools-Laravel/pull/1

## Human setup and verification

Enable GitHub Actions if repository/organization policy disables them. Protect `main` with required
`PHP tests`, `JavaScript tests and build` and `CI gate` checks, strict up-to-date branches (or merge
queue), and code-owner review as described in [deployment.md](deployment.md). Configure contributor
push/PR credentials outside this repository. Test jobs never need production secrets. A separate
reusable production job may run only after successful push CI for the same main SHA and owner
activation. Keep it disabled while reviewing the initial automation PR.
Do not claim remote automation works until its run for the exact commit has been inspected.

To verify locally: run the commands above, inspect the isolation regression, and confirm both
Lobby and game test fixtures are created in memory. For UI features, document manual steps and
expected results using synthetic accounts on a separate development instance. A human should
review the first autonomous PR and confirm required checks block merging on failure.

Suggested first feature: a read-only career progress summary on the career index, displaying
existing completion/mastery data without changing rewards, progression or economy. Define
the displayed fields and empty state before implementation, then cover ownership and rendering.
