import { Navigate } from 'react-router-dom'
import { type ReactNode } from 'react'
import { useAuth } from '../contexts/AuthContext'

interface Props {
    children: ReactNode
    roles?: string[]
}

export default function ProtectedRoute({ children, roles }: Props) {
    const { user, loading, hasRole } = useAuth()

    if (loading) return <div className="container py-5">…</div>

    if (!user) return <Navigate to="/login" replace />

    if (roles && !hasRole(...roles)) {
        return (
            <div className="container py-5">
                <div className="alert alert-danger">Forbidden.</div>
            </div>
        )
    }

    return <>{children}</>
}