import {
    createContext,
    useContext,
    useEffect,
    useState,
    type ReactNode,
} from 'react'
import { authApi } from '../api/auth'
import type { CurrentUser } from '../types'

/**
 * Auth state machine (do not collapse these three terminal states):
 *
 *   loading=true   user=null   error=null  -> initial /api/me in flight
 *   loading=false  user=User   error=null  -> authenticated
 *   loading=false  user=null   error=null  -> unauthenticated (expected,
 *                                              /api/me returned 401)
 *   loading=false  user=null   error=set   -> auth status unknown
 *                                              (/api/me returned 5xx or
 *                                              the request never reached
 *                                              the server)
 *
 * The fourth state is intentionally DISTINCT from the third so the UI can
 * distinguish "logged out" from "the server didn't answer" — collapsing
 * them would let a backend outage silently show the public UI as if
 * everything were fine, which is what triggered the original bug report.
 */
export interface AuthState {
    user: CurrentUser | null
    loading: boolean
    error: string | null
    refresh: () => Promise<void>
    logout: () => Promise<void>
    hasRole: (...roles: string[]) => boolean
}

const AuthContext = createContext<AuthState | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<CurrentUser | null>(null)
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState<string | null>(null)

    const refresh = async () => {
        try {
            const u = await authApi.me()
            // 200 OR 401 — both are valid, expected outcomes.
            setUser(u)
            setError(null)
        } catch {
            // Anything other than 401 (5xx, network, CORS, abort).
            // We deliberately do NOT set user to anything other than
            // null here — we genuinely don't know whether a session
            // exists, so the safest local state is "unknown, no user".
            setUser(null)
            setError('auth.error.unable_to_verify')
        }
    }

    useEffect(() => {
        // React 19 StrictMode in development runs this effect twice,
        // producing two GET /api/me on a fresh page load. Both are
        // independent requests; both 401s are the expected response
        // for an unauthenticated browser, and the duplicate is harmless
        // because refresh() is idempotent and finally() unconditionally
        // sets loading=false. No global flag, no AbortController —
        // the StrictMode behaviour is left intact.
        refresh().finally(() => setLoading(false))
    }, [])

    const logout = async () => {
        await authApi.logout()
        setUser(null)
        setError(null)
        window.location.href = '/login'
    }

    const hasRole = (...roles: string[]) => {
        if (!user) return false
        const userRoles = user.roles ?? []
        return roles.some((r) => userRoles.includes(r))
    }

    return (
        <AuthContext.Provider value={{ user, loading, error, refresh, logout, hasRole }}>
            {children}
        </AuthContext.Provider>
    )
}

export function useAuth(): AuthState {
    const ctx = useContext(AuthContext)
    if (ctx === null) throw new Error('useAuth must be inside AuthProvider')
    return ctx
}