#!/usr/bin/env node
/**
 * Build LMFT Law & Ethics Flashcard Study Center deck from export JSON array.
 *
 * Usage:
 *   node scripts/build-lmft-law-ethics-flashcard-study-center.js
 *   node scripts/build-lmft-law-ethics-flashcard-study-center.js --source=scripts/_lmft-law-ethics-flashcards-export.json
 *   node scripts/build-lmft-law-ethics-flashcard-study-center.js --dry-run
 */

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const defaultSource =
  path.join(root, 'scripts', '_lmft-law-ethics-flashcards-export.json');
const defaultTarget = path.join(
  root,
  'assets',
  'course-materials',
  'lmft-law-ethics',
  'study-tools',
  'flashcard-study-center.json'
);

const args = process.argv.slice(2);
const dryRun = args.includes('--dry-run');
const sourceArg = args.find((a) => a.startsWith('--source='));
const targetArg = args.find((a) => a.startsWith('--target='));
const source = sourceArg ? sourceArg.split('=').slice(1).join('=') : defaultSource;
const target = targetArg ? targetArg.split('=').slice(1).join('=') : defaultTarget;

function sanitizeKey(label) {
  return String(label || '')
    .toLowerCase()
    .replace(/&/g, 'and')
    .replace(/\//g, '-')
    .replace(/[^a-z0-9_-]+/g, '-')
    .replace(/^-+|-+$/g, '') || 'general';
}

function sortOrderFromId(id) {
  const m = String(id || '').match(/(\d+)\s*$/);
  return m ? parseInt(m[1], 10) : 0;
}

function mapExportCard(row) {
  const domainLabel = String(row.Topic || row.topic || '').trim();
  const domainKey = sanitizeKey(domainLabel);
  const id = String(row['Flashcard ID'] || row.id || '').trim();

  return {
    id,
    sort_order: sortOrderFromId(id),
    domain: domainKey,
    domain_label: domainLabel,
    front: String(row.Prompt || row.front || ''),
    back: String(row.Back || row.back || ''),
    memory_cue: String(row['Memory Cue'] || row.memory_cue || ''),
    meta: {
      card_type: String(row['Card Type'] || row.card_type || ''),
      concept: String(row['Display Concept'] || row.concept || ''),
      workbook_number: Number(row['Workbook Number'] ?? row.workbook_number ?? 0),
      chapter_number: Number(row['Chapter Number'] ?? row.chapter_number ?? 0),
      chapter_title: String(row['Chapter Title'] || row.chapter_title || ''),
      part: Number(row.Part ?? row.part ?? 1),
      total_parts: Number(row['Total Parts'] ?? row.total_parts ?? 1),
      source_accuracy: String(row['Source Accuracy'] || row.source_accuracy || ''),
      version: String(row.Version || row.version || ''),
    },
  };
}

function buildDomains(cards) {
  const domains = new Map();
  for (const card of cards) {
    const key = card.domain;
    if (!key) continue;
    if (!domains.has(key)) {
      domains.set(key, {
        key,
        label: card.domain_label || key,
        order: domains.size + 1,
      });
    }
  }
  return Array.from(domains.values()).sort((a, b) => a.order - b.order);
}

function loadExportRows(filePath) {
  const raw = fs.readFileSync(filePath, 'utf8');
  const data = JSON.parse(raw);
  if (Array.isArray(data)) {
    return data;
  }
  if (data && Array.isArray(data.cards)) {
    return data.cards;
  }
  throw new Error(`Invalid export JSON (expected array): ${filePath}`);
}

function main() {
  if (!fs.existsSync(source)) {
    console.error(`Source not readable: ${source}`);
    process.exit(1);
  }

  const rows = loadExportRows(source);
  const cards = [];
  const seenIds = new Set();

  for (let i = 0; i < rows.length; i++) {
    const row = rows[i];
    if (!row || typeof row !== 'object') {
      console.error(`Invalid export row at index ${i}`);
      process.exit(1);
    }
    const card = mapExportCard(row);
    if (!card.id) {
      console.error(`Missing Flashcard ID at index ${i}`);
      process.exit(1);
    }
    if (!card.front.trim() || !card.back.trim()) {
      console.error(`Card ${card.id} is missing front/back text.`);
      process.exit(1);
    }
    if (seenIds.has(card.id)) {
      console.error(`Duplicate card id: ${card.id}`);
      process.exit(1);
    }
    seenIds.add(card.id);
    cards.push(card);
  }

  cards.sort((a, b) => a.sort_order - b.sort_order || a.id.localeCompare(b.id));

  const domains = buildDomains(cards);
  const domainKeys = new Set(domains.map((d) => d.key));
  let missingDomain = 0;
  let missingCue = 0;

  for (const card of cards) {
    if (!domainKeys.has(card.domain)) {
      missingDomain++;
    }
    if (!card.memory_cue.trim()) {
      missingCue++;
    }
  }

  const payload = {
    program: 'lmft-law-ethics',
    title: 'LMFT California Law & Ethics — Flashcard Study Center',
    version: '1.1',
    expected_total: 807,
    domains,
    cards,
  };

  console.log(`Cards: ${cards.length}`);
  console.log(`Domains declared: ${domains.length}`);
  console.log(`Cards with undeclared domain keys: ${missingDomain}`);
  console.log(`Cards missing memory_cue: ${missingCue}`);

  if (807 !== cards.length) {
    console.warn(
      `WARNING: LMFT California Law & Ethics deck expected 807 cards; source has ${cards.length}.`
    );
  }

  if (dryRun) {
    console.log('Dry run only — no file written.');
    return;
  }

  fs.mkdirSync(path.dirname(target), { recursive: true });
  fs.writeFileSync(target, JSON.stringify(payload, null, 2) + '\n', 'utf8');
  console.log(`Wrote ${target}`);
}

main();
