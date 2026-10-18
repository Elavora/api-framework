'use strict';

const fs = require('node:fs');
const path = require('node:path');

const ROOT = path.resolve(__dirname, '..', '..');
const SHA_120 = '3e8259291c0d3f7c031695fe63d714169792c454';
const OLD_SHA = '1e0f42a92a4aafc35522709c30bc4e83acab82f1';
const IMAGE = 'ghcr.io/elavora/php-dev:8.3-latest';
const CENTRAL_SUFFIX = `@${SHA_120} # 1.2.0`;

function abs(rel) {
    return path.join(ROOT, rel);
}

function exists(rel) {
    return fs.existsSync(abs(rel));
}

function readRaw(rel) {
    return fs.readFileSync(abs(rel), 'utf8');
}

/** Lê como texto com fim de linha normalizado para LF. */
function read(rel) {
    return readRaw(rel).replace(/\r\n/g, '\n');
}

function readJson(rel) {
    return JSON.parse(readRaw(rel));
}

/** Lista recursiva de arquivos (caminhos relativos à raiz, com /). */
function listFiles(relDir) {
    const out = [];
    const base = abs(relDir);
    if (!fs.existsSync(base)) return out;
    const walk = (dir) => {
        for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
            const full = path.join(dir, entry.name);
            if (entry.isDirectory()) walk(full);
            else out.push(path.relative(ROOT, full).split(path.sep).join('/'));
        }
    };
    walk(base);
    return out.sort();
}

function workflowFiles() {
    return listFiles('.github/workflows').filter((f) => /\.ya?ml$/.test(f));
}

/**
 * Monta o texto esperado de um arquivo a partir das linhas (LF, uma quebra de linha final).
 * As linhas ficam em strings para que a indentação de 2 espaços do YAML não conte
 * como indentação do .cjs (o .editorconfig exige 4 espaços aqui).
 */
function lines(list) {
    return list.join('\n') + '\n';
}

/** Compara o conteúdo do arquivo com o esperado (LF, uma quebra de linha final). */
function assertExactContent(assert, rel, expected) {
    assert.ok(exists(rel), `${rel} deveria existir`);
    const actual = read(rel);
    assert.equal(actual, expected, `${rel} difere do conteúdo exato documentado`);
}

/**
 * Divide o bloco `jobs:` de um workflow em { id: texto do job }.
 * Parser mínimo por indentação, suficiente para os chamadores deste repositório.
 */
function splitJobs(text) {
    const lines = text.split('\n');
    const jobs = {};
    let inJobs = false;
    let jobIndent = null;
    let current = null;
    for (const line of lines) {
        if (/^jobs:\s*$/.test(line)) {
            inJobs = true;
            continue;
        }
        if (!inJobs) continue;
        if (/^\S/.test(line)) break; // outra chave de topo
        const m = line.match(/^(\s+)([A-Za-z0-9_-]+):\s*$/);
        if (m && (jobIndent === null || m[1].length === jobIndent)) {
            jobIndent = m[1].length;
            current = m[2];
            jobs[current] = '';
            continue;
        }
        if (current) jobs[current] += line + '\n';
    }
    return jobs;
}

function usesLines(text) {
    return text
        .split('\n')
        .map((l) => l.match(/^\s*(?:-\s+)?uses:\s*(.+?)\s*$/))
        .filter(Boolean)
        .map((m) => m[1]);
}

module.exports = {
    ROOT,
    SHA_120,
    OLD_SHA,
    IMAGE,
    CENTRAL_SUFFIX,
    abs,
    exists,
    read,
    readRaw,
    readJson,
    listFiles,
    workflowFiles,
    lines,
    assertExactContent,
    splitJobs,
    usesLines,
};
