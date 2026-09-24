import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Form } from 'react-bootstrap'
import api from '../api/axios'
import { useTranslation } from '../contexts/AppPreferencesContext'

interface SearchHit {
    kind: 'positions' | 'attributes' | 'profile' | string
    id: number
    title: string
    subtitle: string
}

export default function GlobalSearch() {
    const t = useTranslation()
    const navigate = useNavigate()
    const [q, setQ] = useState('')
    const [hits, setHits] = useState<SearchHit[]>([])

    useEffect(() => {
        if (q.trim() === '') {
            setHits([])
            return
        }
        const controller = new AbortController()
        const t = setTimeout(async () => {
            try {
                const { data } = await api.get<SearchHit[]>('/api/search', {
                    params: { q },
                    signal: controller.signal,
                })
                setHits(data)
            } catch {
                /* aborted or 401 */
            }
        }, 200)
        return () => {
            controller.abort()
            clearTimeout(t)
        }
    }, [q])

    return (
        <div className="position-relative">
            <Form.Control
                type="search"
                size="sm"
                placeholder={t('search.placeholder')}
                value={q}
                onChange={(e) => setQ(e.target.value)}
            />
            {hits.length > 0 && (
                <div
                    className="position-absolute top-100 start-0 end-0 mt-1 shadow-sm border bg-body rounded"
                    style={{ zIndex: 1080 }}
                >
                    {hits.map((h) => (
                        <button
                            key={`${h.kind}-${h.id}`}
                            className="d-block w-100 text-start px-3 py-2 border-bottom btn btn-link text-decoration-none"
                            onClick={() => {
                                setQ('')
                                setHits([])
                                navigate(`/${h.kind}/${h.id}`)
                            }}
                        >
                            <strong>{h.title}</strong>
                            <br />
                            <small className="text-muted">{h.subtitle}</small>
                        </button>
                    ))}
                </div>
            )}
        </div>
    )
}