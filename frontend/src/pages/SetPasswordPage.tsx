import { useState } from 'react'
import { Alert, Button, Card, Form, ProgressBar } from 'react-bootstrap'
import { Link, useNavigate } from 'react-router-dom'
import { authApi } from '../api/auth'
import { useAuth } from '../contexts/AuthContext'
import { useTranslation } from '../contexts/AppPreferencesContext'

/**
 * Authenticated set-password page.
 *
 * Reachable via Profile → "Set password" when the SPA knows (from
 * /api/me's hasPassword flag) that the current user is OAuth-only.
 * The backend endpoint requires an active session and identifies the
 * target user via Symfony Security — never from the request body —
 * so this UI cannot be used to set anyone else's password even if
 * the URL is shared.
 *
 * UX mirrors ResetPasswordPage (strength bar, confirmation field,
 * minLength=8) so the password policy feels consistent across the
 * two flows. Translation keys share the `auth.reset.*` namespace
 * where they overlap; only the page-specific copy is under
 * `auth.set_password.*`.
 */
export default function SetPasswordPage() {
    const t = useTranslation()
    const navigate = useNavigate()
    const { user, refresh } = useAuth()
    const [password, setPassword] = useState('')
    const [confirm, setConfirm] = useState('')
    const [loading, setLoading] = useState(false)
    const [done, setDone] = useState(false)
    const [error, setError] = useState('')

    // Same heuristic as ResetPasswordPage so the strength bar reads
    // identically regardless of which flow the user landed on.
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

    const onSubmit = async (e: React.FormEvent) => {
        e.preventDefault()
        setError('')
        if (user === null) {
            // Defence in depth: ProtectedRoute should already have
            // redirected, but the typed-null guard keeps the rest of
            // this function narrow.
            setError(t('auth.set_password.not_authenticated'))
            return
        }
        if (password.length < 8) {
            setError(t('auth.reset.password_too_short'))
            return
        }
        if (password !== confirm) {
            setError(t('auth.reset.passwords_mismatch'))
            return
        }
        setLoading(true)
        try {
            await authApi.setPassword(password)
            // Pull /api/me again so AuthContext.user.hasPassword flips
            // to true and the Profile UI stops prompting for a password.
            await refresh()
            setDone(true)
            window.setTimeout(() => navigate('/profile', { replace: true }), 1500)
        } catch (e: unknown) {
            const msg = (e as { response?: { data?: { error?: string } } })?.response?.data?.error
            setError(msg ?? t('auth.set_password.failed'))
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
                            <h1 className="text-center mb-4">{t('auth.set_password.title')}</h1>
                            <p className="text-muted small">
                                {t('auth.set_password.intro')}
                            </p>

                            {done ? (
                                <Alert variant="success">
                                    {t('auth.set_password.success')}
                                </Alert>
                            ) : (
                                <Form onSubmit={onSubmit}>
                                    <Form.Group className="mb-3">
                                        <Form.Label>{t('auth.reset.new_password_label')}</Form.Label>
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
                                        <Form.Label>{t('auth.reset.repeat_password_label')}</Form.Label>
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
                                        disabled={loading}
                                    >
                                        {loading ? '…' : t('auth.set_password.submit')}
                                    </Button>
                                </Form>
                            )}

                            <hr />
                            <Link to="/profile" className="btn btn-link w-100">
                                {t('common.back')}
                            </Link>
                        </Card.Body>
                    </Card>
                </div>
            </div>
        </div>
    )
}