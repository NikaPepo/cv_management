import { useEffect, useMemo, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { Alert, Button, Form, Spinner } from 'react-bootstrap'
import {
    positionApi,
    type AccessRuleOperator,
    type CreatePositionPayload,
    type Position,
    type PositionAccessRuleInput,
    type PositionAttributeInput,
    type PositionLevel,
} from '../api/positions'
import { attributeApi } from '../api/attributes'
import type { AttributeDefinition } from '../types'
import TagInput from '../components/TagInput'

const LEVELS: PositionLevel[] = ['junior', 'middle', 'senior', 'c_level']

const OPERATORS: { value: AccessRuleOperator; label: string; types: string[] }[] = [
    { value: 'eq', label: '=', types: ['numeric', 'string', 'text', 'one_of_many', 'date', 'period', 'boolean', 'image'] },
    { value: 'ne', label: '≠', types: ['numeric', 'string', 'text', 'one_of_many', 'image'] },
    { value: 'gt', label: '>', types: ['numeric'] },
    { value: 'gte', label: '≥', types: ['numeric'] },
    { value: 'lt', label: '<', types: ['numeric'] },
    { value: 'lte', label: '≤', types: ['numeric'] },
    { value: 'in', label: 'in', types: ['one_of_many'] },
    { value: 'contains', label: 'contains', types: ['string', 'text'] },
    { value: 'before', label: 'before', types: ['date', 'period'] },
    { value: 'after', label: 'after', types: ['date', 'period'] },
]

interface AccessRuleDraft {
    key: string
    attributeDefinitionId: number
    operator: AccessRuleOperator
    value: unknown
}

export default function PositionEditorPage() {
    const navigate = useNavigate()
    const params = useParams<{ id: string }>()
    const editing = params.id !== undefined

    const [title, setTitle] = useState('')
    const [shortDescription, setShortDescription] = useState('')
    const [company, setCompany] = useState('')
    const [level, setLevel] = useState<PositionLevel | ''>('')
    const [isPublic, setIsPublic] = useState(true)
    const [maxProjects, setMaxProjects] = useState(5)
    const [projectTagFilter, setProjectTagFilter] = useState<string[]>([])
    const [version, setVersion] = useState<number | null>(null)
    const [conflict, setConflict] = useState(false)

    const [attributeLibrary, setAttributeLibrary] = useState<AttributeDefinition[]>([])
    const [chosenAttributes, setChosenAttributes] = useState<PositionAttributeInput[]>([])
    const [rules, setRules] = useState<AccessRuleDraft[]>([])

    const [loading, setLoading] = useState(true)
    const [saving, setSaving] = useState(false)
    const [error, setError] = useState('')

    const chosenIds = useMemo(() => new Set(chosenAttributes.map((c) => c.attributeDefinitionId)), [chosenAttributes])

    useEffect(() => {
        void (async () => {
            setLoading(true)
            const lib = await attributeApi.list()
            setAttributeLibrary(lib)
            if (editing) {
                const position = await positionApi.get(Number(params.id))
                hydrate(position)
            }
            setLoading(false)
        })()
    }, [params.id])

    const hydrate = (p: Position) => {
        setTitle(p.title)
        setShortDescription(p.shortDescription)
        setCompany(p.company ?? '')
        setLevel(p.level ?? '')
        setIsPublic(p.isPublic)
        setMaxProjects(p.maxProjects)
        setProjectTagFilter(p.projectTagFilter)
        setVersion(p.version)
        setChosenAttributes(p.attributes.map((a) => ({
            attributeDefinitionId: a.attributeDefinitionId,
            sortOrder: a.sortOrder,
        })))
        setRules((p.accessRules ?? []).map((r, idx) => ({
            key: `seed-${idx}-${r.attributeDefinitionId}`,
            attributeDefinitionId: r.attributeDefinitionId,
            operator: r.operator,
            value: r.value,
        })))
    }

    const addAttribute = (def: AttributeDefinition) => {
        if (chosenIds.has(def.id)) return
        setChosenAttributes((cur) => [
            ...cur,
            { attributeDefinitionId: def.id, sortOrder: cur.length },
        ])
    }

    const removeAttribute = (id: number) =>
        setChosenAttributes((cur) => cur.filter((c) => c.attributeDefinitionId !== id))

    const moveAttribute = (id: number, dir: -1 | 1) => {
        setChosenAttributes((cur) => {
            const idx = cur.findIndex((c) => c.attributeDefinitionId === id)
            if (idx < 0) return cur
            const swap = idx + dir
            if (swap < 0 || swap >= cur.length) return cur
            const next = [...cur]
            const tmp = next[idx]
            next[idx] = next[swap]
            next[swap] = tmp
            return next.map((c, i) => ({ ...c, sortOrder: i }))
        })
    }

    const addRule = () => {
        const def = attributeLibrary[0]
        if (def === undefined) return
        setRules((cur) => [
            ...cur,
            {
                key: `new-${Date.now()}`,
                attributeDefinitionId: def.id,
                operator: 'eq',
                value: null,
            },
        ])
    }

    const removeRule = (key: string) => setRules((cur) => cur.filter((r) => r.key !== key))

    const submit = async () => {
        setSaving(true)
        setError('')
        setConflict(false)
        try {
            const payload: CreatePositionPayload = {
                title,
                shortDescription,
                company: company === '' ? null : company,
                level: level === '' ? null : level,
                isPublic,
                maxProjects,
                projectTagFilter,
                attributes: chosenAttributes,
                accessRules: rules.map<PositionAccessRuleInput>((r) => ({
                    attributeDefinitionId: r.attributeDefinitionId,
                    operator: r.operator,
                    value: r.value,
                })),
            }
            if (editing) {
                await positionApi.update(Number(params.id), { ...payload, version })
            } else {
                await positionApi.create(payload)
            }
            navigate('/positions')
        } catch (e: unknown) {
            const err = e as { response?: { status?: number; data?: { error?: string } } }
            const status = err?.response?.status
            if (status === 409) {
                setConflict(true)
                setError('Position was modified by another session. Reload to see the latest changes before saving again.')
            } else {
                const msg = err?.response?.data?.error
                setError(msg ?? 'Failed to save.')
            }
        } finally {
            setSaving(false)
        }
    }

    if (loading) {
        return <Spinner animation="border" />
    }

    return (
        <div>
            <h1 className="mb-3">{editing ? 'Edit position' : 'New position'}</h1>
            {error !== '' && (
                <Alert variant={conflict ? 'warning' : 'danger'}>{error}</Alert>
            )}

            <div className="row g-3 mb-4">
                <Form.Group className="col-md-8">
                    <Form.Label>Title</Form.Label>
                    <Form.Control value={title} onChange={(e) => setTitle(e.target.value)} />
                </Form.Group>
                <Form.Group className="col-md-4">
                    <Form.Label>Company</Form.Label>
                    <Form.Control value={company} onChange={(e) => setCompany(e.target.value)} />
                </Form.Group>
                <Form.Group className="col-12">
                    <Form.Label>Short description</Form.Label>
                    <Form.Control
                        as="textarea"
                        rows={2}
                        value={shortDescription}
                        onChange={(e) => setShortDescription(e.target.value)}
                    />
                </Form.Group>
                <Form.Group className="col-md-3">
                    <Form.Label>Level</Form.Label>
                    <Form.Select
                        value={level}
                        onChange={(e) => setLevel(e.target.value as PositionLevel | '')}
                    >
                        <option value="">—</option>
                        {LEVELS.map((l) => (
                            <option key={l} value={l}>
                                {l}
                            </option>
                        ))}
                    </Form.Select>
                </Form.Group>
                <Form.Group className="col-md-3">
                    <Form.Label>Max projects</Form.Label>
                    <Form.Control
                        type="number"
                        min={0}
                        max={50}
                        value={maxProjects}
                        onChange={(e) => setMaxProjects(Number(e.target.value))}
                    />
                </Form.Group>
                <Form.Group className="col-md-6 d-flex align-items-end">
                    <Form.Check
                        type="switch"
                        label="Public (anyone can build CV)"
                        checked={isPublic}
                        onChange={(e) => setIsPublic(e.target.checked)}
                    />
                </Form.Group>
                <Form.Group className="col-12">
                    <Form.Label>Project tag filter</Form.Label>
                    <TagInput
                        value={projectTagFilter}
                        onChange={setProjectTagFilter}
                        placeholder="e.g. python, sql"
                    />
                </Form.Group>
            </div>

            <h3>Attributes</h3>
            <div className="row mb-4">
                <div className="col-md-7">
                    {chosenAttributes.length === 0 ? (
                        <div className="text-muted">No attributes yet.</div>
                    ) : (
                        <ul className="list-group">
                            {chosenAttributes.map((c, idx) => {
                                const def = attributeLibrary.find((d) => d.id === c.attributeDefinitionId)
                                if (def === undefined) return null
                                return (
                                    <li
                                        key={c.attributeDefinitionId}
                                        className="list-group-item d-flex justify-content-between"
                                    >
                                        <span>
                                            <strong>{def.name}</strong>{' '}
                                            <small className="text-muted">({def.dataType})</small>
                                        </span>
                                        <span>
                                            <Button
                                                size="sm"
                                                variant="outline-secondary"
                                                onClick={() => moveAttribute(c.attributeDefinitionId, -1)}
                                                disabled={idx === 0}
                                                className="me-2"
                                            >
                                                ↑
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline-secondary"
                                                onClick={() => moveAttribute(c.attributeDefinitionId, 1)}
                                                disabled={idx === chosenAttributes.length - 1}
                                                className="me-2"
                                            >
                                                ↓
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline-danger"
                                                onClick={() => removeAttribute(c.attributeDefinitionId)}
                                            >
                                                ×
                                            </Button>
                                        </span>
                                    </li>
                                )
                            })}
                        </ul>
                    )}
                </div>
                <div className="col-md-5">
                    <div className="card">
                        <div className="card-body">
                            <h6 className="card-title">Attribute library</h6>
                            <div className="list-group" style={{ maxHeight: 320, overflowY: 'auto' }}>
                                {attributeLibrary.map((def) => (
                                    <button
                                        key={def.id}
                                        type="button"
                                        className="list-group-item list-group-item-action d-flex justify-content-between"
                                        onClick={() => addAttribute(def)}
                                        disabled={chosenIds.has(def.id)}
                                    >
                                        <span>
                                            <strong>{def.name}</strong>
                                            <br />
                                            <small className="text-muted">{def.dataType}</small>
                                        </span>
                                        <span className="badge bg-secondary align-self-center">
                                            {chosenIds.has(def.id) ? '✓' : '+'}
                                        </span>
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {!isPublic && (
                <>
                    <div className="d-flex justify-content-between align-items-center mb-2">
                        <h3 className="mb-0">Access rules</h3>
                        <Button size="sm" variant="outline-secondary" onClick={addRule}>
                            Add rule
                        </Button>
                    </div>

                    {rules.length === 0 ? (
                        <div className="text-muted mb-4">
                            No rules yet. Without rules, restricted positions would block everyone.
                        </div>
                    ) : (
                        rules.map((rule) => {
                            const def = attributeLibrary.find((d) => d.id === rule.attributeDefinitionId)
                            const allowedOps = def === undefined ? [] : OPERATORS.filter((o) => o.types.includes(def.dataType))
                            return (
                                <div key={rule.key} className="row g-2 align-items-center mb-2">
                                    <Form.Group className="col-md-4">
                                        <Form.Select
                                            value={rule.attributeDefinitionId}
                                            onChange={(e) =>
                                                setRules((cur) =>
                                                    cur.map((r) =>
                                                        r.key === rule.key
                                                            ? { ...r, attributeDefinitionId: Number(e.target.value) }
                                                            : r
                                                    )
                                                )
                                            }
                                        >
                                            {attributeLibrary.map((d) => (
                                                <option key={d.id} value={d.id}>
                                                    {d.name} ({d.dataType})
                                                </option>
                                            ))}
                                        </Form.Select>
                                    </Form.Group>
                                    <Form.Group className="col-md-2">
                                        <Form.Select
                                            value={rule.operator}
                                            onChange={(e) =>
                                                setRules((cur) =>
                                                    cur.map((r) =>
                                                        r.key === rule.key
                                                            ? { ...r, operator: e.target.value as AccessRuleOperator }
                                                            : r
                                                    )
                                                )
                                            }
                                        >
                                            {allowedOps.map((o) => (
                                                <option key={o.value} value={o.value}>
                                                    {o.label}
                                                </option>
                                            ))}
                                        </Form.Select>
                                    </Form.Group>
                                    <Form.Group className="col-md-5">
                                        <Form.Control
                                            value={String(rule.value ?? '')}
                                            onChange={(e) =>
                                                setRules((cur) =>
                                                    cur.map((r) =>
                                                        r.key === rule.key ? { ...r, value: e.target.value } : r
                                                    )
                                                )
                                            }
                                        />
                                    </Form.Group>
                                    <div className="col-md-1">
                                        <Button
                                            size="sm"
                                            variant="outline-danger"
                                            onClick={() => removeRule(rule.key)}
                                        >
                                            ×
                                        </Button>
                                    </div>
                                </div>
                            )
                        })
                    )}
                </>
            )}

            <div className="mt-4 d-flex gap-2">
                <Button onClick={() => void submit()} disabled={saving}>
                    {saving ? '…' : 'Save'}
                </Button>
                <Button variant="secondary" onClick={() => navigate('/positions')}>
                    Cancel
                </Button>
            </div>
        </div>
    )
}