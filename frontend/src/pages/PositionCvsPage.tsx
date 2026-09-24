import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { Alert, Button, Spinner, Table } from 'react-bootstrap'
import { cvApi, type CvView } from '../api/cvs'
import Markdown from '../components/Markdown'

export default function PositionCvsPage() {
    const { id } = useParams<{ id: string }>()
    const [rows, setRows] = useState<CvView[]>([])
    const [positionTitle, setPositionTitle] = useState('')
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState('')
    const [preview, setPreview] = useState<CvView | null>(null)
    const [selected, setSelected] = useState<number[]>([])

    useEffect(() => {
        void (async () => {
            try {
                const data = await cvApi.listForPosition(Number(id))
                setRows(data.cvs)
                setPositionTitle(data.position.title)
            } catch (e: unknown) {
                const status = (e as { response?: { status?: number } })?.response?.status
                if (status === 403) {
                    setError('Only recruiters may browse CVs.')
                } else {
                    setError((e as Error).message)
                }
            } finally {
                setLoading(false)
            }
        })()
    }, [id])

    if (loading) return <Spinner animation="border" />
    if (error !== '') return <Alert variant="danger">{error}</Alert>

    const toggleSelect = (id: number) =>
        setSelected((cur) => (cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]))

    if (rows.length === 0) {
        return (
            <div>
                <h1 className="mb-3">CVs — {positionTitle}</h1>
                <div className="text-muted">No published CVs yet for this position.</div>
            </div>
        )
    }

    return (
        <div>
            <h1 className="mb-3">CVs — {positionTitle}</h1>

            {selected.length > 0 && (
                <div className="alert alert-secondary d-flex justify-content-between align-items-center">
                    <span>{selected.length} selected</span>
                    <div className="d-flex gap-2">
                        {selected.length === 1 && (
                            <Button
                                size="sm"
                                variant="outline-info"
                                onClick={() => {
                                    const cv = rows.find((r) => r.cv.id === selected[0])
                                    if (cv !== undefined) setPreview(cv)
                                }}
                            >
                                Preview selected
                            </Button>
                        )}
                    </div>
                </div>
            )}

            <Table hover responsive className="align-middle">
                <thead>
                    <tr>
                        <th style={{ width: 40 }}>☑</th>
                        <th>Candidate</th>
                        <th>Likes</th>
                        <th>Published</th>
                        <th>Empty attrs</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((cv) => (
                        <tr
                            key={cv.cv.id}
                            onClick={() => toggleSelect(cv.cv.id)}
                            role="button"
                            className={selected.includes(cv.cv.id) ? 'table-active' : ''}
                        >
                            <td onClick={(e) => e.stopPropagation()}>
                                <input
                                    type="checkbox"
                                    className="form-check-input"
                                    checked={selected.includes(cv.cv.id)}
                                    onChange={() => toggleSelect(cv.cv.id)}
                                />
                            </td>
                            <td className="fw-semibold">
                                {cv.candidate.firstName ?? '—'} {cv.candidate.lastName ?? ''}
                                <br />
                                <small className="text-muted">{cv.candidate.location ?? '—'}</small>
                            </td>
                            <td>★ {cv.likeCount}</td>
                            <td className="small">{cv.cv.publishedAt ?? '—'}</td>
                            <td>
                                <span className="badge text-bg-warning">
                                    {cv.attributes.filter((a) => a.empty).length}
                                </span>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </Table>

            {preview !== null && (
                <div className="modal show d-block" tabIndex={-1} role="dialog">
                    <div className="modal-dialog modal-lg">
                        <div className="modal-content">
                            <div className="modal-header">
                                <h5 className="modal-title">
                                    {preview.candidate.firstName} {preview.candidate.lastName}
                                </h5>
                                <button className="btn-close" onClick={() => setPreview(null)} />
                            </div>
                            <div className="modal-body">
                                <Table size="sm" className="mb-3">
                                    <tbody>
                                        {preview.attributes.map((a) => (
                                            <tr key={a.attributeDefinitionId} className={a.empty ? 'table-danger' : ''}>
                                                <td className="fw-semibold">{a.name}</td>
                                                <td>{a.empty ? 'Empty' : String(a.value ?? '—')}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </Table>
                                <h6>Projects</h6>
                                {preview.projects.map((p) => (
                                    <div key={p.id} className="mb-2">
                                        <strong>{p.name}</strong>
                                        <Markdown source={p.markdownDescription} className="small" />
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            )}
            {preview !== null && <div className="modal-backdrop show" />}
        </div>
    )
}