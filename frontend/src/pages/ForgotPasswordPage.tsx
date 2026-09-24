import { useState } from 'react'
import { Button, Card, Form } from 'react-bootstrap'
import { Link } from 'react-router-dom'
import { authApi } from '../api/auth'

export default function ForgotPasswordPage() {
    const [email, setEmail] = useState('')
    const [submitted, setSubmitted] = useState(false)
    const [loading, setLoading] = useState(false)
    const [error, setError] = useState('')

    const onSubmit = async (e: React.FormEvent) => {
        e.preventDefault()
        setLoading(true)
        setError('')
        try {
            await authApi.forgotPassword(email.trim())
            setSubmitted(true)
        } catch {
            // Even on failure we show the same neutral message to avoid leaking
            // which addresses exist; surface real transport errors only.
            setError('Could not send reset link. Try again.')
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
                            <h1 className="text-center mb-4">Forgot password</h1>

                            {submitted ? (
                                <div className="alert alert-success py-2 small">
                                    If an account exists for that email, a reset link has been sent.
                                </div>
                            ) : (
                                <Form onSubmit={onSubmit}>
                                    <Form.Group className="mb-3">
                                        <Form.Label>Email</Form.Label>
                                        <Form.Control
                                            type="email"
                                            value={email}
                                            onChange={(e) => setEmail(e.target.value)}
                                            required
                                        />
                                    </Form.Group>
                                    {error !== '' && (
                                        <div className="alert alert-danger py-2 small">{error}</div>
                                    )}
                                    <Button
                                        variant="primary"
                                        type="submit"
                                        className="w-100"
                                        disabled={loading}
                                    >
                                        {loading ? '…' : 'Send reset link'}
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