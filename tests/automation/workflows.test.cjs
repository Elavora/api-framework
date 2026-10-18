'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const h = require('./_helpers.cjs');

const RELEASE = h.lines([
    'name: Release',
    '',
    'on:',
    '  pull_request:',
    '    types: [closed]',
    '    branches: [main]',
    '',
    'permissions:',
    '  contents: read',
    '',
    'jobs:',
    '  release:',
    '    if: github.event.pull_request.merged == true',
    '    permissions:',
    '      contents: write',
    '      pull-requests: read',
    '      id-token: write',
    '    uses: Elavora/.github/.github/workflows/release.yml@3e8259291c0d3f7c031695fe63d714169792c454 # 1.2.0',
    '    secrets:',
    '      PACKAGIST_WEBHOOK_URL: ${{ secrets.PACKAGIST_WEBHOOK_URL }}',
]);

const SYNC_LABELS = h.lines([
    'name: Sincronizar labels',
    '',
    'on:',
    '  schedule:',
    "    - cron: '17 3 * * 1'",
    '  workflow_dispatch:',
    '',
    'permissions:',
    '  contents: read',
    '',
    'jobs:',
    '  sync:',
    '    permissions:',
    '      contents: read',
    '      issues: write',
    '      id-token: write',
    '    uses: Elavora/.github/.github/workflows/sync-labels.yml@3e8259291c0d3f7c031695fe63d714169792c454 # 1.2.0',
]);

const QUALITY = h.lines([
    'name: Qualidade',
    '',
    'on:',
    '  pull_request:',
    '    branches: [main]',
    '  push:',
    '    branches: [main]',
    '',
    'permissions:',
    '  contents: read',
    '',
    'jobs:',
    '  quality:',
    '    permissions:',
    '      contents: write',
    '      pull-requests: write',
    '      id-token: write',
    '    uses: Elavora/.github/.github/workflows/php-quality.yml@3e8259291c0d3f7c031695fe63d714169792c454 # 1.2.0',
    '    with:',
    '      image: ghcr.io/elavora/php-dev:8.3-0.3.0',
]);

const CODEQL = h.lines([
    'name: CodeQL',
    '',
    'on:',
    '  pull_request:',
    '    branches: [main]',
    '    types: [opened, synchronize, reopened, ready_for_review]',
    '  merge_group:',
    '  schedule:',
    '    - cron: "0 9 * * 1"',
    '  workflow_dispatch:',
    '',
    'permissions:',
    '  contents: read',
    '  security-events: write',
    '',
    'jobs:',
    '  analyze:',
    '    name: CodeQL',
    '    runs-on: ubuntu-latest',
    '    steps:',
    '      - uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1',
    '        with:',
    '          persist-credentials: false',
    '      - uses: github/codeql-action/init@e4fba868fa4b1b91e1fdab776edc8cfbe6e9fb81 # v4',
    '        with:',
    '          languages: actions',
    '      - uses: github/codeql-action/analyze@e4fba868fa4b1b91e1fdab776edc8cfbe6e9fb81 # v4',
]);

const DEPENDENCY_REVIEW = h.lines([
    'name: Dependency Review',
    '',
    'on:',
    '  pull_request:',
    '    branches: [main]',
    '',
    'permissions:',
    '  contents: read',
    '',
    'jobs:',
    '  dependency-review:',
    '    name: Dependency Review',
    '    runs-on: ubuntu-latest',
    '    steps:',
    '      - uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1',
    '        with:',
    '          persist-credentials: false',
    '      - uses: actions/dependency-review-action@2031cfc080254a8a887f58cffee85186f0e49e48 # v4',
    '        with:',
    '          fail-on-severity: high',
]);

const AUTOMATION = h.lines([
    'name: Automação',
    '',
    'on:',
    '  pull_request:',
    '  push:',
    '    branches: [main]',
    '',
    'permissions:',
    '  contents: read',
    '',
    'jobs:',
    '  tests:',
    '    name: Automação',
    '    runs-on: ubuntu-latest',
    '    timeout-minutes: 10',
    '    steps:',
    '      - uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1',
    '        with:',
    '          persist-credentials: false',
    '',
    '      - name: Testes da automação',
    '        run: docker run --rm -v "$PWD:/w:ro" -w /w node:22-alpine node --test "tests/automation/*.test.cjs"',
    '',
    '      - name: actionlint',
    '        run: docker run --rm -v "$PWD:/w:ro" -w /w rhysd/actionlint:1.7.12 -no-color',
]);

const EXPECTED = {
    '.github/workflows/release.yml': RELEASE,
    '.github/workflows/sync-labels.yml': SYNC_LABELS,
    '.github/workflows/quality.yml': QUALITY,
    '.github/workflows/codeql.yml': CODEQL,
    '.github/workflows/dependency-review.yml': DEPENDENCY_REVIEW,
    '.github/workflows/automation.yml': AUTOMATION,
};

test.describe('conteúdo exato dos workflows', () => {
    for (const [file, content] of Object.entries(EXPECTED)) {
        test(`${file} tem o conteúdo documentado`, () => {
            h.assertExactContent(assert, file, content);
        });
    }
});

test.describe('release.yml', () => {
    test('não passa with (package_name, packagist) e repassa só PACKAGIST_WEBHOOK_URL', () => {
        const text = h.read('.github/workflows/release.yml');
        assert.doesNotMatch(text, /^\s*with:/m);
        assert.doesNotMatch(text, /package_name/);
        assert.doesNotMatch(text, /packagist:\s*false/);
        const secrets = text.split(/^\s*secrets:\s*$/m)[1] || '';
        const names = [...secrets.matchAll(/^\s+([A-Z0-9_]+):/gm)].map((m) => m[1]);
        assert.deepEqual(names, ['PACKAGIST_WEBHOOK_URL']);
    });
});

test.describe('sync-labels.yml', () => {
    test('sem with (prune) e sem secrets', () => {
        const text = h.read('.github/workflows/sync-labels.yml');
        assert.doesNotMatch(text, /^\s*with:/m);
        assert.doesNotMatch(text, /prune/);
        assert.doesNotMatch(text, /^\s*secrets:/m);
    });
});

test.describe('quality.yml', () => {
    test('job quality chama php-quality.yml só com a imagem', () => {
        const text = h.read('.github/workflows/quality.yml');
        const jobs = h.splitJobs(text);
        assert.deepEqual(Object.keys(jobs), ['quality']);
        assert.match(jobs.quality, /uses: Elavora\/\.github\/\.github\/workflows\/php-quality\.yml@/);
        assert.match(jobs.quality, /^\s+image: ghcr\.io\/elavora\/php-dev:8\.3-0\.3\.0$/m);
        assert.match(jobs.quality, /contents: write/);
        assert.match(jobs.quality, /pull-requests: write/);
    });

    test('não mexe em cobertura, audit ou dependências mínimas, nem tem job PHP local ou concurrency', () => {
        const text = h.read('.github/workflows/quality.yml');
        assert.doesNotMatch(text, /coverage|cobertura|audit|lowest|dependency-versions/i);
        assert.doesNotMatch(text, /setup-php|runs-on|matrix|^concurrency:/m);
    });
});

test.describe('regras gerais dos workflows', () => {
    test('os seis workflows documentados existem', () => {
        for (const file of Object.keys(EXPECTED)) {
            assert.ok(h.exists(file), `${file} deveria existir`);
        }
    });

    test('nenhum arquivo em .github/ cita o SHA antigo nem o prefixo 1e0f42a', () => {
        for (const file of h.listFiles('.github')) {
            const text = h.read(file);
            assert.ok(!text.includes(h.OLD_SHA), `${file} cita o SHA antigo`);
            assert.ok(!text.includes('1e0f42a'), `${file} cita o prefixo 1e0f42a`);
        }
    });

    test('todo uses central termina em @<SHA 1.2.0> # 1.2.0', () => {
        let found = 0;
        for (const file of h.workflowFiles()) {
            for (const uses of h.usesLines(h.read(file))) {
                if (!uses.startsWith('Elavora/.github/')) continue;
                found++;
                assert.match(uses, /^Elavora\/\.github\/\.github\/workflows\/[A-Za-z0-9_.-]+\.ya?ml@/, `${file}: ${uses}`);
                assert.ok(uses.endsWith(h.CENTRAL_SUFFIX), `${file}: ${uses} não termina em ${h.CENTRAL_SUFFIX}`);
                assert.equal(uses.split('@').length, 2, `${file}: ${uses}`);
            }
        }
        assert.ok(found >= 3, 'esperados pelo menos três chamadores centrais (release, sync-labels, quality)');
    });

    test('nenhum chamador passa automation_ref nem usa secrets: inherit', () => {
        for (const file of h.workflowFiles()) {
            const text = h.read(file);
            assert.doesNotMatch(text, /automation_ref/, file);
            assert.doesNotMatch(text, /secrets:\s*inherit/, file);
        }
    });

    test('todo job que chama Elavora/.github concede id-token: write', () => {
        for (const file of h.workflowFiles()) {
            const jobs = h.splitJobs(h.read(file));
            for (const [id, body] of Object.entries(jobs)) {
                if (!/uses:\s*Elavora\/\.github\//.test(body)) continue;
                assert.match(body, /^\s+id-token:\s*write\s*$/m, `${file}: job ${id} sem id-token: write`);
            }
        }
    });

    test('actions de terceiros são fixadas por SHA de 40 caracteres com versão em comentário', () => {
        for (const file of h.workflowFiles()) {
            for (const uses of h.usesLines(h.read(file))) {
                if (uses.startsWith('Elavora/.github/') || uses.startsWith('./')) continue;
                assert.match(uses, /^[A-Za-z0-9_.-]+\/[A-Za-z0-9_./-]+@[0-9a-f]{40} # \S+$/, `${file}: ${uses}`);
            }
        }
    });

    test('nenhum workflow local usa shivammathur/setup-php e não existe packagist-sync.yml', () => {
        for (const file of h.workflowFiles()) {
            assert.doesNotMatch(h.read(file), /shivammathur\/setup-php/, file);
        }
        assert.ok(!h.exists('.github/workflows/packagist-sync.yml'));
        assert.ok(!h.exists('.github/workflows/packagist-sync.yaml'));
    });
});
