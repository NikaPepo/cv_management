import api from './axios'

export interface DiscussionPost {
    id: number
    authorId: number
    authorName: string
    authorEmail: string
    content: string
    createdAt: string
}

export const discussionApi = {
    async list(positionId: number, sinceId?: number): Promise<DiscussionPost[]> {
        const params: Record<string, number> = {}
        if (sinceId !== undefined) params.sinceId = sinceId
        const { data } = await api.get<DiscussionPost[]>(
            `/api/positions/${positionId}/discussion`,
            { params }
        )
        return data
    },
    async post(positionId: number, content: string): Promise<DiscussionPost> {
        const { data } = await api.post<DiscussionPost>(
            `/api/positions/${positionId}/discussion`,
            { content }
        )
        return data
    },
}