const { test } = require('node:test');
const assert = require('node:assert/strict');
const { inspectEnvironment } = require('../../scripts/development-doctor.cjs');

const healthy = () => ({
    nodeVersion: '22.14.0',
    php: { status: 0, stdout: JSON.stringify({ version: '8.3.12', extensions: ['pdo_sqlite', 'mbstring', 'dom', 'xml'] }) },
    exists: () => true,
});

test('accepts an installed development environment', () => {
    assert.deepEqual(inspectEnvironment(healthy()), []);
});

test('reports missing PHP without attempting to parse its output', () => {
    const input = healthy();
    input.php = { error: new Error('ENOENT') };
    assert.match(inspectEnvironment(input).join('\n'), /PHP.*unavailable/);
});

test('reports outdated runtimes, missing extensions and dependencies together', () => {
    const input = healthy();
    input.nodeVersion = '20.18.0';
    input.php.stdout = JSON.stringify({ version: '8.1.9', extensions: [] });
    input.exists = () => false;
    const issues = inspectEnvironment(input).join('\n');
    for (const expected of ['Node', 'PHP >=8.2', 'pdo_sqlite', 'mbstring', 'dom', 'xml', 'composer install --no-scripts', 'npm ci']) {
        assert.ok(issues.includes(expected), expected);
    }
});

test('handles failed PHP execution and malformed output', () => {
    for (const php of [{ status: 1, stdout: '' }, { status: 0, stdout: 'invalid' }, { status: 0, stdout: '{}' }]) {
        assert.match(inspectEnvironment({ ...healthy(), php }).join('\n'), /PHP/);
    }
});

test('accepts the minimum PHP version and newer Node releases', () => {
    const input = healthy();
    input.nodeVersion = '24.0.0';
    input.php.stdout = JSON.stringify({ version: '8.2.0', extensions: ['PDO_SQLITE', 'mbstring', 'dom', 'xml'] });
    assert.deepEqual(inspectEnvironment(input), []);
});
