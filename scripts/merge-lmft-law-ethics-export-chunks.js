#!/usr/bin/env node
/**
 * Merge chunked export JSON arrays into scripts/_lmft-law-ethics-flashcards-export.json
 *
 * Usage:
 *   node scripts/merge-lmft-law-ethics-export-chunks.js
 */

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const chunkDir = path.join(root, 'scripts', '_lmft-law-ethics-export-chunks');
const out = path.join(root, 'scripts', '_lmft-law-ethics-flashcards-export.json');

const files = fs
  .readdirSync(chunkDir)
  .filter((f) => f.endsWith('.json'))
  .sort();

const all = [];
const seen = new Set();

for (const file of files) {
  const rows = JSON.parse(fs.readFileSync(path.join(chunkDir, file), 'utf8'));
  if (!Array.isArray(rows)) {
    console.error(`Chunk is not an array: ${file}`);
    process.exit(1);
  }
  for (const row of rows) {
    const id = row['Flashcard ID'];
    if (seen.has(id)) {
      console.error(`Duplicate in merge: ${id} (${file})`);
      process.exit(1);
    }
    seen.add(id);
    all.push(row);
  }
}

all.sort((a, b) => {
  const na = parseInt(String(a['Flashcard ID']).replace(/\D/g, ''), 10);
  const nb = parseInt(String(b['Flashcard ID']).replace(/\D/g, ''), 10);
  return na - nb;
});

fs.writeFileSync(out, JSON.stringify(all, null, 2) + '\n', 'utf8');
console.log(`Merged ${all.length} cards from ${files.length} chunks -> ${out}`);
