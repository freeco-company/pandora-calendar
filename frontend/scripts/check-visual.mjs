#!/usr/bin/env node
/**
 * Visual Linter — Back Button (Layer C — Static Analysis)
 *
 * Why: catches the "← ← 返回" double-arrow regression. Our common_back / common_back_to_me
 * dict strings already include a leading `←`, so any view that puts a literal
 * `←` in template AND also uses `t('common_back...')` ends up with two arrows.
 *
 * Rules:
 *   1. No literal `← ←` (any whitespace between) anywhere in /views.
 *   2. No `>← {{ t('common_back...') }}<` pattern (literal arrow + dict-arrow string).
 *
 * Exits 1 on violation.
 */
import { readFileSync, readdirSync, statSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const FRONTEND_ROOT = join(__dirname, '..')
const VIEWS_DIR = join(FRONTEND_ROOT, 'src/views')

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

const violations = []
const files = walk(VIEWS_DIR)

for (const file of files) {
  const src = readFileSync(file, 'utf8')
  const lines = src.split('\n')

  lines.forEach((line, idx) => {
    // Rule 1: literal `← ←` (with optional whitespace)
    if (/←\s*←/.test(line)) {
      violations.push(`${rel(file)}:${idx + 1}  double-arrow detected → \`${line.trim()}\``)
    }

    // Rule 2: literal `←` adjacent to a t('common_back...') call
    // Examples we want to catch:
    //   ← {{ t('common_back') }}
    //   ←{{ t('common_back_to_me') }}
    if (/←\s*\{\{\s*t\(['"]common_back/.test(line)) {
      violations.push(
        `${rel(file)}:${idx + 1}  literal \`←\` next to t('common_back…') (dict already includes arrow) → \`${line.trim()}\``,
      )
    }
  })
}

function rel(p) {
  return p.replace(FRONTEND_ROOT + '/', '')
}

if (violations.length) {
  console.error(`\nFAIL — ${violations.length} visual violation(s):\n`)
  for (const v of violations) console.error('  ' + v)
  console.error('')
  process.exit(1)
}

console.log(`OK — ${files.length} view file(s) scanned, no double-arrow / dict-arrow conflicts.`)
