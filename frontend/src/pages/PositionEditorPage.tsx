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
import { useT } from '../contexts/AppPreferencesContext'

const LEVEL_VALUES: PositionLevel[] = ['junior', 'middle', 'senior', 'c_level']

// Operator UIs: each entry carries its data types so the access-rule
// dropdown only shows operators that are meaningful for the chosen attribute.
// Labels are produced at render time via t(`operator.${value}`) so they stay
// in lockstep with the locale. Internal `value` stays in English for the
// backend contract.
const OPERATORS: { value: AccessRuleOperator; types: string[] }[] = [
    { value: 'eq', types: ['numeric', 'string', 'text', 'one_of_many', 'date', 'period', 'boolean', 'image'] },
    { value: 'ne', types: ['numeric', 'string', 'text', 'one_of_many', 'image'] },
    { value: 'gt', types: ['numeric'] },
    { value: 'gte', types: ['numeric'] },
    { value: 'lt', types: ['numeric'] },
    { value: 'lte', types: ['numeric'] },
    { value: 'in', types: ['one_of_many'] },
    { value: 'contains', types: ['string', 'text'] },
    { value: 'before', types: ['date', 'period'] },
    { value: 'after', types: ['date', 'period'] },
]

interface AccessRuleDraft {
    key: string
    attributeDefinitionId: number
    operator: AccessRuleOperator
    value: unknown
}

export default function PositionEditorPage() {
    const { t } = useT()
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
                setError(t('editor.save.conflict'))
            } else {
                const msg = err?.response?.data?.error
                setError(msg ?? t('editor.save.failed'))
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
            <h1 className="mb-3">{editing ? t('editor.title.edit') : t('editor.title.new')}</h1>
            {error !== '' && (
                <Alert variant={conflict ? 'warning' : 'danger'}>{error}</Alert>
            )}

            <div className="row g-3 mb-4">
                <Form.Group className="col-md-8">
                    <Form.Label>{t('editor.label.title')}</Form.Label>
                    <Form.Control value={title} onChange={(e) => setTitle(e.target.value)} />
                </Form.Group>
                <Form.Group className="col-md-4">
                    <Form.Label>{t('editor.label.company')}</Form.Label>
                    <Form.Control value={company} onChange={(e) => setCompany(e.target.value)} />
                </Form.Group>
                <Form.Group className="col-12">
                    <Form.Label>{t('editor.label.short_description')}</Form.Label>
                    <Form.Control
                        as="textarea"
                        rows={2}
                        value={shortDescription}
                        onChange={(e) => setShortDescription(e.target.value)}
                    />
                </Form.Group>
                <Form.Group className="col-md-3">
                    <Form.Label>{t('editor.label.level')}</Form.Label>
                    <Form.Select
                        value={level}
                        onChange={(e) => setLevel(e.target.value as PositionLevel | '')}
                    >
                        <option value="">—</option>
                        {LEVEL_VALUES.map((l) => (
                            <option key={l} value={l}>
                                {t(`level.${l}`)}
                            </option>
                        ))}
                    </Form.Select>
                </Form.Group>
                <Form.Group className="col-md-3">
                    <Form.Label>{t('editor.label.max_projects')}</Form.Label>
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
                        label={t('editor.label.public_switch')}
                        checked={isPublic}
                        onChange={(e) => setIsPublic(e.target.checked)}
                    />
                </Form.Group>
                <Form.Group className="col-12">
                    <Form.Label>{t('editor.label.project_tag_filter')}</Form.Label>
                    <TagInput
                        value={projectTagFilter}
                        onChange={setProjectTagFilter}
                        placeholder={t('editor.placeholder.project_tag_filter')}
                    />
                </Form.Group>
            </div>

            <h3>{t('editor.attributes.title')}</h3>
            <div className="row mb-4">
                <div className="col-md-7">
                    {chosenAttributes.length === 0 ? (
                        <div className="text-muted">{t('editor.attributes.empty')}</div>
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
                                            <small className="text-muted">({t(`data_type.${def.dataType}`)})</small>
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
                            <h6 className="card-title">{t('editor.attribute_library.title')}</h6>
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
                                            <small className="text-muted">{t(`data_type.${def.dataType}`)}</small>
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
                        <h3 className="mb-0">{t('editor.access.title')}</h3>
                        <Button size="sm" variant="outline-secondary" onClick={addRule}>
                            {t('editor.access.add_rule')}
                        </Button>
                    </div>

                    {rules.length === 0 ? (
                        <div className="text-muted mb-4">
                            {t('editor.access.empty')}
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
                                                    {d.name} ({t(`data_type.${d.dataType}`)})
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
                                                    {t(`operator.${o.value}`)}
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
                    {saving ? '…' : t('editor.save')}
                </Button>
                <Button variant="secondary" onClick={() => navigate('/positions')}>
                    {t('editor.cancel')}
                </Button>
            </div>
        </div>
    )
}