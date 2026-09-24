import api from './axios'
import type { DataType } from '../types'

export interface CvSummary {
    id: number
    positionId: number
    positionTitle: string
    status: 'DRAFT' | 'PUBLISHED'
    publishedAt: string | null
    updatedAt: string
    accessible: boolean
    likeCount: number
}

export interface CvAttributeValue {
    attributeDefinitionId: number
    name: string
    description: string
    dataType: DataType
    required: boolean
    options: { id: number; value: string }[]
    sortOrder: number
    value: unknown
    empty: boolean
    version: number | null
}

export interface CvProject {
    id: number
    name: string
    periodStart: string | null
    periodEnd: string | null
    markdownDescription: string | null
    technologyTags: { id: number; name: string }[]
}

export interface CvView {
    cv: {
        id: number
        status: 'DRAFT' | 'PUBLISHED'
        createdAt: string
        updatedAt: string
        publishedAt: string | null
    }
    candidate: {
        firstName: string | null
        lastName: string | null
        location: string | null
        photoUrl: string | null
        email: string | null
    }
    position: {
        id: number
        title: string
        company: string | null
        level: string | null
        shortDescription: string
    }
    attributes: CvAttributeValue[]
    projects: CvProject[]
    likeCount: number
    hasUnpublishedRequired: boolean
    hasLiked?: boolean
}

export const cvApi = {
    async list(): Promise<CvSummary[]> {
        const { data } = await api.get<CvSummary[]>('/api/cvs')
        return data
    },
    async create(positionId: number): Promise<CvSummary> {
        const { data } = await api.post<CvSummary>('/api/cvs', { positionId })
        return data
    },
    async get(id: number): Promise<CvView> {
        const { data } = await api.get<CvView>(`/api/cvs/${id}`)
        return data
    },
    async publish(id: number): Promise<CvSummary> {
        const { data } = await api.post<CvSummary>(`/api/cvs/${id}/publish`)
        return data
    },
    async remove(id: number): Promise<void> {
        await api.delete(`/api/cvs/${id}`)
    },
    async setAttribute(
        cvId: number,
        payload: {
            attributeDefinitionId: number
            version?: number | null
            stringValue?: string | null
            markdownText?: string | null
            numericValue?: string | null
            dateValue?: string | null
            periodStart?: string | null
            periodEnd?: string | null
            booleanValue?: boolean | null
            imageUrl?: string | null
            selectedOptionId?: number | null
        },
    ): Promise<CvAttributeValue> {
        const { data } = await api.post<CvAttributeValue>(`/api/cvs/${cvId}/attributes`, payload)
        return data
    },
    async like(id: number): Promise<{ likeCount: number; liked: boolean }> {
        const { data } = await api.post<{ likeCount: number; liked: boolean }>(`/api/cvs/${id}/like`)
        return data
    },
    async unlike(id: number): Promise<{ likeCount: number; liked: boolean }> {
        const { data } = await api.delete<{ likeCount: number; liked: boolean }>(`/api/cvs/${id}/like`)
        return data
    },
    async listForPosition(positionId: number): Promise<{
        position: { id: number; title: string; company: string | null }
        cvs: CvView[]
    }> {
        const { data } = await api.get(`/api/positions/${positionId}/cvs`)
        return data
    },
}