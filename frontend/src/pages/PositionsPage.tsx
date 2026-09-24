import { useEffect, useState } from 'react'
import { Button, Form, Table } from 'react-bootstrap'
import { Link } from 'react-router-dom'
import { positionApi, type Position, type PositionLevel } from '../api/positions'
import { useAuth } from '../contexts/AuthContext'

const LEVELS: { value: PositionLevel | ''; label: string }[] = [
    { value: '', label: 'All levels' },
    { value: 'junior', label: 'Junior' },
    { value: 'middle', label: 'Middle' },
    { value: 'senior', label: 'Senior' },
    { value: 'c_level', label: 'C-level' },
]

export default function PositionsPage() {
    const { hasRole } = useAuth()
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
        } catch (e: unknown) {
            setError((e as Error).message)
        }
    }

    useEffect(() => {
        void refresh()
    }, [company, level, isStaff])

    const bulkDelete = async () => {
        if (selected.length === 0) return
        if (!window.confirm(`Delete ${selected.length} position(s)?`)) return
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

    // Edit is a per-row action in spirit, but the assignment forbids a
    // per-row "Edit" button. The accepted pattern is: edit appears in the
    // toolbar when exactly one row is selected. The user double-clicks or
    // selects one row and clicks "Edit selected".
    const selectedSingle = selected.length === 1 ? selected[0] : null

    const toggleSelect = (id: number) =>
        setSelected((cur) => (cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]))
    const toggleAll = () =>
        setSelected((cur) => (cur.length === items.length ? [] : items.map((p) => p.id)))

    return (
        <div>
            <div className="d-flex justify-content-between align-items-center mb-3">
                <h1 className="mb-0">Positions</h1>
                {isStaff && (
                    <Link to="/positions/new" className="btn btn-primary">
                        New position
                    </Link>
                )}
            </div>

            <div className="row mb-3">
                <Form.Group className="col-md-4">
                    <Form.Label>Company</Form.Label>
                    <Form.Control
                        value={company}
                        onChange={(e) => setCompany(e.target.value)}
                        placeholder="Filter by company…"
                    />
                </Form.Group>
                <Form.Group className="col-md-3">
                    <Form.Label>Level</Form.Label>
                    <Form.Select
                        value={level}
                        onChange={(e) => setLevel(e.target.value as PositionLevel | '')}
                    >
                        {LEVELS.map((l) => (
                            <option key={l.value} value={l.value}>
                                {l.label}
                            </option>
                        ))}
                    </Form.Select>
                </Form.Group>
            </div>

            {selected.length > 0 && isStaff && (
                <div className="alert alert-secondary d-flex justify-content-between align-items-center">
                    <span>{selected.length} selected</span>
                    <div className="d-flex gap-2">
                        {selectedSingle !== null && (
                            <Link
                                to={`/positions/${selectedSingle}/edit`}
                                className="btn btn-sm btn-outline-secondary"
                            >
                                Edit selected
                            </Link>
                        )}
                        <Button
                            size="sm"
                            variant="outline-info"
                            onClick={() => void bulkDuplicate()}
                        >
                            Duplicate selected
                        </Button>
                        <Button
                            size="sm"
                            variant="danger"
                            onClick={() => void bulkDelete()}
                        >
                            Delete selected
                        </Button>
                    </div>
                </div>
            )}

            {error !== '' && <div className="alert alert-danger">{error}</div>}

            {items.length === 0 ? (
                <div className="text-muted">No positions yet.</div>
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
                            <th>Title</th>
                            <th>Company</th>
                            <th>Level</th>
                            <th>Access</th>
                            <th>Submitted CVs</th>
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
                                <td>{p.level ?? '—'}</td>
                                <td>
                                    {p.isPublic ? (
                                        <span className="badge text-bg-success">public</span>
                                    ) : (
                                        <span className="badge text-bg-warning">restricted</span>
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