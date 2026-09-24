import api from './axios'
import type { CurrentUser } from '../types'

export const authApi = {
    async me(): Promise<CurrentUser | null> {
        try {
            const { data } = await api.get<CurrentUser>('/api/me')
            return data
        } catch {
            return null
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
}