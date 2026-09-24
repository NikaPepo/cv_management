import { useEffect, useState } from 'react'
import { useNavigate, useParams, Link } from 'react-router-dom'
import { Alert, Button, Form, Spinner, Table } from 'react-bootstrap'
import { cvApi, type CvAttributeValue, type CvView } from '../api/cvs'
import Markdown from '../components/Markdown'
import { useAuth } from '../contexts/AuthContext'

export default function CvDetailPage() {
    const { id } = useParams<{ id: string }>()
    const { hasRole } = useAuth()
    const navigate = useNavigate()

    const [view, setView] = useState<CvView | null>(null)
    const [error, setError] = useState('')
    const [loading, setLoading] = useState(true)
    const [editing, setEditing] = useState<CvAttributeValue | null>(null)
    const [editMode, setEditMode] = useState(false)
    const [selectedAttrIds, setSelectedAttrIds] = useState<Set<number>>(new Set())
    const [actionBusy, setActionBusy] = useState(false)

    const isOwner = true // controller scopes edit endpoints; this view only opens for owner or admin
    const isStaff = hasRole('ROLE_RECRUITER', 'ROLE_ADMIN')

    const refresh = async () => {
        try {
            const data = await cvApi.get(Number(id))
            setView(data)
        } catch (e: unknown) {
            const status = (e as { response?: { status?: number } })?.response?.status
            if (status === 403) {
                setError('You have lost access to this position.')
            } else if (status === 404) {
                setError('CV not found.')
            } else {
                setError((e as Error).message)
            }
        } finally {
            setLoading(false)
        }
    }

    useEffect(() => {
        void refresh()
    }, [id])

    const onPublish = async () => {
        if (view === null) return
        setActionBusy(true)
        try {
            await cvApi.publish(view.cv.id)
            await refresh()
        } catch (e: unknown) {
            const msg = (e as { response?: { data?: { error?: string } } })?.response?.data?.error
            setError(msg ?? 'Publish failed.')
        } finally {
            setActionBusy(false)
        }
    }

    const onDelete = async () => {
        if (view === null) return
        if (!window.confirm('Delete this CV?')) return
        await cvApi.remove(view.cv.id)
        navigate('/profile?tab=cvs')
    }

    const onLikeToggle = async () => {
        if (view === null) return
        const next = view.hasLiked ? await cvApi.unlike(view.cv.id) : await cvApi.like(view.cv.id)
        setView({ ...view, likeCount: next.likeCount, hasLiked: next.liked })
    }

    const onSaveAttribute = async (payload: Parameters<typeof cvApi.setAttribute>[1]) => {
        if (view === null || editing === null) return
        try {
            await cvApi.setAttribute(view.cv.id, payload)
            setEditing(null)
            setEditMode(false)
            setSelectedAttrIds(new Set())
            await refresh()
        } catch (e: unknown) {
            const status = (e as { response?: { status?: number } })?.response?.status
            if (status === 409) {
                setError('This value was changed in another session. Please reload.')
            } else {
                const msg = (e as { response?: { data?: { error?: string } } })?.response?.data?.error
                setError(msg ?? 'Save failed.')
            }
        }
    }

    const toggleAttrSelected = (attrId: number, checked: boolean) => {
        setSelectedAttrIds((cur) => {
            const next = new Set(cur)
            if (checked) next.add(attrId)
            else next.delete(attrId)
            return next
        })
    }

    const clearAttrSelection = () => setSelectedAttrIds(new Set())

    /**
     * Opens the existing AttributeValueEditor for the single selected
     * attribute. Editing one row at a time matches the existing modal
     * flow (which already calls cvApi.setAttribute under the hood) —
     * no new API.
     */
    const editSelectedAttribute = () => {
        if (view === null) return
        const [id] = selectedAttrIds
        if (id === undefined) return
        const attr = view.attributes.find((a) => a.attributeDefinitionId === id)
        if (attr !== undefined) setEditing(attr)
    }

    if (loading) return <Spinner animation="border" />
    if (error !== '' && view === null) return <Alert variant="danger">{error}</Alert>
    if (view === null) return null

    const canEdit = isOwner && view.cv.status === 'DRAFT'
    const isPublished = view.cv.status === 'PUBLISHED'

    return (
        <div>
            <header className="d-flex flex-wrap justify-content-between align-items-start mb-3 gap-2">
                <div>
                    <h1 className="mb-1">{view.position.title}</h1>
                    <div className="text-muted">
                        {[view.position.company, view.position.level]
                            .filter((s) => s !== null && s !== '')
                            .join(' · ') || '—'}
                    </div>
                </div>
                <div className="d-flex align-items-center gap-2">
                    <span
                        className={`badge ${
                            isPublished ? 'text-bg-success' : 'text-bg-secondary'
                        }`}
                    >
                        {view.cv.status}
                    </span>
                    {canEdit && (
                        <Button
                            onClick={() => void onPublish()}
                            disabled={actionBusy || view.hasUnpublishedRequired}
                        >
                            Publish
                        </Button>
                    )}
                    {isStaff && isPublished && (
                        <Button
                            variant={view.hasLiked ? 'warning' : 'outline-warning'}
                            onClick={() => void onLikeToggle()}
                        >
                            {view.hasLiked ? '★ Liked' : '☆ Like'} ({view.likeCount})
                        </Button>
                    )}
                    {isOwner && (
                        <Button variant="outline-danger" onClick={() => void onDelete()}>
                            Delete
                        </Button>
                    )}
                </div>
            </header>

            {canEdit && view.hasUnpublishedRequired && (
                <Alert variant="warning">
                    Some required attributes are missing.
                    Complete them before publishing this CV.
                </Alert>
            )}

            <section className="mb-4">
                <h3 className="mb-3">About the candidate</h3>
                <div className="d-flex gap-3 align-items-center">
                    {view.candidate.photoUrl !== null && (
                        <img
                            src={view.candidate.photoUrl}
                            alt=""
                            style={{
                                width: 80,
                                height: 80,
                                borderRadius: '50%',
                                objectFit: 'cover',
                            }}
                        />
                    )}
                    <div>
                        <div className="fw-semibold fs-5">
                            {view.candidate.firstName ?? '—'} {view.candidate.lastName ?? ''}
                        </div>
                        <div className="text-muted small">
                            {view.candidate.location ?? '—'}
                        </div>
                    </div>
                </div>
                {isOwner && (
                    <div className="mt-2 small">
                        <Link to="/profile">Edit profile</Link>
                    </div>
                )}
            </section>

            <section className="mb-4">
                <div className="d-flex justify-content-between align-items-center mb-2">
                    <h3 className="mb-0">Attributes</h3>
                    {canEdit &&
                        (editMode ? (
                            <Button
                                variant="outline-secondary"
                                size="sm"
                                onClick={() => {
                                    setEditMode(false)
                                    setEditing(null)
                                    clearAttrSelection()
                                }}
                            >
                                Done editing
                            </Button>
                        ) : (
                            <Button
                                variant="outline-primary"
                                size="sm"
                                onClick={() => setEditMode(true)}
                            >
                                Edit attributes
                            </Button>
                        ))}
                </div>
                {editMode && canEdit && (
                    <div className="d-flex justify-content-between align-items-center mb-2 p-2 border rounded bg-light">
                        <small className="text-muted">
                            {selectedAttrIds.size === 0
                                ? 'Select one row to edit it.'
                                : selectedAttrIds.size === 1
                                  ? '1 selected.'
                                  : `${selectedAttrIds.size} selected — edit one at a time.`}
                        </small>
                        <div className="d-flex gap-2">
                            {selectedAttrIds.size > 0 && (
                                <Button
                                    variant="outline-secondary"
                                    size="sm"
                                    onClick={clearAttrSelection}
                                >
                                    Clear
                                </Button>
                            )}
                            <Button
                                variant="outline-primary"
                                size="sm"
                                onClick={editSelectedAttribute}
                                disabled={selectedAttrIds.size !== 1}
                            >
                                Edit selected
                            </Button>
                        </div>
                    </div>
                )}
                <Table hover responsive className="align-middle">
                    <thead>
                        <tr>
                            {editMode && canEdit && (
                                <th style={{ width: 40 }} aria-label="Select" />
                            )}
                            <th style={{ width: '35%' }}>Attribute</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        {view.attributes.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={editMode && canEdit ? 3 : 2}
                                    className="text-muted text-center py-3"
                                >
                                    No attributes configured for this position.
                                </td>
                            </tr>
                        ) : (
                            view.attributes.map((attr) => (
                                <tr
                                    key={attr.attributeDefinitionId}
                                    className={attr.empty ? 'table-warning' : ''}
                                >
                                    {editMode && canEdit && (
                                        <td>
                                            <Form.Check
                                                type="checkbox"
                                                id={`cv-attr-${attr.attributeDefinitionId}`}
                                                checked={selectedAttrIds.has(
                                                    attr.attributeDefinitionId,
                                                )}
                                                onChange={(e) =>
                                                    toggleAttrSelected(
                                                        attr.attributeDefinitionId,
                                                        e.target.checked,
                                                    )
                                                }
                                                aria-label={`Select ${attr.name}`}
                                            />
                                        </td>
                                    )}
                                    <td className="fw-semibold">
                                        <label
                                            htmlFor={
                                                editMode && canEdit
                                                    ? `cv-attr-${attr.attributeDefinitionId}`
                                                    : undefined
                                            }
                                            className="d-block"
                                        >
                                            {attr.name}
                                            {attr.required && (
                                                <span className="text-danger ms-1">*</span>
                                            )}
                                            {attr.description !== '' && (
                                                <div className="text-muted small fw-normal mt-1">
                                                    {attr.description}
                                                </div>
                                            )}
                                        </label>
                                    </td>
                                    <td>{renderAttributeValue(attr)}</td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </Table>
            </section>

            <section className="mb-4">
                <h3 className="mb-2">Projects</h3>
                {view.projects.length === 0 ? (
                    <div className="text-muted">
                        No matching projects.{' '}
                        {isOwner ? (
                            <Link to="/profile">Add some in your profile</Link>
                        ) : (
                            'The candidate has no projects that match this position.'
                        )}
                    </div>
                ) : (
                    <Table hover responsive className="align-middle">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Technologies</th>
                                <th style={{ width: 200 }}>Period</th>
                            </tr>
                        </thead>
                        <tbody>
                            {view.projects.map((p) => (
                                <tr key={p.id}>
                                    <td>
                                        <div className="fw-semibold">{p.name}</div>
                                        {p.markdownDescription !== null && (
                                            <Markdown
                                                source={p.markdownDescription}
                                                className="mt-1 small"
                                            />
                                        )}
                                    </td>
                                    <td>
                                        {p.technologyTags.map((t) => (
                                            <span
                                                key={t.id}
                                                className="badge text-bg-secondary me-1"
                                            >
                                                {t.name}
                                            </span>
                                        ))}
                                    </td>
                                    <td className="text-muted small">
                                        {p.periodStart ?? '…'} → {p.periodEnd ?? '…'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}
            </section>

            {editing !== null && (
                <AttributeValueEditor
                    attr={editing}
                    onCancel={() => setEditing(null)}
                    onSave={(payload) => void onSaveAttribute(payload)}
                />
            )}

            {error !== '' && true && (
                <Alert variant="danger" className="mt-3" onClose={() => setError('')} dismissible>
                    {error}
                </Alert>
            )}
        </div>
    )
}

/**
 * User-facing rendering of an attribute value. Backend already returns
 * the option *value* (string, not id) for one_of_many, so a plain
 * String() is correct there.
 */
function renderAttributeValue(attr: CvAttributeValue): React.ReactNode {
    if (attr.empty) {
        return (
            <span className="text-muted fst-italic">Not specified</span>
        )
    }
    const { dataType, value } = attr
    if (dataType === 'period' && typeof value === 'object' && value !== null) {
        const v = value as { start: string | null; end: string | null }
        return (
            <>
                {v.start ?? '…'}
                {' → '}
                {v.end ?? '…'}
            </>
        )
    }
    if (dataType === 'boolean') {
        return value ? 'Yes' : 'No'
    }
    if (dataType === 'one_of_many' && typeof value === 'string') {
        // Backend already gives us the option label; just show it.
        return value
    }
    if (dataType === 'text' && typeof value === 'string') {
        // Show text/markdown inline as plain text — no preview widget per spec.
        return value
    }
    return String(value)
}

interface EditorProps {
    attr: CvAttributeValue
    onCancel: () => void
    onSave: (payload: Parameters<typeof cvApi.setAttribute>[1]) => void
}

function AttributeValueEditor({ attr, onCancel, onSave }: EditorProps) {
    const [draft, setDraft] = useState<unknown>(attr.value ?? defaultFor(attr.dataType))

    return (
        <>
            <div className="modal show d-block" tabIndex={-1} role="dialog">
                <div className="modal-dialog">
                    <div className="modal-content">
                        <div className="modal-header">
                            <h5 className="modal-title">{attr.name}</h5>
                            <button className="btn-close" onClick={onCancel} aria-label="Close" />
                        </div>
                        <div className="modal-body">
                            {attr.description !== '' && (
                                <p className="text-muted small">{attr.description}</p>
                            )}
                            <TypedInput
                                dataType={attr.dataType}
                                attr={attr}
                                value={draft}
                                onChange={setDraft}
                            />
                        </div>
                        <div className="modal-footer">
                            <Button variant="secondary" onClick={onCancel}>
                                Cancel
                            </Button>
                            <Button variant="primary" onClick={() => onSave(buildPayload(attr, draft))}>
                                Save
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
            <div className="modal-backdrop show" />
        </>
    )
}

function defaultFor(dataType: string): unknown {
    if (dataType === 'boolean') return false
    if (dataType === 'period') return { start: null, end: null }
    return null
}

function buildPayload(attr: CvAttributeValue, raw: unknown) {
    const payload: Parameters<typeof cvApi.setAttribute>[1] = {
        attributeDefinitionId: attr.attributeDefinitionId,
        version: attr.version,
    }
    switch (attr.dataType) {
        case 'string': payload.stringValue = (raw as string | null) ?? null; break
        case 'text': payload.markdownText = (raw as string | null) ?? null; break
        case 'numeric': payload.numericValue = (raw as string | null) ?? null; break
        case 'date': payload.dateValue = (raw as string | null) ?? null; break
        case 'period': {
            const v = raw as { start: string | null; end: string | null } | null
            payload.periodStart = v?.start ?? null
            payload.periodEnd = v?.end ?? null
            break
        }
        case 'boolean': payload.booleanValue = (raw as boolean | null) ?? null; break
        case 'image': payload.imageUrl = (raw as string | null) ?? null; break
        case 'one_of_many': payload.selectedOptionId = (raw as number | null) ?? null; break
    }
    return payload
}

interface TypedInputProps {
    dataType: string
    attr: CvAttributeValue
    value: unknown
    onChange: (v: unknown) => void
}

function TypedInput({ dataType, attr, value, onChange }: TypedInputProps) {
    switch (dataType) {
        case 'string':
            return (
                <Form.Control
                    value={(value as string | null) ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                />
            )
        case 'text':
            return (
                <Form.Control
                    as="textarea"
                    rows={4}
                    value={(value as string | null) ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                />
            )
        case 'numeric':
            return (
                <Form.Control
                    type="number"
                    step="any"
                    value={(value as string | null) ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                />
            )
        case 'date':
            return (
                <Form.Control
                    type="date"
                    value={(value as string | null) ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                />
            )
        case 'period': {
            const v = (value as { start: string | null; end: string | null } | null) ?? {
                start: null,
                end: null,
            }
            return (
                <div className="d-flex gap-2">
                    <Form.Control
                        type="date"
                        value={v.start ?? ''}
                        onChange={(e) => onChange({ ...v, start: e.target.value })}
                    />
                    <Form.Control
                        type="date"
                        value={v.end ?? ''}
                        onChange={(e) => onChange({ ...v, end: e.target.value })}
                    />
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
                    value={(value as string | null) ?? ''}
                    onChange={(e) => onChange(e.target.value)}
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
                    {attr.options.map((o) => (
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
