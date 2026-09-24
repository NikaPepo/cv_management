import api from './axios'
import type {
    AttributeCategory,
    AttributeDefinition,
    AttributeLookupResponse,
    DataType,
} from '../types'

export interface CreateAttributePayload {
    name: string
    description: string
    dataType: DataType
    categoryId: number
    required: boolean
    options?: { value: string; sortOrder: number }[]
}

export interface UpdateAttributePayload {
    name?: string
    description?: string
    categoryId?: number
    required?: boolean
    options?: { value: string; sortOrder: number }[]
}

export interface AttributeLookupRequest {
    prefix?: string
    categoryId?: number
    recentOnly?: boolean
    limit?: number
}

export const attributeApi = {
    async list(): Promise<AttributeDefinition[]> {
        const { data } = await api.get<AttributeDefinition[]>('/api/attributes')
        return data
    },
    async get(id: number): Promise<AttributeDefinition> {
        const { data } = await api.get<AttributeDefinition>(`/api/attributes/${id}`)
        return data
    },
    async create(payload: CreateAttributePayload): Promise<AttributeDefinition> {
        const { data } = await api.post<AttributeDefinition>('/api/attributes', payload)
        return data
    },
    async update(id: number, payload: UpdateAttributePayload): Promise<AttributeDefinition> {
        const { data } = await api.put<AttributeDefinition>(`/api/attributes/${id}`, payload)
        return data
    },
    async delete(id: number): Promise<void> {
        await api.delete(`/api/attributes/${id}`)
    },
    async lookup(req: AttributeLookupRequest): Promise<AttributeLookupResponse> {
        const params: Record<string, string | number | boolean> = {}
        if (req.prefix !== undefined && req.prefix !== '') params.prefix = req.prefix
        if (req.categoryId !== undefined) params.categoryId = req.categoryId
        if (req.recentOnly) params.recentOnly = '1'
        if (req.limit) params.limit = req.limit
        const { data } = await api.get<AttributeLookupResponse>('/api/attributes/lookup', { params })
        return data
    },
    async listCategories(): Promise<AttributeCategory[]> {
        const { data } = await api.get<AttributeCategory[]>('/api/attributes/categories')
        return data
    },
    async createCategory(payload: { code: string; name: string; sortOrder: number }): Promise<AttributeCategory> {
        const { data } = await api.post<AttributeCategory>('/api/attributes/categories', payload)
        return data
    },
}