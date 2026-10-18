'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const h = require('./_helpers.cjs');

const STUB =
  "<?php\n" +
  "\n" +
  "require_once __DIR__ . '/vendor/elavora/code-standards/src/PhpCsFixer.php';\n" +
  "\n" +
  "return Elavora\\CodeStandards\\PhpCsFixer::config(__DIR__);\n";

function composer() {
  return h.readJson('composer.json');
}

test.describe('composer.json', () => {
  test('elavora/code-standards ^1.0 em require-dev, fora do require', () => {
    const c = composer();
    assert.equal((c['require-dev'] || {})['elavora/code-standards'], '^1.0');
    assert.ok(!('elavora/code-standards' in (c.require || {})));
  });

  test('friendsofphp/php-cs-fixer não entra em nenhuma seção', () => {
    const c = composer();
    for (const section of ['require', 'require-dev', 'suggest', 'conflict', 'replace', 'provide']) {
      assert.ok(!('friendsofphp/php-cs-fixer' in (c[section] || {})), section);
    }
  });

  test('phpstan e phpunit continuam em require-dev', () => {
    const dev = composer()['require-dev'] || {};
    assert.ok('phpstan/phpstan' in dev);
    assert.ok('phpunit/phpunit' in dev);
  });

  test('post-install-cmd e post-update-cmd chamam Composer::sync', () => {
    const s = composer().scripts || {};
    assert.deepEqual(s['post-install-cmd'], ['Elavora\\CodeStandards\\Composer::sync']);
    assert.deepEqual(s['post-update-cmd'], ['Elavora\\CodeStandards\\Composer::sync']);
  });

  test('scripts lint, test, analyse e check mantêm o conteúdo', () => {
    const s = composer().scripts || {};
    assert.equal(s.lint, 'php tools/lint.php');
    assert.equal(s.test, 'phpunit -c phpunit.xml');
    assert.equal(s.analyse, 'phpstan analyse --no-progress --memory-limit=512M');
    assert.deepEqual(s.check, ['@lint', '@test', '@analyse']);
  });

  test('branch-alias dev-main é 2.0-dev', () => {
    const c = composer();
    assert.equal(c.extra && c.extra['branch-alias'] && c.extra['branch-alias']['dev-main'], '2.0-dev');
  });
});

test.describe('.php-cs-fixer.dist.php', () => {
  test('é o stub exato, em bytes (LF e uma quebra de linha final)', () => {
    assert.ok(h.exists('.php-cs-fixer.dist.php'), '.php-cs-fixer.dist.php deveria existir');
    assert.equal(h.readRaw('.php-cs-fixer.dist.php'), STUB);
  });
});

test.describe('.editorconfig', () => {
  test('contém só o bloco gerado pelo elavora/code-standards', () => {
    assert.ok(h.exists('.editorconfig'), '.editorconfig deveria existir');
    const lines = h.read('.editorconfig').replace(/\n+$/, '').split('\n');
    assert.equal(lines[0], '# >>> elavora/code-standards >>>');
    assert.equal(lines[lines.length - 1], '# <<< elavora/code-standards <<<');
    assert.ok(lines.length > 2, 'bloco vazio');
    assert.equal(lines.filter((l) => l === '# >>> elavora/code-standards >>>').length, 1);
    assert.equal(lines.filter((l) => l === '# <<< elavora/code-standards <<<').length, 1);
  });
});

test.describe('.gitignore', () => {
  const lines = () => h.read('.gitignore').split('\n').map((l) => l.trim());

  test('ganha /.elavora/ e /.php-cs-fixer.php', () => {
    const l = lines();
    assert.ok(l.includes('/.elavora/'));
    assert.ok(l.includes('/.php-cs-fixer.php'));
  });

  test('mantém as linhas que já existiam', () => {
    const l = lines();
    for (const old of ['.phpstan.cache/', '.phpunit.cache/', 'composer.lock', 'coverage/', 'vendor/']) {
      assert.ok(l.includes(old), old);
    }
  });

  test('não ignora o stub .php-cs-fixer.dist.php nem o .editorconfig', () => {
    const l = lines();
    for (const bad of ['.php-cs-fixer.dist.php', '/.php-cs-fixer.dist.php', '.editorconfig', '/.editorconfig']) {
      assert.ok(!l.includes(bad), bad);
    }
  });
});
