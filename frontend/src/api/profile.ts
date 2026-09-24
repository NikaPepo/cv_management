import api from './axios'

export interface ProfileDto {
    id: number
    firstName: string | null
    lastName: string | null
    location: string | null
    photoUrl: string | null
    version: number
    updatedAt: string
}

export const profileApi = {
    async me(): Promise<ProfileDto> {
        const { data } = await api.get<ProfileDto>('/api/profile/me')
        return data
    },
    async patch(payload: Partial<ProfileDto>): Promise<ProfileDto> {
        const { data } = await api.patch<ProfileDto>('/api/profile/me', payload)
        return data
    },
}