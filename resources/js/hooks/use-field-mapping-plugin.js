import { linter } from '@codemirror/lint'
import { Decoration, EditorView, WidgetType } from '@codemirror/view'
import { RangeSetBuilder, StateField } from '@codemirror/state'

const TOKEN_REGEX = /\{\$(\d+)\}/g

export class FieldWidget extends WidgetType {
  constructor(fieldId, fieldName) {
    super()
    this.fieldId = fieldId
    this.fieldName = fieldName
  }

  toDOM() {
    const span = document.createElement('span')
    span.className = 'cm-field-pill'
    span.dataset.fieldId = String(this.fieldId)
    span.textContent = this.fieldName
    return span
  }

  eq(other) {
    return other.fieldId === this.fieldId && other.fieldName === this.fieldName
  }

  // Allow events to propagate so the outer click handler can detect pill clicks
  ignoreEvent() {
    return false
  }
}

// Tailwind v4 defines --primary as an oklch() value, so we use color-mix() for alpha.
export const fieldPillTheme = EditorView.baseTheme({
  '.cm-field-pill': {
    display: 'inline-block',
    padding: '0 6px',
    borderRadius: '4px',
    fontSize: '0.8em',
    fontWeight: '600',
    cursor: 'pointer',
    background: 'color-mix(in oklch, var(--primary) 15%, transparent)',
    color: 'var(--primary)',
    border: '1px solid color-mix(in oklch, var(--primary) 40%, transparent)',
    userSelect: 'none',
    verticalAlign: 'baseline',
    lineHeight: '1.6',
  },
  '.cm-field-pill:hover': {
    background: 'color-mix(in oklch, var(--primary) 25%, transparent)',
  },
})

function buildDecorations(state, fieldMap) {
  const builder = new RangeSetBuilder()
  const doc = state.doc.toString()
  TOKEN_REGEX.lastIndex = 0
  let match
  while ((match = TOKEN_REGEX.exec(doc)) !== null) {
    const fieldId = parseInt(match[1], 10)
    const field = fieldMap.get(fieldId)
    const name = field ? (field.label ?? field.name) : `#${fieldId}`

    let from = match.index
    let to = match.index + match[0].length

    // If the token is wrapped in JSON string quotes ("{$N}"), extend the
    // decoration to include them — the pill renders without visible quotes.
    if (from > 0 && doc[from - 1] === '"' && to < doc.length && doc[to] === '"') {
      from -= 1
      to += 1
    }

    builder.add(from, to, Decoration.replace({ widget: new FieldWidget(fieldId, name) }))
  }
  return builder.finish()
}

// Detects extra surrounding quotes around field tokens (e.g. ""{$N}") and shows one
// friendly hint per token — avoids duplicate messages from leading + trailing passes.
const BARE_TOKEN_REGEX = /\{\$\d+\}/g

export const tokenDoubleQuoteLinter = linter((view) => {
  const diagnostics = []
  const doc = view.state.doc.toString()

  BARE_TOKEN_REGEX.lastIndex = 0
  let match
  while ((match = BARE_TOKEN_REGEX.exec(doc)) !== null) {
    const pre = match.index > 1 ? doc[match.index - 2] : ''
    const post = doc[match.index + match[0].length + 1] ?? ''
    const hasExtraLeading = pre === '"'
    const hasExtraTrailing = post === '"'

    if (hasExtraLeading || hasExtraTrailing) {
      diagnostics.push({
        from: match.index - (hasExtraLeading ? 2 : 1),
        to: match.index + match[0].length + (hasExtraTrailing ? 2 : 1),
        severity: 'warning',
        message: 'Field tokens already include their own quotes — remove the extra " to avoid duplication.',
      })
    }
  }

  return diagnostics
})

/**
 * Creates a CodeMirror StateField that scans the document for {$N} tokens
 * and renders them as interactive pill widgets.
 *
 * @param {Map<number, { name: string, label?: string }>} fieldMap
 * @returns {{ stateField: StateField, extensions: Extension[] }}
 */
export function createFieldMappingPlugin(fieldMap) {
  const stateField = StateField.define({
    create: (state) => buildDecorations(state, fieldMap),
    update: (decs, tr) => (tr.docChanged ? buildDecorations(tr.state, fieldMap) : decs),
    provide: (f) => EditorView.decorations.from(f),
  })
  return { stateField, extensions: [stateField, fieldPillTheme] }
}
