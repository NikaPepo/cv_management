import { type ReactNode } from 'react'
import { Link, NavLink } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import {
    usePreferences,
    useTranslation,
    type Locale,
    type Theme,
} from '../contexts/AppPreferencesContext'

interface Props {
    children: ReactNode
    searchSlot?: ReactNode
}

export default function Layout({ children, searchSlot }: Props) {
    const { user, logout, hasRole } = useAuth()
    const t = useTranslation()
    const { locale, setLocale, theme, setTheme } = usePreferences()

    return (
        <div className="min-vh-100 d-flex flex-column" data-bs-theme={theme}>
            <header className="border-bottom bg-body-tertiary">
                <div className="container-fluid d-flex gap-3 align-items-center py-2">
                    <Link to="/" className="navbar-brand fw-semibold mb-0">
                        {t('app.title')}
                    </Link>

                    <nav className="d-flex gap-2 flex-grow-1">
                        <NavLink to="/" end className="nav-link">
                            {t('nav.home')}
                        </NavLink>
                        <NavLink to="/positions" className="nav-link">
                            {t('nav.positions')}
                        </NavLink>
                        {hasRole('ROLE_RECRUITER', 'ROLE_ADMIN') && (
                            <NavLink to="/attributes" className="nav-link">
                                {t('nav.attributes')}
                            </NavLink>
                        )}
                        {user && (
                            <NavLink to="/profile" className="nav-link">
                                {t('nav.profile')}
                            </NavLink>
                        )}
                        {hasRole('ROLE_ADMIN') && (
                            <NavLink to="/admin" className="nav-link">
                                {t('nav.admin')}
                            </NavLink>
                        )}
                    </nav>

                    <div className="flex-grow-1" style={{ maxWidth: 360 }}>
                        {searchSlot}
                    </div>

                    <select
                        className="form-select form-select-sm"
                        style={{ width: 120 }}
                        value={locale}
                        onChange={(e) => setLocale(e.target.value as Locale)}
                        aria-label="Language"
                    >
                        <option value="en">English</option>
                        <option value="ka">ქართული</option>
                    </select>

                    <select
                        className="form-select form-select-sm"
                        style={{ width: 120 }}
                        value={theme}
                        onChange={(e) => setTheme(e.target.value as Theme)}
                        aria-label="Theme"
                    >
                        <option value="light">{t('theme.light')}</option>
                        <option value="dark">{t('theme.dark')}</option>
                    </select>

                    {user ? (
                        <>
                            <span
                                className="small text-muted text-truncate"
                                style={{ maxWidth: 180 }}
                                title={user.email}
                            >
                                {user.email}
                            </span>
                            <button
                                className="btn btn-sm btn-outline-secondary"
                                onClick={() => void logout()}
                            >
                                {t('nav.logout')}
                            </button>
                        </>
                    ) : (
                        <Link to="/login" className="btn btn-sm btn-outline-primary">
                            {t('nav.login')}
                        </Link>
                    )}
                </div>
            </header>

            <main className="container-fluid py-4 flex-grow-1">{children}</main>

            <footer className="border-top bg-body-tertiary text-center py-3 small text-muted">
                {t('app.title')}
            </footer>
        </div>
    )
}