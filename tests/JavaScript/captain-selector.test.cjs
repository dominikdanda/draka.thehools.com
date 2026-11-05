const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { runInNewContext } = require('node:vm');

function setup({ requestSubmit = true, selector = true } = {}) {
    const listeners = {};
    const pageListeners = {};
    const button = { style: {} };
    const attributes = {};
    const form = {
        submissions: 0,
        querySelector: () => button,
        addEventListener: (name, callback) => { listeners[name] = callback; },
        setAttribute: (name, value) => { attributes[name] = value; },
        removeAttribute: (name) => { delete attributes[name]; },
    };
    const select = {
        value: '1', form, disabled: false,
        addEventListener: (name, callback) => { listeners[name] = callback; },
        setAttribute: (name, value) => { attributes[name] = value; },
        removeAttribute: (name) => { delete attributes[name]; },
    };
    const submit = () => {
        let prevented = false;
        listeners.submit?.({ preventDefault: () => { prevented = true; } });
        if (!prevented) form.submissions++;
    };
    if (requestSubmit) form.requestSubmit = submit;
    const document = {
        getElementById: (id) => id === 'header-captain' && selector ? select : null,
        querySelectorAll: () => [],
        addEventListener: () => {},
    };
    runInNewContext(readFileSync(resolve(__dirname, '../../public/assets/js/thehools.js'), 'utf8'), {
        document,
        window: { addEventListener: (name, callback) => { pageListeners[name] = callback; } },
    });

    return { form, select, button, listeners, pageListeners, submit, attributes };
}

test('changes submit once, keep the POST field enabled, and ignore unchanged values', () => {
    const state = setup();
    assert.equal(state.button.style.display, 'none');
    state.listeners.change();
    assert.equal(state.form.submissions, 0);
    state.select.value = '2';
    state.listeners.change();
    assert.equal(state.form.submissions, 1);
    assert.equal(state.select.disabled, false);
    state.select.value = '3';
    state.listeners.change();
    state.submit();
    assert.equal(state.select.value, '2');
    assert.equal(state.form.submissions, 1);
});

test('back-forward cache restoration allows another switch', () => {
    const state = setup();
    state.select.value = '2';
    state.listeners.change();
    state.pageListeners.pageshow({ persisted: true });
    assert.equal(state.select.value, '1');
    assert.equal(state.attributes['aria-busy'], undefined);
    state.select.value = '3';
    state.listeners.change();
    assert.equal(state.form.submissions, 2);
});

test('unsupported requestSubmit leaves the fallback available', () => {
    const state = setup({ requestSubmit: false });
    assert.equal(state.button.style.display, undefined);
    assert.equal(state.listeners.change, undefined);
});

test('single-Captain pages need no JavaScript selector', () => {
    assert.doesNotThrow(() => setup({ selector: false }));
});
