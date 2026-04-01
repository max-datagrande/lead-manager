import { FieldPillPopover } from '@/components/forms/field-pill-popover'
import { FieldSearchPopover } from '@/components/forms/field-search-popover'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { getCurrentTheme } from '@/hooks/use-appearance'
import { FieldWidget, createFieldMappingPlugin, tokenDoubleQuoteLinter } from '@/hooks/use-field-mapping-plugin'
import { useToast } from '@/hooks/use-toast'
import { cn } from '@/lib/utils'
import { json, jsonParseLinter } from '@codemirror/lang-json'
import { linter, lintGutter } from '@codemirror/lint'
import { EditorSelection } from '@codemirror/state'
import { keymap } from '@codemirror/view'
import CodeMirror from '@uiw/react-codemirror'
import { parse } from 'jsonc-parser'
import { useCallback, useMemo, useRef, useState } from 'react'

/**
 * @param {{
 *   label?: string,
 *   className?: string,
 *   value: string,
 *   onChange: (value: string) => void,
 *   placeholder?: string,
 *   fields?: Array<{ id: number, name: string, label?: string }>,
 *   onTokenInsert?: (fieldId: number) => void,
 *   onTokenRemove?: (fieldId: number) => void,
 * }} props
 */
const JsonEditor = ({ label, className = '', value, onChange, placeholder, fields = [], onTokenInsert = null, onTokenRemove = null, getHashConfig = null, onHashChange = null, ...props }) => {
  const theme = getCurrentTheme()
  const { addMessage } = useToast()
  const viewRef = useRef(null)
  const atFromRef = useRef(null)
  const wrapperRef = useRef(null)
  const [searchPopover, setSearchPopover] = useState(null) // { top, left } | null
  const [activePill, setActivePill] = useState(null) // { fieldId, field, from, to, position }

  const handleEditorChange = useCallback(
    (val) => {
      onChange(val)
    },
    [onChange],
  )

  const formatJson = () => {
    try {
      const stringValue = typeof value === 'string' ? value : JSON.stringify(value, null, 2)
      const parsed = parse(stringValue)
      const formatted = JSON.stringify(parsed, null, 2)
      onChange(formatted)
    } catch (error) {
      console.error('Error formatting JSON:', error)
      addMessage('Error formatting JSON', 'error')
    }
  }

  // ── Field mapping plugin (decoration: {$N} → pill) ───────────────────────
  const fieldMap = useMemo(() => new Map(fields.map((f) => [f.id, f])), [fields])
  const { stateField, extensions: fieldExtensions } = useMemo(() => createFieldMappingPlugin(fieldMap), [fieldMap])

  // ── @ trigger (open field search popover) ────────────────────────────────
  // Stable callback ref — keymap reads it at call time (always fresh).
  const openSearchRef = useRef(null)
  openSearchRef.current = (atFrom, coords) => {
    if (!fields.length) return
    atFromRef.current = atFrom
    const wrapperRect = wrapperRef.current?.getBoundingClientRect() ?? { top: 0, left: 0 }
    setSearchPopover({ top: coords.bottom - wrapperRect.top + 4, left: coords.left - wrapperRect.left })
  }

  const atTriggerExtension = useMemo(
    () =>
      keymap.of([
        {
          key: '@',
          run: (view) => {
            const { from } = view.state.selection.main
            view.dispatch({
              changes: { from, insert: '@' },
              selection: EditorSelection.cursor(from + 1),
            })
            const coords = view.coordsAtPos(from + 1)
            if (coords) openSearchRef.current(from, coords)
            return true
          },
        },
      ]),
    [], // eslint-disable-line react-hooks/exhaustive-deps
  )

  // ── Search popover handlers ───────────────────────────────────────────────
  const handleFieldSelect = (field) => {
    const view = viewRef.current
    if (!view || atFromRef.current === null) return
    const atPos = atFromRef.current
    const currentHead = view.state.selection.main.head
    const token = `"{$${field.id}}"`
    view.dispatch({
      changes: { from: atPos, to: currentHead, insert: token },
      selection: EditorSelection.cursor(atPos + token.length),
    })
    setSearchPopover(null)
    atFromRef.current = null
    onTokenInsert?.(field.id)
    view.focus()
  }

  const handleSearchClose = () => {
    setSearchPopover(null)
    atFromRef.current = null
    viewRef.current?.focus()
  }

  // ── Pill click handler (event delegation on the editor wrapper) ───────────
  const handleEditorClick = (e) => {
    const target = e.target
    if (!target.classList.contains('cm-field-pill')) {
      if (activePill) setActivePill(null)
      return
    }
    const view = viewRef.current
    if (!view) return

    const fieldId = parseInt(target.dataset.fieldId, 10)
    const rect = target.getBoundingClientRect()
    const wrapperRect = wrapperRef.current?.getBoundingClientRect() ?? { top: 0, left: 0 }

    // posAtDOM approximates the document position of the widget node.
    // We search a small window around it to find the actual decoration range.
    const approxPos = view.posAtDOM(target)
    let range = null
    view.state.field(stateField).between(Math.max(0, approxPos - 10), Math.min(view.state.doc.length, approxPos + 20), (from, to, dec) => {
      if (dec.widget instanceof FieldWidget && dec.widget.fieldId === fieldId && range === null) {
        range = { from, to }
      }
    })

    if (range) {
      setActivePill({
        fieldId,
        field: fields.find((f) => f.id === fieldId),
        from: range.from,
        to: range.to,
        position: { top: rect.bottom - wrapperRect.top + 4, left: rect.left - wrapperRect.left },
      })
    }
  }

  // ── Pill popover handlers ─────────────────────────────────────────────────
  const handlePillDelete = () => {
    const view = viewRef.current
    if (!view || !activePill) return
    view.dispatch({ changes: { from: activePill.from, to: activePill.to, insert: '' } })
    onTokenRemove?.(activePill.fieldId)
    setActivePill(null)
    view.focus()
  }

  const handlePillClose = () => {
    setActivePill(null)
    viewRef.current?.focus()
  }

  // ─────────────────────────────────────────────────────────────────────────

  return (
    <div ref={wrapperRef} className={cn('relative space-y-2', className)}>
      <div className="mb-2 flex justify-between gap-4">
        {label && (
          <Label className="flex items-center gap-2" htmlFor="json-editor">
            {label}
          </Label>
        )}
        <Button type="button" variant="outline" size="sm" onClick={formatJson}>
          Format JSON
        </Button>
      </div>
      {/* eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-static-element-interactions */}
      <div onClick={handleEditorClick}>
        <CodeMirror
          {...props}
          id="json-editor"
          value={typeof value === 'string' ? value : JSON.stringify(value, null, 2)}
          height="450px"
          extensions={[json(), linter(jsonParseLinter()), tokenDoubleQuoteLinter, lintGutter(), atTriggerExtension, ...fieldExtensions]}
          onChange={handleEditorChange}
          onCreateEditor={(view) => {
            viewRef.current = view
          }}
          theme={theme === 'dark' ? 'dark' : 'light'}
          placeholder={placeholder}
          basicSetup={{
            lineNumbers: true,
            indentOnInput: true,
          }}
          indentWithTab={false}
        />
      </div>

      {searchPopover && fields.length > 0 && (
        <FieldSearchPopover fields={fields} position={searchPopover} onSelect={handleFieldSelect} onClose={handleSearchClose} />
      )}

      {activePill && (
        <FieldPillPopover
          fieldId={activePill.fieldId}
          field={activePill.field}
          position={activePill.position}
          hashConfig={getHashConfig ? getHashConfig(activePill.fieldId) : null}
          onHashChange={onHashChange ? (patch) => onHashChange(activePill.fieldId, patch) : null}
          onDelete={handlePillDelete}
          onClose={handlePillClose}
        />
      )}
    </div>
  )
}

export default JsonEditor
