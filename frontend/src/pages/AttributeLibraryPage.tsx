import { useEffect, useState } from 'react'
import { Button, Form, Modal, Table } from 'react-bootstrap'
import { attributeApi, type CreateAttributePayload } from '../api/attributes'
import type { AttributeCategory, AttributeDefinition, DataType } from '../types'
import { useT } from '../contexts/AppPreferencesContext'

const DATA_TYPE_VALUES: DataType[] = [
    'string',
    'text',
    'image',
    'numeric',
    'date',
    'period',
    'boolean',
    'one_of_many',
]

interface EditorState {
    open: boolean
    editing: AttributeDefinition | null
    name: string
    description: string
    dataType: DataType
    categoryId: number | null
    required: boolean
    options: { value: string; sortOrder: number; id?: number }[]
}

const EMPTY_EDITOR: EditorState = {
    open: true,
    editing: null,
    name: '',
    description: '',
    dataType: 'string',
    categoryId: null,
    required: false,
    options: [],
}

export default function AttributeLibraryPage() {
    const { t, tPlural } = useT()
    const [items, setItems] = useState<AttributeDefinition[]>([])
    const [categories, setCategories] = useState<AttributeCategory[]>([])
    const [selectedIds, setSelectedIds] = useState<number[]>([])
    const [editor, setEditor] = useState<EditorState | null>(null)
    const [error, setError] = useState<string>('')

    const refresh = async () => {
        setItems(await attributeApi.list())
        setCategories(await attributeApi.listCategories())
    }

    useEffect(() => {
        void refresh()
    }, [])

    const openCreate = () => {
        setEditor({ ...EMPTY_EDITOR, categoryId: categories[0]?.id ?? null })
    }

    const openEdit = (a: AttributeDefinition) => {
        setEditor({
            open: true,
            editing: a,
            name: a.name,
            description: a.description,
            dataType: a.dataType,
            categoryId: a.categoryId,
            required: a.required,
            // Preserve each option's server-side id — the backend uses
            // it to UPDATE existing rows in place instead of INSERTing
            // duplicates. New options the recruiter types into the
            // editor below are pushed with id: undefined.
            options: (a.options ?? []).map((o) => ({
                id: o.id,
                value: o.value,
                sortOrder: o.sortOrder,
            })),
        })
    }

    const close = () => setEditor(null)

    const submit = async () => {
        if (editor === null) return
        setError('')
        try {
            if (editor.categoryId === null) {
                setError(t('attr_library.error.pick_category'))
                return
            }
            const payload: CreateAttributePayload = {
                name: editor.name.trim(),
                description: editor.description,
                dataType: editor.dataType,
                categoryId: editor.categoryId,
                required: editor.required,
                options:
                    editor.dataType === 'one_of_many'
                        ? editor.options
                        : undefined,
            }
            if (editor.editing) {
                await attributeApi.update(editor.editing.id, payload)
            } else {
                await attributeApi.create(payload)
            }
            close()
            await refresh()
        } catch (e: unknown) {
            const msg = (e as { response?: { data?: { error?: string } } })?.response?.data?.error
            setError(msg ?? t('attr_library.error.save_failed'))
        }
    }

    const bulkDelete = async () => {
        if (selectedIds.length === 0) return
        if (!window.confirm(tPlural('attr_library.delete.confirm', selectedIds.length, { count: selectedIds.length }))) return
        await Promise.all(selectedIds.map((id) => attributeApi.delete(id).catch(() => {})))
        setSelectedIds([])
        await refresh()
    }

    const toggleSelect = (id: number) => {
        setSelectedIds((cur) =>
            cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]
        )
    }
    const toggleAll = () => {
        setSelectedIds((cur) =>
            cur.length === items.length ? [] : items.map((a) => a.id)
        )
    }

    // Per the assignment rule, table rows must NOT carry View/Edit/Delete
    // buttons. Actions live in the toolbar above the table once the user
    // selects one or more rows. Editing requires exactly one selection
    // because the modal form needs a single target.
    const selectedSingle = selectedIds.length === 1 ? selectedIds[0] : null

    return (
        <div>
            <div className="d-flex justify-content-between align-items-center mb-3">
                <h1 className="mb-0">{t('attr.library.title')}</h1>
                <Button onClick={openCreate}>{t('attr.library.create')}</Button>
            </div>

            {selectedIds.length > 0 && (
                <div className="alert alert-secondary d-flex justify-content-between align-items-center">
                    <span>{selectedIds.length} selected</span>
                    <div className="d-flex gap-2">
                        {selectedSingle !== null && (
                            <Button
                                size="sm"
                                variant="outline-secondary"
                                onClick={() => {
                                    const a = items.find((x) => x.id === selectedSingle)
                                    if (a !== undefined) openEdit(a)
                                }}
                            >
                                {t('common.edit')} selected
                            </Button>
                        )}
                        <Button
                            size="sm"
                            variant="danger"
                            onClick={() => void bulkDelete()}
                        >
                            {t('common.delete')} selected
                        </Button>
                    </div>
                </div>
            )}

            {items.length === 0 ? (
                <div className="text-muted">{t('attr.library.empty')}</div>
            ) : (
                <Table hover responsive className="align-middle">
                    <thead>
                        <tr>
                            <th style={{ width: 40 }}>
                                <Form.Check
                                    type="checkbox"
                                    checked={selectedIds.length === items.length && items.length > 0}
                                    onChange={toggleAll}
                                    aria-label={t('attr_library.aria.select_all')}
                                />
                            </th>
                            <th>{t('attr.library.name')}</th>
                            <th>{t('attr.library.category')}</th>
                            <th>{t('attr.library.type')}</th>
                            <th>{t('attr.library.required')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((a) => (
                            <tr
                                key={a.id}
                                onClick={() => toggleSelect(a.id)}
                                role="button"
                                className={selectedIds.includes(a.id) ? 'table-active' : ''}
                            >
                                <td onClick={(e) => e.stopPropagation()}>
                                    <Form.Check
                                        type="checkbox"
                                        checked={selectedIds.includes(a.id)}
                                        onChange={() => toggleSelect(a.id)}
                                        aria-label={`Select ${a.name}`}
                                    />
                                </td>
                                <td className="fw-semibold">{a.name}</td>
                                <td>{a.categoryName}</td>
                                <td><code>{t(`data_type.${a.dataType}`)}</code></td>
                                <td>{a.required ? '✓' : ''}</td>
                            </tr>
                        ))}
                    </tbody>
                </Table>
            )}

            <Modal show={editor !== null} onHide={close} size="lg">
                <Modal.Header closeButton>
                    <Modal.Title>
                        {editor?.editing ? t('common.edit') : t('common.create')}
                    </Modal.Title>
                </Modal.Header>
                <Modal.Body>
                    {editor && (
                        <div className="d-flex flex-column gap-3">
                            <Form.Group>
                                <Form.Label>{t('attr.library.name')}</Form.Label>
                                <Form.Control
                                    value={editor.name}
                                    onChange={(e) =>
                                        setEditor({ ...editor, name: e.target.value })
                                    }
                                />
                            </Form.Group>

                            <Form.Group>
                                <Form.Label>{t('attr.library.description')}</Form.Label>
                                <Form.Control
                                    as="textarea"
                                    rows={2}
                                    value={editor.description}
                                    onChange={(e) =>
                                        setEditor({ ...editor, description: e.target.value })
                                    }
                                />
                            </Form.Group>

                            <div className="row">
                                <Form.Group className="col-md-6">
                                    <Form.Label>{t('attr.library.category')}</Form.Label>
                                    <Form.Select
                                        value={editor.categoryId ?? ''}
                                        onChange={(e) =>
                                            setEditor({
                                                ...editor,
                                                categoryId: Number(e.target.value),
                                            })
                                        }
                                    >
                                        <option value="">…</option>
                                        {categories.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.name}
                                            </option>
                                        ))}
                                    </Form.Select>
                                </Form.Group>

                                <Form.Group className="col-md-6">
                                    <Form.Label>{t('attr.library.type')}</Form.Label>
                                    <Form.Select
                                        value={editor.dataType}
                                        onChange={(e) =>
                                            setEditor({
                                                ...editor,
                                                dataType: e.target.value as DataType,
                                                options:
                                                    e.target.value === 'one_of_many'
                                                        ? editor.options
                                                        : [],
                                            })
                                        }
                                    >
                                        {DATA_TYPE_VALUES.map((dt) => (
                                            <option key={dt} value={dt}>
                                                {t(`data_type.${dt}`)}
                                            </option>
                                        ))}
                                    </Form.Select>
                                </Form.Group>
                            </div>

                            <Form.Check
                                type="switch"
                                label={t('attr.library.required')}
                                checked={editor.required}
                                onChange={(e) =>
                                    setEditor({ ...editor, required: e.target.checked })
                                }
                            />

                            {editor.dataType === 'one_of_many' && (
                                <Form.Group>
                                    <Form.Label>{t('attr.library.options')}</Form.Label>
                                    {editor.options.map((opt, idx) => (
                                        <div
                                            key={idx}
                                            className="d-flex gap-2 mb-2"
                                        >
                                            <Form.Control
                                                value={opt.value}
                                                onChange={(e) => {
                                                    const next = [...editor.options]
                                                    next[idx] = { ...opt, value: e.target.value }
                                                    setEditor({ ...editor, options: next })
                                                }}
                                            />
                                            <Button
                                                variant="outline-danger"
                                                onClick={() => {
                                                    const next = editor.options.filter(
                                                        (_, i) => i !== idx
                                                    )
                                                    setEditor({ ...editor, options: next })
                                                }}
                                            >
                                                ×
                                            </Button>
                                        </div>
                                    ))}
                                    <Button
                                        size="sm"
                                        variant="outline-secondary"
                                        onClick={() =>
                                            setEditor({
                                                ...editor,
                                                options: [
                                                    ...editor.options,
                                                    { value: '', sortOrder: editor.options.length },
                                                ],
                                            })
                                        }
                                    >
                                        {t('attr.library.addOption')}
                                    </Button>
                                </Form.Group>
                            )}

                            {error && <div className="text-danger small">{error}</div>}
                        </div>
                    )}
                </Modal.Body>
                <Modal.Footer>
                    <Button variant="secondary" onClick={close}>
                        {t('common.cancel')}
                    </Button>
                    <Button onClick={() => void submit()}>{t('common.save')}</Button>
                </Modal.Footer>
            </Modal>
        </div>
    )
}