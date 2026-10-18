'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const h = require('./_helpers.cjs');

const FILE = '.github/dependabot.yml';

const DEPENDABOT = `
version: 2
updates:
  - package-ecosystem: "composer"
    directory: "/"
    schedule:
      interval: "weekly"
      day: "monday"
      time: "09:00"
      timezone: "America/Sao_Paulo"
    open-pull-requests-limit: 5
    labels: ["dependencies"]
    commit-message:
      prefix: "chore"
      prefix-development: "chore"
      include: "scope"

  - package-ecosystem: "github-actions"
    directory: "/"
    schedule:
      interval: "weekly"
      day: "monday"
      time: "09:00"
      timezone: "America/Sao_Paulo"
    open-pull-requests-limit: 5
    labels: ["dependencies"]
    commit-message:
      prefix: "ci"
      include: "scope"
`;

/** Divide as entradas de `updates` pelo marcador "  - package-ecosystem:". */
function entries() {
  const text = h.read(FILE);
  return text
    .split(/^\s*-\s+package-ecosystem:/m)
    .slice(1)
    .map((chunk) => {
      const eco = chunk.match(/^\s*"?([^"\s]+)"?/)[1];
      return { eco, body: chunk };
    });
}

test.describe('.github/dependabot.yml', () => {
  test('tem o conteúdo exato documentado', () => {
    h.assertExactContent(assert, FILE, DEPENDABOT);
  });

  test('só existem as entradas composer e github-actions', () => {
    assert.deepEqual(entries().map((e) => e.eco), ['composer', 'github-actions']);
  });

  test('toda entrada tem labels só com "dependencies" (label do catálogo)', () => {
    for (const { eco, body } of entries()) {
      const m = body.match(/^\s+labels:\s*\[(.*)\]\s*$/m);
      assert.ok(m, `${eco} sem labels`);
      const labels = m[1].split(',').map((s) => s.trim().replace(/^"|"$/g, '')).filter(Boolean);
      assert.deepEqual(labels, ['dependencies'], eco);
    }
  });

  test('toda entrada tem commit-message com include: "scope" e o prefixo certo', () => {
    const expected = {
      composer: { prefix: 'chore', dev: 'chore' },
      'github-actions': { prefix: 'ci', dev: null },
    };
    for (const { eco, body } of entries()) {
      assert.match(body, /^\s+commit-message:\s*$/m, `${eco} sem commit-message`);
      assert.match(body, /^\s+include:\s*"scope"\s*$/m, eco);
      const prefix = body.match(/^\s+prefix:\s*"([^"]*)"\s*$/m);
      assert.equal(prefix && prefix[1], expected[eco].prefix, eco);
      const dev = body.match(/^\s+prefix-development:\s*"([^"]*)"\s*$/m);
      assert.equal(dev ? dev[1] : null, expected[eco].dev, eco);
    }
  });
});
