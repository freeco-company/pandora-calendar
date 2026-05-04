#!/usr/bin/env node
/**
 * i18n Consistency Check (Layer B — Static Analysis)
 *
 * Why: catches the "{name}/{n}/{total} placeholder not interpolated" class of
 * bug at build-time. Recent regression: SkillPath / BodyDex strings rendered
 * with literal `{name}` because the t() call was missing the params object.
 *
 * Three checks:
 *   1. all 4 dicts (zh-TW, en, zh-TW-inclusive, en-inclusive) have identical key sets
 *   2. for every key, all dicts share the same `{placeholder}` set
 *      (so a translator can't drop a `{n}` from `en` while `zh-TW` still expects it)
 *   3. every `t('foo', { x, y })` callsite in views must:
 *      a) reference a key that exists in zh-TW canonical
 *      b) supply a params object whose keys cover ALL placeholders the dict
 *         string contains (catches "forgot to pass {name}")
 *
 * No vue-i18n / unplugin needed — we parse the four dict files with regex.
 * Exit 1 on any violation.
 */
import { readFileSync, readdirSync, statSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const FRONTEND_ROOT = join(__dirname, '..')
const LOCALES_DIR = join(FRONTEND_ROOT, 'src/locales')
const VIEWS_DIR = join(FRONTEND_ROOT, 'src/views')

const DICT_FILES = ['zh-TW.ts', 'en.ts', 'zh-TW-inclusive.ts', 'en-inclusive.ts']

/* ---------- 1. parse dict files ---------- */

/**
 * Match keys like `  foo_bar: 'value',` or `  foo_bar: "value",` possibly
 * spanning into back-tick / multi-line strings. We grab the value as the
 * raw text up to a comma at end of (logical) line.
 *
 * Simplification: we only support single-line single/double-quote values.
 * Wave 13 narrative strings use back-ticks; we'll handle those as needed.
 */
function parseDict(filePath) {
  const src = readFileSync(filePath, 'utf8')
  const dict = {}
  // Match: key: '...' or key: "..." or key: `...` (single-line back-tick only)
  const re = /^\s{2}([a-z_][a-z0-9_]*)\s*:\s*(['"`])((?:\\\2|(?!\2).)*)\2\s*,?\s*$/gm
  let m
  while ((m = re.exec(src)) !== null) {
    const key = m[1]
    const value = m[3]
    dict[key] = value
  }
  return dict
}

const dicts = Object.fromEntries(
  DICT_FILES.map((f) => [f, parseDict(join(LOCALES_DIR, f))]),
)

/* ---------- 2. extract placeholders from a string ---------- */

function placeholdersOf(str) {
  // Both `{var}` and `{{ var }}` accepted (matches useTone.t() impl)
  const set = new Set()
  const re = /\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}|\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}/g
  let m
  while ((m = re.exec(str)) !== null) {
    set.add(m[1] ?? m[2])
  }
  return set
}

/* ---------- 3. check key parity + placeholder parity ---------- */

const violations = []
const canonical = dicts['zh-TW.ts']
const canonicalKeys = new Set(Object.keys(canonical))

for (const f of DICT_FILES) {
  if (f === 'zh-TW.ts') continue
  const d = dicts[f]
  const keys = new Set(Object.keys(d))
  for (const k of canonicalKeys) {
    if (!keys.has(k)) {
      violations.push(`[parity] ${f} missing key \`${k}\``)
    }
  }
  for (const k of keys) {
    if (!canonicalKeys.has(k)) {
      violations.push(`[parity] ${f} has extra key \`${k}\` (not in zh-TW canonical)`)
    }
  }
}

for (const k of canonicalKeys) {
  const expectPh = placeholdersOf(canonical[k])
  for (const f of DICT_FILES) {
    if (f === 'zh-TW.ts') continue
    const v = dicts[f][k]
    if (v === undefined) continue
    const got = placeholdersOf(v)
    const missing = [...expectPh].filter((p) => !got.has(p))
    const extra = [...got].filter((p) => !expectPh.has(p))
    if (missing.length || extra.length) {
      violations.push(
        `[placeholder] ${f}::${k} mismatch — expected {${[...expectPh].join(',')}} got {${[...got].join(',')}}`,
      )
    }
  }
}

/* ---------- 4. scan view files for t('key', { params }) ---------- */

function walk(dir) {
  const out = []
  for (const ent of readdirSync(dir)) {
    const p = join(dir, ent)
    const st = statSync(p)
    if (st.isDirectory()) out.push(...walk(p))
    else if (ent.endsWith('.vue')) out.push(p)
  }
  return out
}

const viewFiles = walk(VIEWS_DIR)

// match t('key') and t('key', {...}); also t("key") variants
const tCallRe = /\bt\(\s*(['"])([a-zA-Z_][a-zA-Z0-9_]*)\1\s*(?:,\s*(\{[^}]*\}))?\s*\)/g

for (const file of viewFiles) {
  const src = readFileSync(file, 'utf8')
  let m
  while ((m = tCallRe.exec(src)) !== null) {
    const key = m[2]
    const paramsRaw = m[3] // e.g. "{ n: collected, total }"

    if (!canonicalKeys.has(key)) {
      violations.push(`[missing-key] ${relativeTo(file)} uses t('${key}') but no such key in zh-TW canonical`)
      continue
    }

    const expectPh = placeholdersOf(canonical[key])
    if (expectPh.size === 0) continue

    if (!paramsRaw) {
      violations.push(
        `[missing-params] ${relativeTo(file)} t('${key}') needs params {${[...expectPh].join(',')}} but caller passes none`,
      )
      continue
    }

    // extract param keys: support `{ name: foo }`, `{ name }`, `{ ...spread }` (ignore spread)
    const paramKeys = new Set()
    const inner = paramsRaw.slice(1, -1)
    for (const part of inner.split(',')) {
      const p = part.trim()
      if (!p || p.startsWith('...')) continue
      const colon = p.indexOf(':')
      const key = colon === -1 ? p : p.slice(0, colon).trim()
      // strip quotes if shorthand was actually a string key
      const bare = key.replace(/^['"]|['"]$/g, '')
      if (/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(bare)) paramKeys.add(bare)
    }

    const missing = [...expectPh].filter((p) => !paramKeys.has(p))
    if (missing.length) {
      violations.push(
        `[unfilled] ${relativeTo(file)} t('${key}', ${paramsRaw}) — missing param(s) {${missing.join(',')}} for string "${canonical[key]}"`,
      )
    }
  }
}

function relativeTo(p) {
  return p.replace(FRONTEND_ROOT + '/', '')
}

/* ---------- report ---------- */

if (violations.length) {
  console.error(`\nFAIL — ${violations.length} i18n violation(s):\n`)
  for (const v of violations) console.error('  ' + v)
  console.error('')
  process.exit(1)
}

console.log(
  `OK — ${canonicalKeys.size} keys × 4 dicts in parity; ${viewFiles.length} view files scanned; placeholders consistent.`,
)
