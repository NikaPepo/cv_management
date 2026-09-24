import { useEffect, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { Alert, Button, Card, Form, ProgressBar } from 'react-bootstrap'
import { authApi } from '../api/auth'

/**
 * Page reached from the link in the password-reset email.
 * Token comes via ?token=... and is sent to the backend on submit.
 */
export default function ResetPasswordPage() {
    const navigate = useNavigate()
    const [searchParams] = useSearchParams()
    const token = searchParams.get('token') ?? ''

    const [password, setPassword] = useState('')
    const [confirm, setConfirm] = useState('')
    const [loading, setLoading] = useState(false)
    const [done, setDone] = useState(false)
    const [error, setError] = useState('')

    // Simple strength heuristic for the bar — purely UX feedback.
    const strength = Math.min(
        100,
        (password.length >= 8 ? 25 : 0) +
            (password.length >= 12 ? 25 : 0) +
            (/[A-Z]/.test(password) ? 15 : 0) +
            (/[a-z]/.test(password) ? 15 : 0) +
            (/[0-9]/.test(password) ? 10 : 0) +
            (/[^A-Za-z0-9]/.test(password) ? 10 : 0)
    )
    const strengthVariant =
        strength < 40 ? 'danger' : strength < 70 ? 'warning' : 'success'

    useEffect(() => {
        if (token === '') {
            setError('Missing or invalid reset link.')
        }
    }, [token])

    const onSubmit = async (e: React.FormEvent) => {
        e.preventDefault()
        setError('')
        if (token === '') return
        if (password.length < 8) {
            setError('Password must be at least 8 characters.')
            return
        }
        if (password !== confirm) {
            setError('Passwords do not match.')
            return
        }
        setLoading(true)
        try {
            await authApi.resetPassword(token, password)
            setDone(true)
            window.setTimeout(() => navigate('/login'), 2000)
        } catch (e: unknown) {
            const msg = (e as { response?: { data?: { error?: string } } })?.response?.data?.error
            setError(msg ?? 'Could not reset password.')
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
                            <h1 className="text-center mb-4">Set a new password</h1>

                            {done ? (
                                <Alert variant="success">
                                    Password reset. Redirecting to sign in…
                                </Alert>
                            ) : (
                                <Form onSubmit={onSubmit}>
                                    <Form.Group className="mb-3">
                                        <Form.Label>New password</Form.Label>
                                        <Form.Control
                                            type="password"
                                            value={password}
                                            onChange={(e) => setPassword(e.target.value)}
                                            autoComplete="new-password"
                                            required
                                            minLength={8}
                                        />
                                        {password !== '' && (
                                            <ProgressBar
                                                variant={strengthVariant}
                                                now={strength}
                                                label={`${strength}%`}
                                                className="mt-2"
                                                style={{ height: 6 }}
                                            />
                                        )}
                                    </Form.Group>
                                    <Form.Group className="mb-3">
                                        <Form.Label>Repeat password</Form.Label>
                                        <Form.Control
                                            type="password"
                                            value={confirm}
                                            onChange={(e) => setConfirm(e.target.value)}
                                            autoComplete="new-password"
                                            required
                                            minLength={8}
                                        />
                                    </Form.Group>
                                    {error !== '' && (
                                        <Alert variant="danger" className="py-2 small">
                                            {error}
                                        </Alert>
                                    )}
                                    <Button
                                        type="submit"
                                        variant="primary"
                                        className="w-100"
                                        disabled={loading || token === ''}
                                    >
                                        {loading ? '…' : 'Reset password'}
                                    </Button>
                                </Form>
                            )}

                            <hr />
                            <Link to="/login" className="btn btn-link w-100">
                                Back to sign in
                            </Link>
                        </Card.Body>
                    </Card>
                </div>
            </div>
        </div>
    )
}