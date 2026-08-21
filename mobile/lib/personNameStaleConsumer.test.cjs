const assert = require('node:assert/strict');
const { readdirSync, readFileSync, statSync } = require('node:fs');
const { join, relative } = require('node:path');
const test = require('node:test');

const root = join(__dirname, '..');

function productionSources() {
  const files = [];
  const visit = (path) => {
    for (const entry of readdirSync(path)) {
      const child = join(path, entry);
      if (statSync(child).isDirectory()) visit(child);
      else if (/\.(ts|tsx)$/.test(entry) && !/\.test\.(ts|tsx)$/.test(entry)) files.push(child);
    }
  };

  [join(root, 'app'), join(root, 'components'), join(root, 'lib')].forEach(visit);
  return files;
}

function matchingLines(pattern) {
  const matches = [];
  for (const file of productionSources()) {
    for (const line of readFileSync(file, 'utf8').split(/\r?\n/)) {
      pattern.lastIndex = 0;
      if (pattern.test(line)) matches.push(`${relative(root, file).replaceAll('\\', '/')}:${line.trim()}`);
    }
  }
  return matches.sort();
}

test('mobile direct person name reads stay at explicit compatibility boundaries', () => {
  assert.deepEqual(matchingLines(/\b(?:[A-Za-z_$][\w$]*(?:user|patient)|user|patient)\??\.name\b/gi), [
    "app/reports.tsx:<Text className=\"text-sm font-bold text-emerald-800\">{sheet.user.name ?? 'Staff'}</Text>",
  ]);
  assert.deepEqual(matchingLines(/\bperson\.name\b/gi), [
    'lib/personName.ts:if (person.name?.trim()) return person.name;',
  ]);

  const attributionCounts = Object.fromEntries(
    matchingLines(/\b(?:actor|creator|created_by|author|owner|createdBy|updatedBy|user)\??\.name\b/gi)
      .reduce((files, match) => {
        const file = match.slice(0, match.indexOf(':'));
        files.set(file, (files.get(file) ?? 0) + 1);
        return files;
      }, new Map()),
  );
  assert.deepEqual(attributionCounts, {
    'app/(tabs)/index.tsx': 2,
    'app/reports.tsx': 1,
    'components/AnnouncementsScreen.tsx': 4,
  });
});

test('mobile profile uses split fields and the shared compatibility adapter', () => {
  const profile = readFileSync(join(root, 'app', 'profile.tsx'), 'utf8');
  assert.match(profile, /changedPersonNameFields/);
  assert.match(profile, /personNameFormValues/);
  assert.doesNotMatch(profile, /\buser\??\.name\b/);
});

test('mobile operational names remain unrelated entity names', () => {
  for (const file of ['app/(tabs)/menu.tsx', 'app/(tabs)/prep.tsx', 'app/(tabs)/procurement.tsx']) {
    const source = readFileSync(join(root, file), 'utf8');
    assert.match(source, /\.name\b/, `${file} must retain operational entity names`);
    assert.doesNotMatch(source, /first_name|last_name/, `${file} must stay outside the person-name migration`);
  }
});
