import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Button, Card, Form } from 'react-bootstrap'
import api from '../api/axios'
import { useAuth } from '../contexts/AuthContext'
import { useTranslation } from '../contexts/AppPreferencesContext'

type AccountType = 'candidate' | 'recruiter'

export default function RegisterPage() {
    const t = useTranslation()
    const navigate = useNavigate()
    const { refresh } = useAuth()
    const [email, setEmail] = useState('')
    const [password, setPassword] = useState('')
    const [accountType, setAccountType] = useState<AccountType>('candidate')
    const [error, setError] = useState('')
    const [loading, setLoading] = useState(false)

    const submit = async (e: React.FormEvent) => {
        e.preventDefault()
        setError('')
        setLoading(true)
        try {
            await api.post('/api/registration', { email, password, accountType })
            await refresh()
            navigate('/', { replace: true })
        } catch (e: unknown) {
            const data = (e as { response?: { data?: { error?: string } } })?.response?.data
            setError(data?.error ?? t('auth.register.failed'))
        } finally {
            setLoading(false)
        }
    }

    return (
        <div className="container py-5">
            <div className="row justify-content-center">
                <div className="col-12 col-md-6 col-lg-5">
                    <Card className="shadow-sm">
                        <Card.Body className="p-4">
                            <h1 className="text-center mb-4">{t('nav.register')}</h1>
                            <Form onSubmit={submit}>
                                <Form.Group className="mb-3">
                                    <Form.Label>{t('auth.register.email_label')}</Form.Label>
                                    <Form.Control
                                        type="email"
                                        value={email}
                                        onChange={(e) => setEmail(e.target.value)}
                                        required
                                    />
                                </Form.Group>

                                <Form.Group className="mb-3">
                                    <Form.Label>{t('auth.register.password_label')}</Form.Label>
                                    <Form.Control
                                        type="password"
                                        minLength={8}
                                        value={password}
                                        onChange={(e) => setPassword(e.target.value)}
                                        required
                                    />
                                </Form.Group>

                                <Form.Group className="mb-3">
                                    <Form.Label>{t('auth.register.account_type_label')}</Form.Label>
                                    <Form.Select
                                        value={accountType}
                                        onChange={(e) => setAccountType(e.target.value as AccountType)}
                                    >
                                        <option value="candidate">{t('role.candidate')}</option>
                                        <option value="recruiter">{t('role.recruiter')}</option>
                                    </Form.Select>
                                </Form.Group>

                                {error && <div className="alert alert-danger py-2 small">{error}</div>}

                                <Button
                                    type="submit"
                                    variant="primary"
                                    className="w-100"
                                    disabled={loading}
                                >
                                    {loading ? '…' : t('nav.register')}
                                </Button>
                            </Form>

                            <hr />
                            <Link to="/login" className="btn btn-link w-100">
                                {t('nav.login')}
                            </Link>
                        </Card.Body>
                    </Card>
                </div>
            </div>
        </div>
    )
}