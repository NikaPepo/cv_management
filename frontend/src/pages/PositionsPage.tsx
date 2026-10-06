import { useEffect, useState } from 'react'
import { Button, Form, Table } from 'react-bootstrap'
import { Link } from 'react-router-dom'
import { positionApi, type Position, type PositionLevel } from '../api/positions'
import { useAuth } from '../contexts/AuthContext'
import { useT } from '../contexts/AppPreferencesContext'

const LEVEL_VALUES: (PositionLevel | '')[] = ['', 'junior', 'middle', 'senior', 'c_level']

export default function PositionsPage() {
    const { t, tPlural } = useT()
    const { hasRole, loading } = useAuth()
    const [items, setItems] = useState<Position[]>([])
    const [company, setCompany] = useState('')
    const [level, setLevel] = useState<PositionLevel | ''>('')
    const [selected, setSelected] = useState<number[]>([])
    const [error, setError] = useState('')

    const isStaff = hasRole('ROLE_RECRUITER', 'ROLE_ADMIN')

    const refresh = async () => {
        try {
            const data = await positionApi.list({
                all: isStaff,
                company: company === '' ? undefined : company,
                level: level === '' ? undefined : level,
            })
            setItems(data)
        } catch {
            setError(t('error.generic'))
        }
    }

    useEffect(() => {
        // AuthContext is still resolving the initial /api/me: user is
        // null, so isStaff is false and a fetch here would go out
        // without ?all=1. When /api/me resolves the effect re-runs with
        // the real role, racing the in-flight no-all request — the
        // public-only response sometimes wins and clobbers the correct
        // all-positions response. Skip the fetch until the auth state is
        // known; the effect re-fires when authLoading flips to false.
        if (loading) return
        void refresh()
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [company, level, isStaff, loading])

    const bulkDelete = async () => {
        if (selected.length === 0) return
        if (!window.confirm(tPlural('positions.delete.confirm', selected.length, { count: selected.length }))) return
        await Promise.all(selected.map((id) => positionApi.remove(id).catch(() => {})))
        setSelected([])
        await refresh()
    }

    const bulkDuplicate = async () => {
        if (selected.length === 0) return
        await Promise.all(selected.map((id) => positionApi.duplicate(id).catch(() => {})))
        setSelected([])
        await refresh()
    }

    const selectedSingle = selected.length === 1 ? selected[0] : null

    const toggleSelect = (id: number) =>
        setSelected((cur) => (cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]))
    const toggleAll = () =>
        setSelected((cur) => (cur.length === items.length ? [] : items.map((p) => p.id)))

    return (
        <div>
            <div className="d-flex justify-content-between align-items-center mb-3">
                <h1 className="mb-0">{t('positions.title')}</h1>
                {isStaff && (
                    <Link to="/positions/new" className="btn btn-primary">
                        {t('positions.new_link')}
                    </Link>
                )}
            </div>

            <div className="row mb-3">
                <Form.Group className="col-md-4">
                    <Form.Label>{t('positions.filter.company_label')}</Form.Label>
                    <Form.Control
                        value={company}
                        onChange={(e) => setCompany(e.target.value)}
                        placeholder={t('positions.filter.company_placeholder')}
                    />
                </Form.Group>
                <Form.Group className="col-md-3">
                    <Form.Label>{t('positions.filter.level_label')}</Form.Label>
                    <Form.Select
                        value={level}
                        onChange={(e) => setLevel(e.target.value as PositionLevel | '')}
                    >
                        {LEVEL_VALUES.map((l) => (
                            <option key={l} value={l}>
                                {t(`level.${l === '' ? 'all' : l}`)}
                            </option>
                        ))}
                    </Form.Select>
                </Form.Group>
            </div>

            {selected.length > 0 && isStaff && (
                <div className="alert alert-secondary d-flex justify-content-between align-items-center">
                    <span>{tPlural('positions.selected_count', selected.length, { count: selected.length })}</span>
                    <div className="d-flex gap-2">
                        {selectedSingle !== null && (
                            <Link
                                to={`/positions/${selectedSingle}/edit`}
                                className="btn btn-sm btn-outline-secondary"
                            >
                                {t('positions.edit_selected')}
                            </Link>
                        )}
                        <Button
                            size="sm"
                            variant="outline-info"
                            onClick={() => void bulkDuplicate()}
                        >
                            {t('positions.duplicate_selected')}
                        </Button>
                        <Button
                            size="sm"
                            variant="danger"
                            onClick={() => void bulkDelete()}
                        >
                            {t('positions.delete_selected')}
                        </Button>
                    </div>
                </div>
            )}

            {error !== '' && <div className="alert alert-danger">{error}</div>}

            {items.length === 0 ? (
                <div className="text-muted">{t('positions.empty')}</div>
            ) : (
                <Table hover responsive className="align-middle">
                    <thead>
                        <tr>
                            {isStaff && (
                                <th style={{ width: 40 }}>
                                    <Form.Check
                                        type="checkbox"
                                        checked={selected.length === items.length}
                                        onChange={toggleAll}
                                    />
                                </th>
                            )}
                            <th>{t('positions.col.title')}</th>
                            <th>{t('positions.col.company')}</th>
                            <th>{t('positions.col.level')}</th>
                            <th>{t('positions.col.access')}</th>
                            <th>{t('positions.col.submitted_cvs')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((p) => (
                            <tr
                                key={p.id}
                                onClick={() => isStaff && toggleSelect(p.id)}
                                className={selected.includes(p.id) ? 'table-active' : ''}
                                role={isStaff ? 'button' : undefined}
                            >
                                {isStaff && (
                                    <td onClick={(e) => e.stopPropagation()}>
                                        <Form.Check
                                            type="checkbox"
                                            checked={selected.includes(p.id)}
                                            onChange={() => toggleSelect(p.id)}
                                        />
                                    </td>
                                )}
                                <td>
                                    <Link
                                        to={`/positions/${p.id}`}
                                        className="fw-semibold text-decoration-none"
                                    >
                                        {p.title}
                                    </Link>
                                    <br />
                                    <small className="text-muted">{p.shortDescription}</small>
                                </td>
                                <td>{p.company ?? '—'}</td>
                                <td>{p.level ? t(`level.${p.level}`) : '—'}</td>
                                <td>
                                    {p.isPublic ? (
                                        <span className="badge text-bg-success">{t('access.public')}</span>
                                    ) : (
                                        <span className="badge text-bg-warning">{t('access.restricted')}</span>
                                    )}
                                </td>
                                <td>{p.submittedCvs ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </Table>
            )}
        </div>
    )
}