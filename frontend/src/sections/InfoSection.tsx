import { useEffect, useMemo, useState } from 'react'
import { Alert, Button, Form, Spinner } from 'react-bootstrap'
import { attributeApi } from '../api/attributes'
import { buildPayload, profileAttributeApi } from '../api/profileAttributes'
import type {
    AttributeDefinition,
    DataType,
    ProfileAttributeRow,
} from '../types'
import { useTranslation } from '../contexts/AppPreferencesContext'

type SaveStatus = 'idle' | 'saving' | 'saved' | 'conflict' | 'error'

/**
 * A locally-added attribute that hasn't been POSTed yet.
 *
 * Backend requires `selectedOptionId` for one_of_many, so we cannot
 * create the ProfileAttribute until the user has picked a value. While
 * the picker-added field is unsaved, it lives only in this client-side
 * list — no API row exists, and the toolbar "Remove selected" simply
 * drops it locally.
 *
 * Selection key for the toolbar: a positive attributeDefinitionId means
 * a saved row; a negative pendingId means a pending one. This lets a
 * single Set<number> cover both kinds.
 */
interface PendingAttribute {
    pendingId: number
    attributeDefinitionId: number
}

export default function InfoSection() {
    const t = useTranslation()
    const [rows, setRows] = useState<ProfileAttributeRow[]>([])
    const [pending, setPending] = useState<PendingAttribute[]>([])
    const [definitions, setDefinitions] = useState<Map<number, AttributeDefinition>>(
        new Map(),
    )
    const [loading, setLoading] = useState(true)
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())
    const [actionBusy, setActionBusy] = useState(false)

    const [prefix, setPrefix] = useState('')
    const [categoryId, setCategoryId] = useState<number | ''>('')
    const [prefixResults, setPrefixResults] = useState<AttributeDefinition[]>([])
    const [byCategory, setByCategory] = useState<AttributeDefinition[]>([])
    const [recent, setRecent] = useState<AttributeDefinition[]>([])
    const [categories, setCategories] = useState<{ id: number; name: string }[]>([])

    const [addError, setAddError] = useState('')

    const refreshRows = async () => setRows(await profileAttributeApi.list())

    useEffect(() => {
        void (async () => {
            setLoading(true)
            const [r, defs, cats] = await Promise.all([
                profileAttributeApi.list(),
                attributeApi.list(),
                attributeApi.listCategories(),
            ])
            setRows(r)
            setDefinitions(new Map(defs.map((d) => [d.id, d])))
            setCategories(cats)
            setLoading(false)
        })()
    }, [])

    useEffect(() => {
        let cancelled = false
        const timeoutId = window.setTimeout(async () => {
            const data = await attributeApi.lookup({
                prefix,
                categoryId: categoryId === '' ? undefined : categoryId,
                recentOnly: prefix === '' && categoryId === '',
                limit: 20,
            })
            if (cancelled) return
            // Backend returns three buckets; the picker decides which to
            // show based on what the user has typed/selected (see
            // lookupResults below). Keeping them as separate state slices
            // makes Case C (prefix + category) a cheap filter.
            setPrefixResults(data.prefix)
            setByCategory(data.byCategory ?? [])
            setRecent(data.recent)
        }, 200)
        return () => {
            cancelled = true
            window.clearTimeout(timeoutId)
        }
    }, [prefix, categoryId])

    const replaceRow = (updated: ProfileAttributeRow) =>
        setRows((cur) =>
            cur.map((r) =>
                r.attributeDefinitionId === updated.attributeDefinitionId ? updated : r,
            ),
        )

    const toggleSelected = (id: number, checked: boolean) => {
        setSelectedIds((cur) => {
            const next = new Set(cur)
            if (checked) next.add(id)
            else next.delete(id)
            return next
        })
    }

    const clearSelection = () => setSelectedIds(new Set())

    /**
     * Remove all selected rows. Saved rows go through DELETE; pending
     * rows drop out of local state only (no API row to delete).
     */
    const removeSelected = async () => {
        const savedIds: number[] = []
        const pendingIds: number[] = []
        selectedIds.forEach((id) => {
            if (id < 0) pendingIds.push(id)
            else savedIds.push(id)
        })
        const total = savedIds.length + pendingIds.length
        if (total === 0) return
        if (
            !window.confirm(
                `Remove ${total} attribute${total === 1 ? '' : 's'} from your profile?`,
            )
        ) {
            return
        }

        setActionBusy(true)
        setAddError('')

        if (pendingIds.length > 0) {
            setPending((cur) => cur.filter((p) => !pendingIds.includes(p.pendingId)))
        }

        const failures: string[] = []
        for (const defId of savedIds) {
            try {
                await profileAttributeApi.remove(defId)
            } catch (e: unknown) {
                failures.push(apiErrorMessage(e, `Could not remove attribute ${defId}.`))
            }
        }

        if (savedIds.length > 0) await refreshRows()
        clearSelection()
        setActionBusy(false)
        if (failures.length > 0) setAddError(failures.join(' '))
    }

    /**
     * Picker handler. Splits by dataType:
     *   - one_of_many: backend demands selectedOptionId, so we cannot
     *     POST an empty row. Add it locally; PendingAttributeField will
     *     POST once the user picks an option.
     *   - everything else: backend accepts an empty value, so POST
     *     immediately and refresh.
     */
    const addFromPicker = async (definition: AttributeDefinition) => {
        setAddError('')
        if (rows.some((r) => r.attributeDefinitionId === definition.id)) return
        if (pending.some((p) => p.attributeDefinitionId === definition.id)) return

        if (definition.dataType === 'one_of_many') {
            setPending((cur) => [
                ...cur,
                { pendingId: nextPendingId(), attributeDefinitionId: definition.id },
            ])
            return
        }

        try {
            await profileAttributeApi.set({ attributeDefinitionId: definition.id })
            await refreshRows()
        } catch (e: unknown) {
            const msg = apiErrorMessage(e, 'Could not add this attribute.')
            setAddError(msg)
        }
    }

    const promotePending = (pendingId: number, saved: ProfileAttributeRow) => {
        setPending((cur) => cur.filter((p) => p.pendingId !== pendingId))
        setRows((cur) => {
            // Defensive replace-then-append: if a refresh raced and the
            // row already arrived, swap it; otherwise append.
            const idx = cur.findIndex(
                (r) => r.attributeDefinitionId === saved.attributeDefinitionId,
            )
            if (idx >= 0) {
                return cur.map((r, i) => (i === idx ? saved : r))
            }
            return [...cur, saved]
        })
        // Any prior selection of this pending id is no longer valid.
        setSelectedIds((cur) => {
            if (!cur.has(pendingId)) return cur
            const next = new Set(cur)
            next.delete(pendingId)
            return next
        })
    }

    const lookupResults = useMemo(() => {
        // Case A: prefix + no category → data.prefix
        if (prefix !== '') {
            if (categoryId !== '') {
                // Case C: prefix + category → prefix filtered by categoryId
                return prefixResults.filter((a) => a.categoryId === categoryId)
            }
            return prefixResults
        }
        // Case B: no prefix + category → data.byCategory
        if (categoryId !== '') return byCategory
        // Case D: nothing → data.recent (may be empty for new candidates)
        return recent
    }, [prefixResults, byCategory, recent, prefix, categoryId])

    const emptyMessage = useMemo(() => {
        if (lookupResults.length > 0) return ''
        if (prefix !== '' || categoryId !== '') return 'No matching attributes.'
        return 'No recently used attributes. Use search or choose a category to find attributes.'
    }, [lookupResults, prefix, categoryId])

    const isOnForm = (definitionId: number) =>
        rows.some((r) => r.attributeDefinitionId === definitionId) ||
        pending.some((p) => p.attributeDefinitionId === definitionId)

    const formIsEmpty = rows.length === 0 && pending.length === 0

    return (
        <div className="row">
            <div className="col-lg-7">
                <h3>Information</h3>
                {addError !== '' && (
                    <Alert variant="danger" onClose={() => setAddError('')} dismissible>
                        {addError}
                    </Alert>
                )}
                {loading ? (
                    <Spinner animation="border" />
                ) : formIsEmpty ? (
                    <div className="text-muted">
                        No attributes yet. Add some from the picker on the right.
                    </div>
                ) : (
                    <Form>
                        {!formIsEmpty && (
                            <div className="d-flex justify-content-between align-items-center mb-3 p-2 border rounded bg-light">
                                <small className="text-muted">
                                    {selectedIds.size === 0
                                        ? 'Select rows to enable actions.'
                                        : `${selectedIds.size} selected.`}
                                </small>
                                <div className="d-flex gap-2">
                                    {selectedIds.size > 0 && (
                                        <Button
                                            variant="outline-secondary"
                                            size="sm"
                                            onClick={clearSelection}
                                            disabled={actionBusy}
                                        >
                                            Clear
                                        </Button>
                                    )}
                                    <Button
                                        variant="outline-danger"
                                        size="sm"
                                        onClick={() => void removeSelected()}
                                        disabled={selectedIds.size === 0 || actionBusy}
                                    >
                                        Remove selected
                                    </Button>
                                </div>
                            </div>
                        )}
                        {pending.map((p) => {
                            const def = definitions.get(p.attributeDefinitionId)
                            return (
                                <Form.Group
                                    key={p.pendingId}
                                    className="mb-3 d-flex gap-2 align-items-start"
                                >
                                    <Form.Check
                                        type="checkbox"
                                        className="mt-2"
                                        id={`select-pending-${p.pendingId}`}
                                        checked={selectedIds.has(p.pendingId)}
                                        onChange={(e) =>
                                            toggleSelected(p.pendingId, e.target.checked)
                                        }
                                        aria-label={`Select ${def?.name ?? 'pending attribute'}`}
                                    />
                                    <div className="flex-grow-1">
                                        <Form.Label
                                            className="fw-semibold"
                                            htmlFor={`select-pending-${p.pendingId}`}
                                        >
                                            {def?.name ?? '…'}
                                            {def?.required && (
                                                <span className="text-danger ms-1">*</span>
                                            )}
                                        </Form.Label>
                                        {def === undefined ? (
                                            <div className="text-muted small">
                                                Definition unavailable.
                                            </div>
                                        ) : (
                                            <PendingAttributeField
                                                definition={def}
                                                onPromoted={(saved) =>
                                                    promotePending(p.pendingId, saved)
                                                }
                                            />
                                        )}
                                    </div>
                                </Form.Group>
                            )
                        })}
                        {rows.map((row) => {
                            const def = definitions.get(row.attributeDefinitionId)
                            return (
                                <Form.Group
                                    key={row.attributeDefinitionId}
                                    className="mb-3 d-flex gap-2 align-items-start"
                                >
                                    <Form.Check
                                        type="checkbox"
                                        className="mt-2"
                                        id={`select-saved-${row.attributeDefinitionId}`}
                                        checked={selectedIds.has(row.attributeDefinitionId)}
                                        onChange={(e) =>
                                            toggleSelected(
                                                row.attributeDefinitionId,
                                                e.target.checked,
                                            )
                                        }
                                        aria-label={`Select ${row.name}`}
                                    />
                                    <div className="flex-grow-1">
                                        <Form.Label
                                            className="fw-semibold"
                                            htmlFor={`select-saved-${row.attributeDefinitionId}`}
                                        >
                                            {row.name}
                                            {row.required && (
                                                <span className="text-danger ms-1">*</span>
                                            )}
                                        </Form.Label>
                                        {def === undefined ? (
                                            <div className="text-muted small">
                                                Definition unavailable.
                                            </div>
                                        ) : (
                                            <AttributeFormField
                                                row={row}
                                                definition={def}
                                                onSaved={replaceRow}
                                            />
                                        )}
                                    </div>
                                </Form.Group>
                            )
                        })}
                    </Form>
                )}
            </div>

            <div className="col-lg-5">
                <div className="card">
                    <div className="card-body">
                        <h5 className="card-title">{t('attr.library.title')}</h5>

                        <Form.Group className="mb-2">
                            <Form.Label>{t('attr.lookup.prefix')}</Form.Label>
                            <Form.Control
                                value={prefix}
                                onChange={(e) => setPrefix(e.target.value)}
                                placeholder={t('attr.lookup.prefix')}
                            />
                        </Form.Group>

                        <Form.Group className="mb-3">
                            <Form.Label>{t('attr.lookup.category')}</Form.Label>
                            <Form.Select
                                value={categoryId}
                                onChange={(e) =>
                                    setCategoryId(
                                        e.target.value === '' ? '' : Number(e.target.value),
                                    )
                                }
                            >
                                <option value="">—</option>
                                {categories.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </Form.Select>
                        </Form.Group>

                        <div className="d-flex justify-content-between align-items-center mb-2">
                            <small className="text-muted">
                                {prefix === '' && categoryId === ''
                                    ? t('attr.lookup.recent')
                                    : 'Results'}
                            </small>
                        </div>

                        <div
                            className="list-group"
                            style={{ maxHeight: 360, overflowY: 'auto' }}
                        >
                            {lookupResults.length === 0 ? (
                                <div className="list-group-item text-muted">
                                    {emptyMessage}
                                </div>
                            ) : (
                                lookupResults.map((a) => {
                                    const onForm = isOnForm(a.id)
                                    return (
                                        <button
                                            key={a.id}
                                            type="button"
                                            className="list-group-item list-group-item-action d-flex justify-content-between"
                                            onClick={() => void addFromPicker(a)}
                                            disabled={onForm}
                                        >
                                            <span>
                                                <strong>{a.name}</strong>
                                                <br />
                                                <small className="text-muted">
                                                    {a.categoryName} · {a.dataType}
                                                </small>
                                            </span>
                                            <span className="badge bg-secondary align-self-center">
                                                {onForm ? '✓' : t('common.add')}
                                            </span>
                                        </button>
                                    )
                                })
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    )
}

/**
 * Form field for a row that already exists on the backend. Auto-save on
 * change for boolean/one_of_many, on blur for everything else. Conflict
 * (HTTP 409) is surfaced as a status hint, not a thrown error.
 *
 * Removal is handled by the toolbar in the parent, so this component
 * no longer renders a per-row × button.
 */
interface AttributeFormFieldProps {
    row: ProfileAttributeRow
    definition: AttributeDefinition
    onSaved: (updated: ProfileAttributeRow) => void
}

function AttributeFormField({ row, definition, onSaved }: AttributeFormFieldProps) {
    const [draft, setDraft] = useState<unknown>(
        initialDraftFor(definition.dataType, row.value, definition),
    )
    const [status, setStatus] = useState<SaveStatus>('idle')
    const [statusMsg, setStatusMsg] = useState('')

    const save = async (next: unknown) => {
        setStatus('saving')
        setStatusMsg('')
        try {
            const updated = await profileAttributeApi.set(
                buildPayload(definition.dataType, definition.id, row.version, next),
            )
            onSaved(updated)
            setStatus('saved')
            setStatusMsg('')
            window.setTimeout(
                () => setStatus((s) => (s === 'saved' ? 'idle' : s)),
                1500,
            )
        } catch (e: unknown) {
            const code = (e as { response?: { status?: number } })?.response?.status
            if (code === 409) {
                setStatus('conflict')
                setStatusMsg('This field was changed in another session. Please reload.')
            } else {
                setStatus('error')
                setStatusMsg(apiErrorMessage(e, 'Save failed.'))
            }
        }
    }

    const onChange = (next: unknown) => {
        if (
            definition.dataType === 'one_of_many' ||
            definition.dataType === 'boolean'
        ) {
            if (next === draft) return
            setDraft(next)
            void save(next)
        } else {
            setDraft(next)
        }
    }

    const onBlur = () => {
        if (definition.dataType === 'one_of_many' || definition.dataType === 'boolean') {
            return
        }
        void save(draft)
    }

    return (
        <div>
            <TypedInput
                dataType={definition.dataType}
                definition={definition}
                value={draft}
                onChange={onChange}
                onBlur={onBlur}
            />
            <div className="mt-1">
                <small>
                    {status === 'saving' && (
                        <span className="text-muted">Saving…</span>
                    )}
                    {status === 'saved' && (
                        <span className="text-success">Saved</span>
                    )}
                    {status === 'conflict' && (
                        <span className="text-warning">{statusMsg}</span>
                    )}
                    {status === 'error' && (
                        <span className="text-danger">{statusMsg}</span>
                    )}
                </small>
            </div>
        </div>
    )
}

/**
 * Form field for a one_of_many attribute that the user just added from
 * the picker and hasn't picked a value for yet. The backend refuses an
 * empty one_of_many ProfileAttribute (422), so we keep it client-only
 * until the first selection; on first successful POST we promote it to
 * a real row and hand control back to AttributeFormField on the next
 * render via parent state.
 *
 * Removal is handled by the toolbar in the parent.
 */
interface PendingAttributeFieldProps {
    definition: AttributeDefinition
    onPromoted: (saved: ProfileAttributeRow) => void
}

function PendingAttributeField({ definition, onPromoted }: PendingAttributeFieldProps) {
    const [draft, setDraft] = useState<unknown>(
        initialDraftFor(definition.dataType, null, definition),
    )
    const [status, setStatus] = useState<SaveStatus>('idle')
    const [statusMsg, setStatusMsg] = useState('')

    const save = async (next: unknown) => {
        setStatus('saving')
        setStatusMsg('')
        try {
            const saved = await profileAttributeApi.set(
                // No version yet — backend will create the row.
                buildPayload(definition.dataType, definition.id, null, next),
            )
            onPromoted(saved)
        } catch (e: unknown) {
            setStatus('error')
            setStatusMsg(apiErrorMessage(e, 'Could not save this attribute.'))
        }
    }

    // one_of_many persists on change; everything else stays as a textarea
    // here — but in practice the picker only routes one_of_many through
    // the pending path, so this is mostly future-proofing.
    const onChange = (next: unknown) => {
        if (definition.dataType === 'one_of_many') {
            if (next === draft) return
            setDraft(next)
            void save(next)
        } else {
            setDraft(next)
        }
    }

    return (
        <div>
            <TypedInput
                dataType={definition.dataType}
                definition={definition}
                value={draft}
                onChange={onChange}
            />
            <div className="mt-1">
                <small>
                    {status === 'saving' && (
                        <span className="text-muted">Saving…</span>
                    )}
                    {status === 'idle' && (
                        <span className="text-muted">Not saved yet — pick a value.</span>
                    )}
                    {status === 'error' && (
                        <span className="text-danger">{statusMsg}</span>
                    )}
                </small>
            </div>
        </div>
    )
}

/**
 * Backend ProfileAttributeController::present() returns the option's
 * *value* (string) for one_of_many, not the id. The form, however, drives
 * the <select> by id — so we translate string → id here, on read.
 * TypedInput stays untouched; buildPayload already serializes the id
 * back to selectedOptionId on save.
 *
 * For pending rows, value is null and we return null/empty drafts until
 * the user picks something.
 */
function initialDraftFor(
    dataType: DataType,
    value: unknown,
    def: AttributeDefinition,
): unknown {
    if (dataType === 'boolean') return value ?? false
    if (dataType === 'period') {
        return value ?? { start: null, end: null }
    }
    if (dataType === 'one_of_many' && typeof value === 'string') {
        const match = (def.options ?? []).find((o) => o.value === value)
        return match?.id ?? null
    }
    return (value as string | null) ?? null
}

function apiErrorMessage(e: unknown, fallback: string): string {
    const err = e as { response?: { data?: { error?: string } } }
    return err?.response?.data?.error ?? fallback
}

let pendingCounter = 0
function nextPendingId(): number {
    pendingCounter -= 1
    return pendingCounter
}

interface TypedInputProps {
    dataType: DataType
    definition: AttributeDefinition
    value: unknown
    onChange: (v: unknown) => void
    onBlur?: () => void
}

function TypedInput({
    dataType,
    definition,
    value,
    onChange,
    onBlur,
}: TypedInputProps) {
    switch (dataType) {
        case 'string':
            return (
                <Form.Control
                    type="text"
                    value={(value as string | null) ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                    onBlur={onBlur}
                />
            )
        case 'text':
            return (
                <Form.Control
                    as="textarea"
                    rows={4}
                    value={(value as string | null) ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                    onBlur={onBlur}
                />
            )
        case 'numeric':
            return (
                <Form.Control
                    type="number"
                    step="any"
                    value={(value as string | null) ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                    onBlur={onBlur}
                />
            )
        case 'date':
            return (
                <Form.Control
                    type="date"
                    value={(value as string | null) ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                    onBlur={onBlur}
                />
            )
        case 'period': {
            const v =
                (value as { start: string | null; end: string | null } | null) ?? {
                    start: null,
                    end: null,
                }
            return (
                <div className="row g-2">
                    <div className="col-6">
                        <Form.Label className="small text-muted mb-1">From</Form.Label>
                        <Form.Control
                            type="date"
                            value={v.start ?? ''}
                            onChange={(e) =>
                                onChange({
                                    ...v,
                                    start: e.target.value === '' ? null : e.target.value,
                                })
                            }
                            onBlur={onBlur}
                        />
                    </div>
                    <div className="col-6">
                        <Form.Label className="small text-muted mb-1">To</Form.Label>
                        <Form.Control
                            type="date"
                            value={v.end ?? ''}
                            onChange={(e) =>
                                onChange({
                                    ...v,
                                    end: e.target.value === '' ? null : e.target.value,
                                })
                            }
                            onBlur={onBlur}
                        />
                    </div>
                </div>
            )
        }
        case 'boolean':
            return (
                <Form.Check
                    type="switch"
                    checked={Boolean(value)}
                    onChange={(e) => onChange(e.target.checked)}
                />
            )
        case 'image':
            return (
                <Form.Control
                    type="url"
                    placeholder="https://res.cloudinary.com/…"
                    value={(value as string | null) ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                    onBlur={onBlur}
                />
            )
        case 'one_of_many':
            return (
                <Form.Select
                    value={(value as number | null) ?? ''}
                    onChange={(e) =>
                        onChange(e.target.value === '' ? null : Number(e.target.value))
                    }
                >
                    <option value="">—</option>
                    {(definition.options ?? []).map((o) => (
                        <option key={o.id} value={o.id}>
                            {o.value}
                        </option>
                    ))}
                </Form.Select>
            )
        default:
            return null
    }
}
