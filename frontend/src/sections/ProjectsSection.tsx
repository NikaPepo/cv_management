import { useEffect, useState } from 'react'
import { Button, Form, Modal, Table } from 'react-bootstrap'
import { projectApi, type CreateProjectPayload, type Project } from '../api/projects'
import TagInput from '../components/TagInput'
import Markdown from '../components/Markdown'

interface EditorState {
    open: boolean
    project: Project | null
    name: string
    periodStart: string
    periodEnd: string
    markdownDescription: string
    technologyTags: string[]
}

const EMPTY_EDITOR: EditorState = {
    open: true,
    project: null,
    name: '',
    periodStart: '',
    periodEnd: '',
    markdownDescription: '',
    technologyTags: [],
}

export default function ProjectsSection() {
    const [items, setItems] = useState<Project[]>([])
    const [editor, setEditor] = useState<EditorState | null>(null)
    const [selected, setSelected] = useState<number[]>([])
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState('')
    const [preview, setPreview] = useState<Project | null>(null)

    const refresh = async () => {
        setLoading(true)
        setItems(await projectApi.list())
        setLoading(false)
    }

    useEffect(() => {
        void refresh()
    }, [])

    const openCreate = () => setEditor({ ...EMPTY_EDITOR })

    const openEdit = (p: Project) =>
        setEditor({
            open: true,
            project: p,
            name: p.name,
            periodStart: p.periodStart ?? '',
            periodEnd: p.periodEnd ?? '',
            markdownDescription: p.markdownDescription ?? '',
            technologyTags: p.technologyTags.map((t) => t.name),
        })

    const close = () => setEditor(null)

    const submit = async () => {
        if (editor === null) return
        setError('')
        try {
            const payload: CreateProjectPayload = {
                name: editor.name.trim(),
                periodStart: editor.periodStart === '' ? null : editor.periodStart,
                periodEnd: editor.periodEnd === '' ? null : editor.periodEnd,
                markdownDescription:
                    editor.markdownDescription === '' ? null : editor.markdownDescription,
                technologyTagNames: editor.technologyTags,
            }
            if (editor.project !== null) {
                await projectApi.update(editor.project.id, payload)
            } else {
                await projectApi.create(payload)
            }
            close()
            await refresh()
        } catch (e: unknown) {
            const msg = (e as { response?: { data?: { error?: string } } })?.response?.data?.error
            setError(msg ?? 'Failed to save.')
        }
    }

    const bulkDelete = async () => {
        if (selected.length === 0) return
        if (!window.confirm(`Delete ${selected.length} project(s)?`)) return
        await Promise.all(selected.map((id) => projectApi.remove(id).catch(() => {})))
        setSelected([])
        await refresh()
    }

    const toggleSelect = (id: number) =>
        setSelected((cur) => (cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]))
    const toggleAll = () =>
        setSelected((cur) => (cur.length === items.length ? [] : items.map((p) => p.id)))

    // Per assignment rule, table rows must NOT carry View/Edit/Delete
    // buttons. Per-row actions live in the toolbar: edit/preview require
    // exactly one row selected, delete supports bulk.
    const selectedSingle = selected.length === 1 ? selected[0] : null

    return (
        <div>
            <div className="d-flex justify-content-between align-items-center mb-3">
                <h3 className="mb-0">Projects</h3>
                <Button onClick={openCreate}>New project</Button>
            </div>

            {selected.length > 0 && (
                <div className="alert alert-secondary d-flex justify-content-between align-items-center">
                    <span>{selected.length} selected</span>
                    <div className="d-flex gap-2">
                        {selectedSingle !== null && (
                            <>
                                <Button
                                    size="sm"
                                    variant="outline-secondary"
                                    onClick={() => {
                                        const p = items.find((x) => x.id === selectedSingle)
                                        if (p !== undefined) openEdit(p)
                                    }}
                                >
                                    Edit selected
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline-info"
                                    onClick={() => {
                                        const p = items.find((x) => x.id === selectedSingle)
                                        if (p !== undefined) setPreview(p)
                                    }}
                                >
                                    Preview selected
                                </Button>
                            </>
                        )}
                        <Button size="sm" variant="danger" onClick={() => void bulkDelete()}>
                            Delete selected
                        </Button>
                    </div>
                </div>
            )}

            {loading ? (
                <div>Loading…</div>
            ) : items.length === 0 ? (
                <div className="text-muted">No projects yet.</div>
            ) : (
                <Table hover responsive className="align-middle">
                    <thead>
                        <tr>
                            <th style={{ width: 40 }}>
                                <Form.Check
                                    type="checkbox"
                                    checked={selected.length === items.length}
                                    onChange={toggleAll}
                                />
                            </th>
                            <th>Name</th>
                            <th>Period</th>
                            <th>Tags</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((p) => (
                            <tr
                                key={p.id}
                                onClick={() => toggleSelect(p.id)}
                                className={selected.includes(p.id) ? 'table-active' : ''}
                                role="button"
                            >
                                <td onClick={(e) => e.stopPropagation()}>
                                    <Form.Check
                                        type="checkbox"
                                        checked={selected.includes(p.id)}
                                        onChange={() => toggleSelect(p.id)}
                                    />
                                </td>
                                <td className="fw-semibold">{p.name}</td>
                                <td className="small">
                                    {p.periodStart ?? '…'} → {p.periodEnd ?? '…'}
                                </td>
                                <td>
                                    {p.technologyTags.map((t) => (
                                        <span key={t.id} className="badge text-bg-secondary me-1">
                                            {t.name}
                                        </span>
                                    ))}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </Table>
            )}

            <Modal show={editor !== null} onHide={close} size="lg">
                <Modal.Header closeButton>
                    <Modal.Title>
                        {editor?.project !== null && editor?.project !== undefined ? 'Edit project' : 'New project'}
                    </Modal.Title>
                </Modal.Header>
                <Modal.Body>
                    {editor && (
                        <div className="d-flex flex-column gap-3">
                            <Form.Group>
                                <Form.Label>Name</Form.Label>
                                <Form.Control
                                    value={editor.name}
                                    onChange={(e) =>
                                        setEditor({ ...editor, name: e.target.value })
                                    }
                                />
                            </Form.Group>
                            <div className="row">
                                <Form.Group className="col-md-6">
                                    <Form.Label>Period start</Form.Label>
                                    <Form.Control
                                        type="date"
                                        value={editor.periodStart}
                                        onChange={(e) =>
                                            setEditor({ ...editor, periodStart: e.target.value })
                                        }
                                    />
                                </Form.Group>
                                <Form.Group className="col-md-6">
                                    <Form.Label>Period end</Form.Label>
                                    <Form.Control
                                        type="date"
                                        value={editor.periodEnd}
                                        onChange={(e) =>
                                            setEditor({ ...editor, periodEnd: e.target.value })
                                        }
                                    />
                                </Form.Group>
                            </div>
                            <Form.Group>
                                <Form.Label>Tags</Form.Label>
                                <TagInput
                                    value={editor.technologyTags}
                                    onChange={(next) =>
                                        setEditor({ ...editor, technologyTags: next })
                                    }
                                />
                            </Form.Group>
                            <Form.Group>
                                <Form.Label>Description (Markdown)</Form.Label>
                                <Form.Control
                                    as="textarea"
                                    rows={6}
                                    value={editor.markdownDescription}
                                    onChange={(e) =>
                                        setEditor({
                                            ...editor,
                                            markdownDescription: e.target.value,
                                        })
                                    }
                                />
                            </Form.Group>
                            {error !== '' && <div className="text-danger small">{error}</div>}
                        </div>
                    )}
                </Modal.Body>
                <Modal.Footer>
                    <Button variant="secondary" onClick={close}>
                        Cancel
                    </Button>
                    <Button onClick={() => void submit()}>Save</Button>
                </Modal.Footer>
            </Modal>

            <Modal show={preview !== null} onHide={() => setPreview(null)} size="lg">
                <Modal.Header closeButton>
                    <Modal.Title>{preview?.name}</Modal.Title>
                </Modal.Header>
                <Modal.Body>
                    <div className="mb-3">
                        {preview?.technologyTags.map((t) => (
                            <span key={t.id} className="badge text-bg-secondary me-1">
                                {t.name}
                            </span>
                        ))}
                    </div>
                    <Markdown source={preview?.markdownDescription ?? null} />
                </Modal.Body>
            </Modal>
        </div>
    )
}