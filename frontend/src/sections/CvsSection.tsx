import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { Spinner, Table } from 'react-bootstrap'
import { cvApi, type CvSummary } from '../api/cvs'

export default function CvsSection() {
    const [items, setItems] = useState<CvSummary[]>([])
    const [loading, setLoading] = useState(true)

    useEffect(() => {
        void (async () => {
            setItems(await cvApi.list())
            setLoading(false)
        })()
    }, [])

    if (loading) return <Spinner animation="border" />

    if (items.length === 0) {
        return (
            <div className="text-muted">
                No CVs yet. Open a position and click <strong>Build CV</strong>.
            </div>
        )
    }

    return (
        <Table hover responsive className="align-middle">
            <thead>
                <tr>
                    <th>Position</th>
                    <th>Status</th>
                    <th>Published</th>
                    <th>Updated</th>
                    <th>Likes</th>
                    <th>Access</th>
                </tr>
            </thead>
            <tbody>
                {items.map((c) => (
                    <tr key={c.id}>
                        <td>
                            <Link to={`/cvs/${c.id}`} className="fw-semibold text-decoration-none">
                                {c.positionTitle}
                            </Link>
                        </td>
                        <td>
                            {c.status === 'DRAFT' ? (
                                <span className="badge text-bg-secondary">DRAFT</span>
                            ) : (
                                <span className="badge text-bg-success">PUBLISHED</span>
                            )}
                        </td>
                        <td>{c.publishedAt ?? '—'}</td>
                        <td className="small">{c.updatedAt}</td>
                        <td>{c.likeCount}</td>
                        <td>
                            {c.accessible ? (
                                <span className="text-success">✓</span>
                            ) : (
                                <span className="text-danger">lost</span>
                            )}
                        </td>
                    </tr>
                ))}
            </tbody>
        </Table>
    )
}