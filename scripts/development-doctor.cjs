const { existsSync } = require('node:fs');
const { resolve } = require('node:path');
const { spawnSync } = require('node:child_process');

function inspectEnvironment({ nodeVersion, php, exists }) {
    const issues = [];
    if (Number(nodeVersion.split('.')[0]) < 22) {
        issues.push('Node >=22 is required; use Node 22 to match CI.');
    }

    if (php.error || php.status !== 0) {
        issues.push('PHP CLI is unavailable or failed; install PHP 8.3 and add it to PATH.');
    } else {
        try {
            const info = JSON.parse(php.stdout);
            if (typeof info.version !== 'string' || !Array.isArray(info.extensions)) {
                throw new Error('Invalid PHP probe response');
            }
            const [major, minor] = info.version.split('.').map(Number);
            if (major < 8 || (major === 8 && minor < 2)) {
                issues.push('PHP >=8.2 is required; use PHP 8.3 to match CI.');
            }
            const extensions = info.extensions.map(extension => extension.toLowerCase());
            for (const extension of ['pdo_sqlite', 'mbstring', 'dom', 'xml']) {
                if (!extensions.includes(extension)) {
                    issues.push(`Enable the PHP ${extension} extension for tests.`);
                }
            }
        } catch {
            issues.push('Could not read PHP version/extensions from the CLI probe.');
        }
    }

    if (!exists('vendor/autoload.php')) {
        issues.push('PHP dependencies are missing: run composer install --no-scripts.');
    }
    if (!exists('node_modules/vite/bin/vite.js')) {
        issues.push('JavaScript dependencies are missing: run npm ci.');
    }
    return issues;
}

if (require.main === module) {
    const root = resolve(__dirname, '..');
    const php = spawnSync('php', ['-r', 'echo json_encode(["version" => PHP_VERSION, "extensions" => get_loaded_extensions()]);'], {
        cwd: root,
        encoding: 'utf8',
        timeout: 10000,
        windowsHide: true,
    });
    const issues = inspectEnvironment({
        nodeVersion: process.versions.node,
        php,
        exists: path => existsSync(resolve(root, path)),
    });
    if (issues.length) {
        console.error(issues.map(issue => `- ${issue}`).join('\n'));
        process.exitCode = 1;
    } else {
        console.log('Development prerequisites OK. Run PHP/JS tests and build; see docs/development.md.');
    }
}

module.exports = { inspectEnvironment };
