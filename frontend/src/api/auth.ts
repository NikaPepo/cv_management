import axios from 'axios'
import api from './axios'
import type { CurrentUser } from '../types'

export const authApi = {
    /**
     * Initial / refresh session check.
     *
     * Contract (intentionally narrow):
     *   - 200 OK                 -> returns the CurrentUser (authenticated).
     *   - 401 Unauthorized       -> returns null (unauthenticated, expected).
     *     This is the normal response for a fresh browser with no session
     *     cookie. Callers MUST treat `null` as a valid unauthenticated
     *     state, NOT as an error.
     *   - any other failure      -> rethrows. Callers MUST surface this
     *     as "auth status unknown" (5xx, network failure, CORS, etc.) and
     *     must NOT silently fall back to "unauthenticated" — a backend
     *     outage is not the same thing as a logged-out user.
     *
     * This explicit split is what lets the browser console show two
     * "Failed to load resource: 401" lines for the StrictMode double
     * mount of the AuthProvider without those lines being turned into
     * a global application error.
     */
    async me(): Promise<CurrentUser | null> {
        try {
            const { data } = await api.get<CurrentUser>('/api/me')
            return data
        } catch (err) {
            if (axios.isAxiosError(err) && err.response?.status === 401) {
                return null
            }
            throw err
        }
    },
    async forgotPassword(email: string): Promise<{ message: string }> {
        const { data } = await api.post<{ message: string }>('/api/forgot-password', { email })
        return data
    },
    async resetPassword(token: string, password: string): Promise<{ message: string }> {
        const { data } = await api.post<{ message: string }>('/api/reset-password', {
            token,
            password,
        })
        return data
    },
    async logout(): Promise<void> {
        await api.post('/api/logout')
    },
    /**
     * Lets an authenticated user set a password on their OWN account.
     * Identity comes from the active session — the server never accepts
     * an `email` / `userId` parameter, so this cannot be used to set
     * a password on someone else's account even if a forged request
     * reached the backend.
     */
    async setPassword(password: string): Promise<{ status: string; hasPassword: boolean }> {
        const { data } = await api.post<{ status: string; hasPassword: boolean }>(
            '/api/set-password',
            { password },
        )
        return data
    },
}