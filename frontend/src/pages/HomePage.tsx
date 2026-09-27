import { useEffect, useState } from 'react'
import { Spinner } from 'react-bootstrap'
import { Link } from 'react-router-dom'
import { mainPageApi, type MainPage } from '../api/mainPage'
import { formatDate, usePreferences, useT } from '../contexts/AppPreferencesContext'

export default function HomePage() {
    const { t } = useT()
    const { locale } = usePreferences()
    const [data, setData] = useState<MainPage | null>(null)
    const [error, setError] = useState('')

    useEffect(() => {
        void (async () => {
            try {
                setData(await mainPageApi.load())
            } catch {
                // Translate technical/axios errors into a friendly localized
                // message. Backend validation text (if any) is already in
                // `e.response?.data?.error` and would have surfaced here too,
                // so this `t()` is purely the fallback for transport errors.
                setError(t('error.generic'))
            }
        })()
    }, [t])

    if (error !== '') return <div className="alert alert-danger">{error}</div>
    if (data === null) return <Spinner animation="border" />

    return (
        <div>
            <h1 className="mb-4">{t('home.title')}</h1>

            <section className="mb-4">
                <h2 className="h5">{t('home.statistics')}</h2>
                <div className="row g-3">
                    <StatCard label={t('home.stat.cvs_24h')} value={data.statistics.cvsLast24h} />
                    <StatCard label={t('home.stat.positions')} value={data.statistics.totalPositions} />
                    <StatCard label={t('home.stat.candidates')} value={data.statistics.totalCandidates} />
                    <StatCard label={t('home.stat.recruiters')} value={data.statistics.totalRecruiters} />
                    <StatCard label={t('home.stat.published_cvs')} value={data.statistics.totalPublishedCvs} />
                </div>
            </section>

            <section className="mb-4">
                <h2 className="h5">{t('home.popular.title')}</h2>
                <table className="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>{t('home.popular.col.position')}</th>
                            <th>{t('home.popular.col.company')}</th>
                            <th>{t('home.popular.col.level')}</th>
                            <th>{t('home.popular.col.submitted_cvs')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.popular.length === 0 ? (
                            <tr><td colSpan={4} className="text-muted">{t('home.popular.empty')}</td></tr>
                        ) : data.popular.map((p) => (
                            <tr key={p.id}>
                                <td>
                                    <Link to={`/positions/${p.id}`} className="fw-semibold text-decoration-none">
                                        {p.title}
                                    </Link>
                                </td>
                                <td>{p.company ?? '—'}</td>
                                <td>{p.level ?? '—'}</td>
                                <td>{p.submittedCvs}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>

            <section className="mb-4">
                <h2 className="h5">{t('home.latest.title')}</h2>
                <table className="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>{t('home.latest.col.title')}</th>
                            <th>{t('home.latest.col.company')}</th>
                            <th>{t('home.latest.col.level')}</th>
                            <th>{t('home.latest.col.updated')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.latest.length === 0 ? (
                            <tr><td colSpan={4} className="text-muted">{t('home.latest.empty')}</td></tr>
                        ) : data.latest.map((p) => (
                            <tr key={p.id}>
                                <td>
                                    <Link to={`/positions/${p.id}`} className="fw-semibold text-decoration-none">
                                        {p.title}
                                    </Link>
                                </td>
                                <td>{p.company ?? '—'}</td>
                                <td>{p.level ?? '—'}</td>
                                <td className="small">{formatDate(p.updatedAt, locale)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>

            <section className="mb-4">
                <h2 className="h5">{t('home.tag_cloud.title')}</h2>
                {data.tagCloud.length === 0 ? (
                    <div className="text-muted">{t('home.tag_cloud.empty')}</div>
                ) : (
                    <div>
                        {data.tagCloud.map((tc) => (
                            <span
                                key={tc.name}
                                className="badge text-bg-primary me-1 mb-1"
                                style={{ fontSize: 12 + Math.min(tc.count, 8) * 2 }}
                            >
                                {tc.name} ({tc.count})
                            </span>
                        ))}
                    </div>
                )}
            </section>
        </div>
    )
}

function StatCard({ label, value }: { label: string; value: number }) {
    return (
        <div className="col-6 col-md-2">
            <div className="card">
                <div className="card-body text-center">
                    <div className="display-6 fw-semibold">{value}</div>
                    <div className="text-muted small">{label}</div>
                </div>
            </div>
        </div>
    )
}