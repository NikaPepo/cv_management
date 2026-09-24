import { useEffect, useState } from 'react'
import { Button, Form, Table } from 'react-bootstrap'
import api from '../api/axios'
import { useAuth } from '../contexts/AuthContext'
import { useTranslation } from '../contexts/AppPreferencesContext'

interface AdminUser {
    id: number
    email: string
    roles: string[]
    isVerified: boolean
    isBlocked: boolean
}

export default function AdminPage() {
    const t = useTranslation()
    const { hasRole } = useAuth()
    const [items, setItems] = useState<AdminUser[]>([])
    const [page, setPage] = useState(1)
    const [total, setTotal] = useState(0)
    const [selected, setSelected] = useState<number[]>([])
    const [error] = useState('')

    if (!hasRole('ROLE_ADMIN')) {
        return <div className="alert alert-danger">Forbidden.</div>
    }

    const refresh = async (p: number) => {
        const { data } = await api.get('/api/admin/users', {
            params: { page: p, perPage: 20 },
        })
        setItems(data.items)
        setTotal(data.total)
        setPage(data.page)
    }

    useEffect(() => {
        void refresh(1)
    }, [])

    const toggleSelect = (id: number) => {
        setSelected((cur) => (cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]))
    }

    const bulkBlock = async (blocked: boolean) => {
        await Promise.all(
            selected.map((id) => api.patch(`/api/admin/users/${id}`, { blocked }))
        )
        setSelected([])
        await refresh(page)
    }

    const bulkRole = async (role: 'candidate' | 'recruiter' | 'admin') => {
        await Promise.all(
            selected.map((id) => api.patch(`/api/admin/users/${id}`, { role }))
        )
        setSelected([])
        await refresh(page)
    }

    const bulkDelete = async () => {
        if (!window.confirm(`Delete ${selected.length} user(s)?`)) return
        await Promise.all(
            selected.map((id) => api.delete(`/api/admin/users/${id}`).catch(() => {}))
        )
        setSelected([])
        await refresh(page)
    }

    return (
        <div>
            <h1 className="mb-3">{t('nav.admin')}</h1>

            {selected.length > 0 && (
                <div className="alert alert-secondary d-flex justify-content-between align-items-center">
                    <span>{selected.length} selected</span>
                    <div className="d-flex gap-2 flex-wrap">
                        <Button size="sm" variant="outline-secondary" onClick={() => void bulkBlock(true)}>
                            Block selected
                        </Button>
                        <Button size="sm" variant="outline-success" onClick={() => void bulkBlock(false)}>
                            Unblock selected
                        </Button>
                        <Button size="sm" variant="outline-primary" onClick={() => void bulkRole('admin')}>
                            Promote to admin
                        </Button>
                        <Button size="sm" variant="outline-primary" onClick={() => void bulkRole('recruiter')}>
                            Set as recruiter
                        </Button>
                        <Button size="sm" variant="outline-primary" onClick={() => void bulkRole('candidate')}>
                            Set as candidate
                        </Button>
                        <Button size="sm" variant="danger" onClick={() => void bulkDelete()}>
                            Delete selected
                        </Button>
                    </div>
                </div>
            )}

            {error && <div className="alert alert-danger">{error}</div>}

            <Table hover responsive className="align-middle">
                <thead>
                    <tr>
                        <th style={{ width: 40 }}>☑</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Verified</th>
                        <th>Blocked</th>
                    </tr>
                </thead>
                <tbody>
                    {items.map((u) => (
                        <tr
                            key={u.id}
                            className={selected.includes(u.id) ? 'table-active' : ''}
                            onClick={() => toggleSelect(u.id)}
                            role="button"
                        >
                            <td onClick={(e) => e.stopPropagation()}>
                                <Form.Check
                                    type="checkbox"
                                    checked={selected.includes(u.id)}
                                    onChange={() => toggleSelect(u.id)}
                                />
                            </td>
                            <td>{u.email}</td>
                            <td onClick={(e) => e.stopPropagation()}>
                                <Form.Select
                                    size="sm"
                                    value={roleValue(u.roles)}
                                    onChange={(e) =>
                                        void bulkRole(e.target.value as 'candidate' | 'recruiter' | 'admin')
                                    }
                                >
                                    <option value="candidate">Candidate</option>
                                    <option value="recruiter">Recruiter</option>
                                    <option value="admin">Admin</option>
                                </Form.Select>
                            </td>
                            <td>{u.isVerified ? '✓' : '✗'}</td>
                            <td>{u.isBlocked ? '🚫' : ''}</td>
                        </tr>
                    ))}
                </tbody>
            </Table>

            <div className="d-flex justify-content-between">
                <span className="text-muted">Total: {total}</span>
                <div className="d-flex gap-2">
                    <Button
                        variant="secondary"
                        disabled={page <= 1}
                        onClick={() => void refresh(page - 1)}
                    >
                        ←
                    </Button>
                    <span className="align-self-center">{page}</span>
                    <Button
                        variant="secondary"
                        disabled={page * 20 >= total}
                        onClick={() => void refresh(page + 1)}
                    >
                        →
                    </Button>
                </div>
            </div>
        </div>
    )
}

function roleValue(roles: string[]): 'candidate' | 'recruiter' | 'admin' {
    if (roles.includes('ROLE_ADMIN')) return 'admin'
    if (roles.includes('ROLE_RECRUITER')) return 'recruiter'
    return 'candidate'
}