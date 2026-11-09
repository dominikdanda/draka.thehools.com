const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { accepts } = require('../../scripts/ci-gate.cjs');
const { runSuite } = require('../../scripts/test-javascript.cjs');
const path = require('node:path');

const results = (php, javascript) => ({ php: { result: php }, javascript: { result: javascript } });

test('gate requires both complete successful suites', () => {
    assert.equal(accepts(results('success', 'success')), true);
    for (const state of ['failure', 'cancelled', 'skipped', 'neutral', 'timed_out', 'pending', undefined]) {
        assert.equal(accepts(results(state, 'success')), false, `PHP: ${state}`);
        assert.equal(accepts(results('success', state)), false, `JS: ${state}`);
    }
    for (const input of [null, {}, { php: { result: 'success' } }, { ...results('success', 'success'), extra: { result: 'skipped' } }]) {
        assert.equal(accepts(input), false);
    }
});

test('actual Node suites reject skipped, todo, failed tests and missing suites', () => {
    assert.equal(runSuite([]).success, false);
    const dir = fs.mkdtempSync(path.join('tests', '.draka-ci-'));
    try {
        const file = path.join(dir, 'fixture.test.cjs');
        for (const [body, expected] of [
            ["test('ok', () => {})", true],
            ["test.skip('skipped', () => {})", false],
            ["test.todo('unfinished')", false],
            ["test('failure', () => { throw Error('expected'); })", false],
            ["test('cancelled', () => new Promise(() => {}))", false],
        ]) {
            fs.writeFileSync(file, `const { test } = require('node:test');\n${body}\n`);
            const result = runSuite([file]);
            assert.equal(result.success, expected, JSON.stringify({ body, ...result }));
        }
    } finally {
        fs.rmSync(dir, { recursive: true, force: true });
    }
});

test('CI binds deployment to tested push SHA after an unconditional strict gate', () => {
    const ci = fs.readFileSync('.github/workflows/ci.yml', 'utf8');
    const prepare = ci.indexOf('run: php scripts/prepare-ci.php');
    assert.ok(prepare > ci.indexOf('uses: shivammathur/setup-php@v2'));
    assert.ok(prepare < ci.indexOf('run: composer install'));
    assert.match(ci, /APP_ENV: testing/);
    assert.match(ci, /merge_group:/);
    assert.match(ci, /cancel-in-progress: \$\{\{ github.ref != 'refs\/heads\/main' \}\}/);
    assert.match(ci, /name: CI gate\s+if: \$\{\{ always\(\) \}\}\s+needs: \[php, javascript\]/);
    assert.match(ci, /NEEDS_JSON: \$\{\{ toJSON\(needs\) \}\}/);
    assert.match(ci, /node scripts\/ci-gate.cjs/);
    assert.match(ci, /--fail-on-skipped --fail-on-incomplete --fail-on-risky/);
    assert.match(ci, /needs: \[php, javascript, gate\]/);
    assert.match(ci, /github.event_name == 'push' && github.ref == 'refs\/heads\/main'/);
    assert.match(ci, /vars.DRAKA_DEPLOY_ENABLED == 'true'/);
    assert.match(ci, /uses: \.\/\.github\/workflows\/deploy.yml\s+with:\s+sha: \$\{\{ github.sha \}\}/);
    assert.doesNotMatch(ci, /secrets: inherit|pull_request_target|continue-on-error/);
});

test('deployment is callable only and requires main, production isolation and an exact receipt', () => {
    const deploy = fs.readFileSync('.github/workflows/deploy.yml', 'utf8');
    assert.match(deploy, /workflow_call:/);
    assert.doesNotMatch(deploy, /workflow_dispatch:|workflow_run:|\n  push:|actions\/checkout|secrets: inherit/);
    assert.match(deploy, /github.event_name == 'push' && github.ref == 'refs\/heads\/main'/);
    assert.match(deploy, /inputs.sha == github.sha/);
    assert.match(deploy, /environment: production/);
    assert.match(deploy, /group: draka-production\s+cancel-in-progress: false/);
    assert.match(deploy, /draka-deploy@"\$SSH_HOST" "deploy \$DEPLOY_SHA"/);
    assert.match(deploy, /test "\$receipt" = "deployed \$DEPLOY_SHA"/);
    assert.match(deploy, /StrictHostKeyChecking=yes/);
    assert.match(deploy, /\[\[ "\$DEPLOY_SHA" =~ \^\[0-9a-f\]\{40\}\$ \]\]/);
});
