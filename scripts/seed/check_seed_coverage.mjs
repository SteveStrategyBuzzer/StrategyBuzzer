#!/usr/bin/env node
// #93 — Build-time coverage check for the embedded seed pool.
//
// Verifies that every (language, domain, depth band) segment of the
// fallback question pool has AT LEAST `target` questions. Run as part
// of CI / npm scripts:
//
//   node scripts/seed/check_seed_coverage.mjs
//   node scripts/seed/check_seed_coverage.mjs --target 10
//
// Exit code 0 = OK, 1 = at least one segment is below threshold or a
// language file is missing/malformed. Prints a compact report on stderr
// listing every gap so the operator knows exactly what to regenerate
// with scripts/seed/generate_seed_pool.mjs.

import fs from "node:fs";
import path from "node:path";
import url from "node:url";

const __dirname = path.dirname(url.fileURLToPath(import.meta.url));
const SEED_DIR  = path.resolve(__dirname, "../../resources/seed");

// Mirror config/question_bank_profiles.php — kept in sync by hand. If
// either list grows, the generator and PHP config must be updated too.
const LANGS   = ["fr", "en", "es", "it", "de", "pt", "ru", "zh", "ar", "el"];
const DOMAINS = [
  "general", "histoire", "sport", "geographie",
  "art", "cuisine", "science", "cinema", "faune",
];
const BANDS   = ["3-4", "5-6", "7-8", "9-10"];

function parseArgs(argv) {
  const args = { target: 10 };
  for (let i = 2; i < argv.length; i++) {
    const a = argv[i];
    if (a === "--target") args.target = parseInt(argv[++i], 10) || 10;
    else if (a.startsWith("--target=")) args.target = parseInt(a.split("=")[1], 10) || 10;
    else if (a === "-h" || a === "--help") {
      console.log("usage: check_seed_coverage.mjs [--target N]");
      process.exit(0);
    }
  }
  return args;
}

function loadFile(lang) {
  const p = path.join(SEED_DIR, `fallback-questions-${lang}.json`);
  if (!fs.existsSync(p)) return { error: `missing file ${p}` };
  try {
    const raw  = fs.readFileSync(p, "utf8");
    const data = JSON.parse(raw);
    if (!Array.isArray(data.questions)) {
      return { error: `${p}: 'questions' is not an array` };
    }
    return { data, path: p };
  } catch (e) {
    return { error: `${p}: ${e.message}` };
  }
}

function buildCoverage(questions) {
  // We rebuild coverage from the actual rows rather than trusting the
  // _meta block — the file could have been hand-edited and gone stale.
  const cov = {};
  for (const dom of DOMAINS) {
    cov[dom] = {};
    for (const b of BANDS) cov[dom][b] = 0;
  }
  for (const q of questions) {
    const dom  = q.theme || q.domain;
    const band = q.depth_band;
    if (cov[dom] && cov[dom][band] !== undefined) cov[dom][band]++;
  }
  return cov;
}

function main() {
  const { target } = parseArgs(process.argv);
  const gaps = [];
  const summary = [];

  for (const lang of LANGS) {
    const r = loadFile(lang);
    if (r.error) {
      gaps.push({ lang, kind: "file", detail: r.error });
      summary.push(`${lang}: ERROR ${r.error}`);
      continue;
    }
    const cov = buildCoverage(r.data.questions);
    let total = 0, ok = 0, bad = 0;
    for (const dom of DOMAINS) for (const b of BANDS) {
      const n = cov[dom][b];
      total += n;
      if (n >= target) ok++;
      else { bad++; gaps.push({ lang, kind: "segment", domain: dom, band: b, count: n }); }
    }
    summary.push(`${lang}: total=${total} segments_ok=${ok}/${DOMAINS.length * BANDS.length} below_target=${bad}`);
  }

  console.log(`[seed-coverage] target = ${target} questions per (lang, domain, band)`);
  for (const line of summary) console.log("  " + line);

  if (gaps.length === 0) {
    console.log(`[seed-coverage] OK — ${LANGS.length} languages × ${DOMAINS.length} domains × ${BANDS.length} bands all ≥ ${target}`);
    process.exit(0);
  }

  console.error(`\n[seed-coverage] FAIL — ${gaps.length} gap(s):`);
  for (const g of gaps) {
    if (g.kind === "file") console.error(`  ${g.lang}: ${g.detail}`);
    else console.error(`  ${g.lang}/${g.domain}/${g.band} → ${g.count}/${target}`);
  }
  console.error(`\nFix: node scripts/seed/generate_seed_pool.mjs --langs <lang> --target ${target}`);
  process.exit(1);
}

main();
