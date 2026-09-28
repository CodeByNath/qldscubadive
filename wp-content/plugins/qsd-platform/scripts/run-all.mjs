#!/usr/bin/env node
// Runs every contract, regression, and snapshot script declared in
// package.json, in order, and exits non-zero if any fails. Requires a fresh
// build for the mounted regressions (npm test builds first).

import { readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';

const scripts = JSON.parse(readFileSync(new URL('../package.json', import.meta.url), 'utf8')).scripts;
const names = Object.keys(scripts).filter((name) => /^(contract|regression|snapshot):/.test(name));

const failed = [];
for (const name of names) {
  const result = spawnSync('npm', ['run', '-s', name], { stdio: ['ignore', 'pipe', 'pipe'], encoding: 'utf8' });
  const ok = result.status === 0;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}`);
  if (!ok) {
    failed.push(name);
    process.stdout.write((result.stdout ?? '').split('\n').slice(-15).join('\n'));
    process.stderr.write((result.stderr ?? '').split('\n').slice(-15).join('\n'));
  }
}

console.log(`\n${names.length - failed.length}/${names.length} passed.`);
process.exit(failed.length ? 1 : 0);
