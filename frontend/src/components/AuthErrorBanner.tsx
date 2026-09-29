import { useAuth } from '../contexts/AuthContext'
import { useTranslation } from '../contexts/AppPreferencesContext'

/**
 * Surfaces the "auth status unknown" terminal state of AuthProvider.
 *
 * This banner renders ONLY when:
 *   - loading has finished (the initial /api/me check completed), AND
 *   - error is non-null (authApi.me() threw a non-401 error).
 *
 * It deliberately does NOT render for the expected 401 path — that path
 * keeps `error === null` and lets the public UI show normally. The whole
 * point of separating the 401 and 5xx branches in AuthContext is that
 * the user sees nothing alarming when simply not logged in, and sees
 * this banner when the backend genuinely failed.
 */
export default function AuthErrorBanner() {
    const { error, loading } = useAuth()
    const t = useTranslation()

    if (loading) return null
    if (error === null) return null

    return (
        <div
            role="alert"
            className="alert alert-warning rounded-0 mb-0 py-2 small text-center"
        >
            {t('auth.error.unable_to_verify')}
        </div>
    )
}