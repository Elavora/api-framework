'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const h = require('./_helpers.cjs');

function namesIn(rel) {
    const dir = h.abs(rel);
    return fs.existsSync(dir) ? fs.readdirSync(dir) : [];
}

test.describe('arquivos removidos', () => {
    test('não existe CONTRIBUTING.md na raiz, em .github/ ou em docs/', () => {
        for (const dir of ['.', '.github', 'docs']) {
            const found = namesIn(dir).filter((n) => n.toLowerCase() === 'contributing.md');
            assert.deepEqual(found, [], dir);
        }
    });

    test('não existe template de PR próprio (qualquer caixa) em .github/, na raiz ou em docs/', () => {
        for (const dir of ['.', '.github', 'docs']) {
            const found = namesIn(dir).filter((n) => n.toLowerCase() === 'pull_request_template.md');
            assert.deepEqual(found, [], dir);
        }
        for (const dir of ['.', '.github', 'docs']) {
            const found = namesIn(dir).filter((n) => n.toLowerCase() === 'pull_request_template');
            assert.deepEqual(found, [], `${dir} tem pasta PULL_REQUEST_TEMPLATE`);
        }
    });

    test('não existe tools/lint.php nem a pasta tools/', () => {
        assert.ok(!h.exists('tools/lint.php'), 'tools/lint.php deveria ter sido apagado');
        assert.ok(!h.exists('tools'), 'a pasta tools/ deveria ter sido apagada');
    });

    test('não existe packagist-sync.yml em .github/workflows/', () => {
        const found = namesIn('.github/workflows').filter((n) => /^packagist-sync\.ya?ml$/i.test(n));
        assert.deepEqual(found, []);
    });
});
