const { spawnSync } = require('node:child_process');
const { readdirSync } = require('node:fs');

function runSuite(files) {
    if (files.length === 0) return { success: false, output: '', error: 'No JavaScript test files.' };
    // Nested regression fixtures must start an independent Node test runner.
    const env = { ...process.env };
    delete env.NODE_TEST_CONTEXT;
    const result = spawnSync(process.execPath, ['--test', '--test-reporter=tap', ...files], {
        env, encoding: 'utf8', maxBuffer: 16 * 1024 * 1024,
    });
    const output = result.stdout || '';
    const count = (name) => [...output.matchAll(new RegExp(`^# ${name} (\\d+)\\r?$`, 'gm'))].at(-1)?.[1];
    const success = files.length > 0 && !result.error && result.status === 0
        && Number(count('tests')) > 0
        && ['fail', 'cancelled', 'skipped', 'todo'].every((name) => count(name) === '0');
    return { success, output, error: result.stderr || result.error?.message || '' };
}

module.exports = { runSuite };

if (require.main === module) {
    const files = readdirSync('tests/JavaScript').filter((name) => name.endsWith('.test.cjs'))
        .sort().map((name) => `tests/JavaScript/${name}`);
    const result = runSuite(files);
    process.stdout.write(result.output);
    process.stderr.write(result.error);
    if (!result.success) {
        console.error('JavaScript requires a non-empty suite with zero failed, cancelled, skipped or todo tests.');
        process.exitCode = 1;
    }
}
