// Fail closed: missing, skipped or cancelled suites never produce a green gate.
function accepts(needs) {
    return needs !== null && typeof needs === 'object'
        && Object.keys(needs).sort().join(',') === 'javascript,php'
        && ['php', 'javascript'].every((job) => needs[job]?.result === 'success');
}

module.exports = { accepts };

if (require.main === module) {
    try {
        if (!accepts(JSON.parse(process.env.NEEDS_JSON))) {
            throw new Error('Both PHP and JavaScript suites must finish successfully.');
        }
    } catch (error) {
        console.error(error.message);
        process.exitCode = 1;
    }
}
