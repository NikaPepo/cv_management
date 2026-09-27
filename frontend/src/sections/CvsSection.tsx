import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { Spinner, Table } from 'react-bootstrap'
import { cvApi, type CvSummary } from '../api/cvs'
import { formatDate, usePreferences, useT } from '../contexts/AppPreferencesContext'

export default function CvsSection() {
    const { t } = useT()
    const { locale } = usePreferences()
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
            <div className="text-muted">{t('cvs.empty')}</div>
        )
    }

    return (
        <Table hover responsive className="align-middle">
            <thead>
                <tr>
                    <th>{t('cvs.col.position')}</th>
                    <th>{t('cvs.col.status')}</th>
                    <th>{t('cvs.col.published')}</th>
                    <th>{t('cvs.col.updated')}</th>
                    <th>{t('cvs.col.likes')}</th>
                    <th>{t('cvs.col.access')}</th>
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
                                <span className="badge text-bg-secondary">{t('status.draft')}</span>
                            ) : (
                                <span className="badge text-bg-success">{t('status.published')}</span>
                            )}
                        </td>
                        <td>{c.publishedAt ? formatDate(c.publishedAt, locale) : '—'}</td>
                        <td className="small">{formatDate(c.updatedAt, locale)}</td>
                        <td>{c.likeCount}</td>
                        <td>
                            {c.accessible ? (
                                <span className="text-success">✓</span>
                            ) : (
                                <span className="text-danger">{t('access.lost')}</span>
                            )}
                        </td>
                    </tr>
                ))}
            </tbody>
        </Table>
    )
}