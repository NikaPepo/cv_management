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
import type { AttributeDefinition, DataType } from '../types'
import TagInput from '../components/TagInput'
import TypedInput from '../components/TypedInput'
import { useT } from '../contexts/AppPreferencesContext'

const LEVEL_VALUES: PositionLevel[] = ['junior', 'middle', 'senior', 'c_level']

/**
 * Operators allowed per dataType. Mirrors
 * App\Enum\AccessRuleOperator::allowedFor() on the backend — kept in
 * sync by hand because the editor needs to filter the dropdown before
 * the user picks a value. The backend is still the source of truth
 * (PositionService::syncAccessRules rejects incompatible operators
 * with 422).
 */
const OPERATORS_BY_TYPE: Record<DataType, AccessRuleOperator[]> = {
    numeric: ['eq', 'ne', 'gt', 'gte', 'lt', 'lte'],
    boolean: ['eq'],
    one_of_many: ['eq', 'ne', 'in'],
    date: ['eq', 'before', 'after'],
    period: ['eq', 'before', 'after'],
    string: ['eq', 'ne', 'contains'],
    text: ['eq', 'ne', 'contains'],
    image: ['eq', 'ne'],
}

interface AccessRuleDraft {
    key: string
    attributeDefinitionId: number
    operator: AccessRuleOperator
    value: unknown
}

/**
 * Picks a sane default value for a freshly-added rule, given the
 * attribute's dataType. Keeps the editor from sending booleans as
 * strings, dates as empty strings, etc.
 */
function defaultValueFor(dataType: DataType | undefined): unknown {
    switch (dataType) {
        case 'boolean':
            return false
        case 'period':
            return { start: null, end: null }
        default:
            return null
    }
}

/**
 * Picks a default value for the rule that depends on BOTH the attribute
 * dataType AND the operator. Used when the recruiter switches operator
 * within the same attribute (e.g. eq → in on a one_of_many) so the
 * previous scalar value doesn't leak across as the wrong type.
 */
function defaultValueForOperator(
    dataType: DataType | undefined,
    operator: AccessRuleOperator,
): unknown {
    if (dataType === 'one_of_many' && operator === 'in') {
        return []
    }
    return defaultValueFor(dataType)
}

/**
 * Returns whether a value for the given operator/dataType is a single
 * scalar ("one") or a list ("many"). Used to decide whether a stale
 * value must be reset when the recruiter switches operator within the
 * same attribute.
 */
function operatorValueShape(
    operator: AccessRuleOperator,
    dataType: DataType | undefined,
): 'one' | 'many' {
    if (dataType === 'one_of_many' && operator === 'in') return 'many'
    return 'one'
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
        // eslint-disable-next-line react-hooks/exhaustive-deps
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
        // Prefer an attribute that's NOT already chosen in another rule
        // so the recruiter doesn't accidentally create two rules on the
        // same attribute (which the evaluator would treat as AND).
        const usedIds = new Set(rules.map((r) => r.attributeDefinitionId))
        const def = attributeLibrary.find((d) => !usedIds.has(d.id)) ?? attributeLibrary[0]
        if (def === undefined) return
        const allowed = OPERATORS_BY_TYPE[def.dataType] ?? ['eq']
        const initialOp = allowed[0]
        setRules((cur) => [
            ...cur,
            {
                key: `new-${Date.now()}`,
                attributeDefinitionId: def.id,
                operator: initialOp,
                value: defaultValueForOperator(def.dataType, initialOp),
            },
        ])
    }

    const removeRule = (key: string) => setRules((cur) => cur.filter((r) => r.key !== key))

    /**
     * When the recruiter swaps the attribute on a rule, the previous
     * operator and value are almost certainly incompatible with the new
     * dataType. Reset both: pick the first compatible operator and a
     * sensible empty value for the new dataType. The PositionService
     * would also reject the bad combination with 422, but this keeps
     * the editor self-consistent on its own.
     */
    const changeRuleAttribute = (key: string, newDefId: number) => {
        const newDef = attributeLibrary.find((d) => d.id === newDefId)
        const allowed = newDef === undefined ? ['eq' as AccessRuleOperator] : (OPERATORS_BY_TYPE[newDef.dataType] ?? ['eq'])
        setRules((cur) =>
            cur.map((r) =>
                r.key === key
                    ? {
                          ...r,
                          attributeDefinitionId: newDefId,
                          operator: allowed[0],
                          value: defaultValueFor(newDef?.dataType),
                      }
                    : r,
            ),
        )
    }

    /**
     * Switching operator within the same attribute also requires a
     * value reset if the operator semantics changed shape — most
     * importantly, eq/ne use a single option id while in uses a list.
     * Carrying the old scalar across would round-trip as the wrong
     * payload and the backend would 422.
     */
    const changeRuleOperator = (key: string, newOp: AccessRuleOperator) => {
        setRules((cur) =>
            cur.map((r) => {
                if (r.key !== key) return r
                const def = attributeLibrary.find((d) => d.id === r.attributeDefinitionId)
                // Only reset value when the operator semantics differ —
                // for the same shape (e.g. eq → ne, both scalar ids)
                // the existing value still fits.
                const currentShape = operatorValueShape(r.operator, def?.dataType)
                const nextShape = operatorValueShape(newOp, def?.dataType)
                return {
                    ...r,
                    operator: newOp,
                    value: currentShape === nextShape
                        ? r.value
                        : defaultValueForOperator(def?.dataType, newOp),
                }
            }),
        )
    }

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
                        <option value="">{t('level.all')}</option>
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
                            const allowedOps = def === undefined
                                ? []
                                : OPERATORS_BY_TYPE[def.dataType] ?? ['eq']
                            return (
                                <div key={rule.key} className="card mb-2">
                                    <div className="card-body">
                                        <div className="row g-2 align-items-center">
                                            <Form.Group className="col-md-5">
                                                <Form.Select
                                                    value={rule.attributeDefinitionId}
                                                    onChange={(e) =>
                                                        changeRuleAttribute(rule.key, Number(e.target.value))
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
                                                        changeRuleOperator(
                                                            rule.key,
                                                            e.target.value as AccessRuleOperator,
                                                        )
                                                    }
                                                >
                                                    {allowedOps.map((o) => (
                                                        <option key={o} value={o}>
                                                            {t(`operator.${o}`)}
                                                        </option>
                                                    ))}
                                                </Form.Select>
                                            </Form.Group>
                                            <Form.Group className="col-md-4">
                                                {def === undefined ? (
                                                    <div className="text-muted small">
                                                        {t('editor.access.definition_missing')}
                                                    </div>
                                                ) : (
                                                    <TypedInput
                                                        dataType={def.dataType}
                                                        definition={def}
                                                        value={rule.value}
                                                        multiple={
                                                            def.dataType === 'one_of_many' && rule.operator === 'in'
                                                        }
                                                        onChange={(next) =>
                                                            setRules((cur) =>
                                                                cur.map((r) =>
                                                                    r.key === rule.key ? { ...r, value: next } : r,
                                                                ),
                                                            )
                                                        }
                                                    />
                                                )}
                                            </Form.Group>
                                            <div className="col-md-1 d-flex align-items-end">
                                                <Button
                                                    size="sm"
                                                    variant="outline-danger"
                                                    onClick={() => removeRule(rule.key)}
                                                    aria-label={t('editor.access.remove_rule')}
                                                >
                                                    ×
                                                </Button>
                                            </div>
                                        </div>
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