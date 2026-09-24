import { useEffect, useState } from 'react'
import { useNavigate, useParams, Link } from 'react-router-dom'
import { Alert, Button, Spinner } from 'react-bootstrap'
import { positionApi, type Position } from '../api/positions'
import { cvApi } from '../api/cvs'
import Discussion from '../components/Discussion'
import { useAuth } from '../contexts/AuthContext'

type Tab = 'details' | 'discussion'

export default function PositionViewPage() {
    const { hasRole, user } = useAuth()
    const navigate = useNavigate()
    const { id } = useParams<{ id: string }>()
    const [position, setPosition] = useState<Position | null>(null)
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState('')
    const [building, setBuilding] = useState(false)
    const [tab, setTab] = useState<Tab>('details')

    const isCandidate = user !== null && !hasRole('ROLE_RECRUITER', 'ROLE_ADMIN')
    const isStaff = hasRole('ROLE_RECRUITER', 'ROLE_ADMIN')

    useEffect(() => {
        void (async () => {
            try {
                const data = await positionApi.get(Number(id))
                setPosition(data)
            } catch (e: unknown) {
                setError((e as Error).message)
            } finally {
                setLoading(false)
            }
        })()
    }, [id])

    const onBuildCv = async () => {
        setBuilding(true)
        try {
            const summary = await cvApi.create(Number(id))
            navigate(`/cvs/${summary.id}`)
        } catch (e: unknown) {
            const msg = (e as { response?: { data?: { error?: string } } })?.response?.data?.error
            setError(msg ?? 'Failed to build CV.')
        } finally {
            setBuilding(false)
        }
    }

    if (loading) return <Spinner animation="border" />
    if (error !== '' && position === null) return <Alert variant="danger">{error}</Alert>
    if (position === null) return null

    return (
        <div>
            <div className="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h1 className="mb-1">{position.title}</h1>
                    <p className="text-muted mb-0">{position.shortDescription}</p>
                </div>
                <div>
                    {isCandidate && position.accessible === true && (
                        <Button onClick={() => void onBuildCv()} disabled={building}>
                            {building ? '…' : 'Build CV'}
                        </Button>
                    )}
                    {isStaff && (
                        <Link to={`/positions/${position.id}/cvs`} className="btn btn-outline-secondary me-2">
                            Browse CVs
                        </Link>
                    )}
                    {isStaff && (
                        <Link to={`/positions/${position.id}/edit`} className="btn btn-outline-secondary">
                            Edit
                        </Link>
                    )}
                </div>
            </div>

            <ul className="nav nav-tabs mb-4">
                <li className="nav-item">
                    <button
                        className={`nav-link ${tab === 'details' ? 'active' : ''}`}
                        onClick={() => setTab('details')}
                    >
                        Details
                    </button>
                </li>
                <li className="nav-item">
                    <button
                        className={`nav-link ${tab === 'discussion' ? 'active' : ''}`}
                        onClick={() => setTab('discussion')}
                    >
                        Discussion
                    </button>
                </li>
            </ul>

            {tab === 'details' && (
                <div>
                    <div className="mb-3">
                        {position.company !== null && <span className="badge text-bg-secondary me-2">{position.company}</span>}
                        {position.level !== null && <span className="badge text-bg-info me-2">{position.level}</span>}
                        {position.isPublic ? (
                            <span className="badge text-bg-success">public</span>
                        ) : (
                            <span className="badge text-bg-warning">restricted</span>
                        )}
                    </div>

                    <p>
                        Max projects in CV: <strong>{position.maxProjects}</strong>
                    </p>

                    {position.projectTagFilter.length > 0 && (
                        <div className="mb-3">
                            Project tags:{' '}
                            {position.projectTagFilter.map((tag) => (
                                <span key={tag} className="badge text-bg-primary me-1">
                                    {tag}
                                </span>
                            ))}
                        </div>
                    )}

                    <h3>Attributes</h3>
                    {position.attributes.length === 0 ? (
                        <div className="text-muted">No attributes selected.</div>
                    ) : (
                        <ul className="list-group mb-4">
                            {position.attributes.map((a) => (
                                <li key={a.attributeDefinitionId} className="list-group-item d-flex justify-content-between">
                                    <span>{a.name}</span>
                                    <small className="text-muted">{a.dataType}</small>
                                </li>
                            ))}
                        </ul>
                    )}

                    {position.accessRules !== undefined && position.accessRules.length > 0 && (
                        <>
                            <h3>Access rules</h3>
                            <ul className="list-group">
                                {position.accessRules.map((r, idx) => (
                                    <li key={idx} className="list-group-item">
                                        <strong>{r.attributeName}</strong> {r.operator}{' '}
                                        <code>{JSON.stringify(r.value)}</code>
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}

                    {position.accessible === false && (
                        <Alert variant="warning" className="mt-4">
                            You don't currently satisfy this position's access rules.
                        </Alert>
                    )}
                </div>
            )}

            {tab === 'discussion' && <Discussion positionId={position.id} />}
        </div>
    )
}