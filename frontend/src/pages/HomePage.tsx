import { useEffect, useState } from 'react'
import { Spinner } from 'react-bootstrap'
import { Link } from 'react-router-dom'
import { mainPageApi, type MainPage } from '../api/mainPage'

export default function HomePage() {
    const [data, setData] = useState<MainPage | null>(null)
    const [error, setError] = useState('')

    useEffect(() => {
        void (async () => {
            try {
                setData(await mainPageApi.load())
            } catch (e: unknown) {
                setError((e as Error).message)
            }
        })()
    }, [])

    if (error !== '') return <div className="alert alert-danger">{error}</div>
    if (data === null) return <Spinner animation="border" />

    return (
        <div>
            <h1 className="mb-4">Dashboard</h1>

            <section className="mb-4">
                <h2 className="h5">Statistics</h2>
                <div className="row g-3">
                    <StatCard label="CVs (24h)" value={data.statistics.cvsLast24h} />
                    <StatCard label="Positions" value={data.statistics.totalPositions} />
                    <StatCard label="Candidates" value={data.statistics.totalCandidates} />
                    <StatCard label="Recruiters" value={data.statistics.totalRecruiters} />
                    <StatCard label="Published CVs" value={data.statistics.totalPublishedCvs} />
                </div>
            </section>

            <section className="mb-4">
                <h2 className="h5">Most popular positions (top 5)</h2>
                <table className="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Position</th>
                            <th>Company</th>
                            <th>Level</th>
                            <th>Submitted CVs</th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.popular.length === 0 ? (
                            <tr><td colSpan={4} className="text-muted">No positions yet.</td></tr>
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
                <h2 className="h5">Latest positions</h2>
                <table className="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Company</th>
                            <th>Level</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.latest.length === 0 ? (
                            <tr><td colSpan={4} className="text-muted">No positions yet.</td></tr>
                        ) : data.latest.map((p) => (
                            <tr key={p.id}>
                                <td>
                                    <Link to={`/positions/${p.id}`} className="fw-semibold text-decoration-none">
                                        {p.title}
                                    </Link>
                                </td>
                                <td>{p.company ?? '—'}</td>
                                <td>{p.level ?? '—'}</td>
                                <td className="small">{new Date(p.updatedAt).toLocaleDateString()}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>

            <section className="mb-4">
                <h2 className="h5">Tag cloud</h2>
                {data.tagCloud.length === 0 ? (
                    <div className="text-muted">
                        Tags appear once candidates create projects with technology tags.
                    </div>
                ) : (
                    <div>
                        {data.tagCloud.map((t) => (
                            <span
                                key={t.name}
                                className="badge text-bg-primary me-1 mb-1"
                                style={{ fontSize: 12 + Math.min(t.count, 8) * 2 }}
                            >
                                {t.name} ({t.count})
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