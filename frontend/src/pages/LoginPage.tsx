import { useState } from 'react'
import { useNavigate, useSearchParams, Link } from 'react-router-dom'
import { Button, Card, Form } from 'react-bootstrap'
import api from '../api/axios'
import { useAuth } from '../contexts/AuthContext'
import { useTranslation } from '../contexts/AppPreferencesContext'

export default function LoginPage() {
    const t = useTranslation()
    const navigate = useNavigate()
    const { refresh } = useAuth()
    const [searchParams, setSearchParams] = useSearchParams()
    const [email, setEmail] = useState('')
    const [password, setPassword] = useState('')
    const [error, setError] = useState('')
    const [loading, setLoading] = useState(false)

    // Read the params Symfony stamps onto the URL after email verification.
    // We clear them once shown so the banner does not reappear on refresh.
    const justVerified = searchParams.get('verified') === '1'
    const verifyErrorReason = searchParams.get('error') === 'verify_email'
        ? (searchParams.get('reason') ?? 'invalid_link')
        : null

    const dismissUrlFlags = () => {
        if (!searchParams.has('verified') && !searchParams.has('error')) return
        const next = new URLSearchParams(searchParams)
        next.delete('verified')
        next.delete('error')
        next.delete('reason')
        setSearchParams(next, { replace: true })
    }

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault()
        setError('')
        setLoading(true)
        try {
            await api.post('/api/login', { email, password })
            await refresh()
            navigate('/', { replace: true })
        } catch {
            setError('Invalid credentials.')
        } finally {
            setLoading(false)
        }
    }

    return (
        <div className="container py-5">
            <div className="row justify-content-center">
                <div className="col-12 col-md-6 col-lg-4">
                    <Card className="shadow-sm">
                        <Card.Body className="p-4">
                            <h1 className="text-center mb-4">{t('nav.login')}</h1>

                            {justVerified && (
                                <div
                                    className="alert alert-success py-2 small"
                                    onClick={dismissUrlFlags}
                                    role="status"
                                >
                                    Email verified. You can sign in now.
                                </div>
                            )}

                            {verifyErrorReason !== null && (
                                <div
                                    className="alert alert-danger py-2 small"
                                    onClick={dismissUrlFlags}
                                    role="alert"
                                >
                                    Verification link is invalid or expired: {verifyErrorReason}
                                </div>
                            )}

                            <Form onSubmit={handleSubmit}>
                                <Form.Group className="mb-3" controlId="loginEmail">
                                    <Form.Label>Email</Form.Label>
                                    <Form.Control
                                        type="email"
                                        value={email}
                                        onChange={(e) => setEmail(e.target.value)}
                                        required
                                    />
                                </Form.Group>
                                <Form.Group className="mb-3" controlId="loginPassword">
                                    <Form.Label>Password</Form.Label>
                                    <Form.Control
                                        type="password"
                                        value={password}
                                        onChange={(e) => setPassword(e.target.value)}
                                        required
                                    />
                                </Form.Group>
                                {error && (
                                    <div className="alert alert-danger py-2 small">{error}</div>
                                )}
                                <Button
                                    variant="primary"
                                    type="submit"
                                    className="w-100"
                                    disabled={loading}
                                >
                                    {loading ? '…' : t('nav.login')}
                                </Button>
                                <div className="text-center mt-2">
                                    <Link to="/forgot-password" className="small">
                                        Forgot password?
                                    </Link>
                                </div>
                            </Form>
                            <hr />
                            <div className="d-flex gap-2">
                                <a
                                    href="/connect/google"
                                    className="btn btn-outline-secondary flex-grow-1"
                                >
                                    Google
                                </a>
                                <a
                                    href="/connect/facebook"
                                    className="btn btn-outline-primary flex-grow-1"
                                >
                                    Facebook
                                </a>
                            </div>
                            <div className="mt-2">
                                <Link to="/register" className="btn btn-link w-100">
                                    {t('nav.register')}
                                </Link>
                            </div>
                        </Card.Body>
                    </Card>
                </div>
            </div>
        </div>
    )
}