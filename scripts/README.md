# Development environment check

Run `node scripts/development-doctor.cjs` from the repository root before running
tests. You can also invoke the script by absolute path from another directory.

The read-only check reports unsupported Node/PHP versions, missing PHP extensions
needed by the SQLite test suite, and missing Composer/Vite dependencies. It exits
with code 1 when a prerequisite is missing, or 0 when all checks pass. PHP probes
time out after ten seconds. Use PHP 8.3 and Node 22 to match CI.

The check does not load Laravel, read `.env`, install packages, clear caches or
connect to databases. Passing does not replace Composer platform validation, tests
or the asset build. Follow `docs/development.md` for the complete safe workflow.

Regression tests run with `npm test` or, individually,
`node --test tests/JavaScript/development-doctor.test.cjs`.
