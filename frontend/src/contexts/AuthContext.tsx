import {
    createContext,
    useContext,
    useEffect,
    useState,
    type ReactNode,
} from 'react'
import { authApi } from '../api/auth'
import type { CurrentUser } from '../types'

interface AuthState {
    user: CurrentUser | null
    loading: boolean
    refresh: () => Promise<void>
    logout: () => Promise<void>
    hasRole: (...roles: string[]) => boolean
}

const AuthContext = createContext<AuthState | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<CurrentUser | null>(null)
    const [loading, setLoading] = useState(true)

    const refresh = async () => {
        const u = await authApi.me()
        setUser(u)
    }

    useEffect(() => {
        refresh().finally(() => setLoading(false))
    }, [])

    const logout = async () => {
        await authApi.logout()
        setUser(null)
        window.location.href = '/login'
    }

    const hasRole = (...roles: string[]) => {
        if (!user) return false
        const userRoles = user.roles ?? []
        return roles.some((r) => userRoles.includes(r))
    }

    return (
        <AuthContext.Provider value={{ user, loading, refresh, logout, hasRole }}>
            {children}
        </AuthContext.Provider>
    )
}

export function useAuth(): AuthState {
    const ctx = useContext(AuthContext)
    if (ctx === null) throw new Error('useAuth must be inside AuthProvider')
    return ctx
}