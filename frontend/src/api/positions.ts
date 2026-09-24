import api from './axios'

export type PositionLevel = 'junior' | 'middle' | 'senior' | 'c_level'
export type AccessRuleOperator =
    | 'eq' | 'ne' | 'gt' | 'gte' | 'lt' | 'lte'
    | 'in' | 'contains' | 'before' | 'after'

export interface PositionAttributeInput {
    attributeDefinitionId: number
    sortOrder: number
}

export interface PositionAccessRuleInput {
    attributeDefinitionId: number
    operator: AccessRuleOperator
    value: unknown
}

export interface Position {
    id: number
    /** Optimistic-lock token; echo back on update to detect concurrent edits. */
    version: number | null
    title: string
    shortDescription: string
    company: string | null
    level: PositionLevel | null
    isPublic: boolean
    maxProjects: number
    projectTagFilter: string[]
    attributes: {
        attributeDefinitionId: number
        name: string
        dataType: string
        sortOrder: number
    }[]
    updatedAt: string
    accessRules?: {
        attributeDefinitionId: number
        attributeName: string
        operator: AccessRuleOperator
        value: unknown
    }[]
    submittedCvs?: number
    accessible?: boolean
}

export interface CreatePositionPayload {
    title: string
    shortDescription: string
    company?: string | null
    level?: PositionLevel | null
    isPublic: boolean
    maxProjects: number
    projectTagFilter: string[]
    attributes: PositionAttributeInput[]
    accessRules: PositionAccessRuleInput[]
}

export interface UpdatePositionPayload extends Partial<CreatePositionPayload> {
    /** Version we loaded; server returns 409 if it has moved since. */
    version?: number | null
}

export const positionApi = {
    async list(opts: { all?: boolean; company?: string; level?: string } = {}): Promise<Position[]> {
        const { data } = await api.get<Position[]>('/api/positions', { params: opts })
        return data
    },
    async latest(): Promise<Position[]> {
        const { data } = await api.get<Position[]>('/api/positions/latest')
        return data
    },
    async popular(): Promise<Position[]> {
        const { data } = await api.get<Position[]>('/api/positions/popular')
        return data
    },
    async get(id: number): Promise<Position> {
        const { data } = await api.get<Position>(`/api/positions/${id}`)
        return data
    },
    async create(payload: CreatePositionPayload): Promise<Position> {
        const { data } = await api.post<Position>('/api/positions', payload)
        return data
    },
    async update(id: number, payload: UpdatePositionPayload): Promise<Position> {
        const { data } = await api.put<Position>(`/api/positions/${id}`, payload)
        return data
    },
    async duplicate(id: number, newTitle?: string): Promise<Position> {
        const { data } = await api.post<Position>(`/api/positions/${id}/duplicate`, {
            title: newTitle,
        })
        return data
    },
    async remove(id: number): Promise<void> {
        await api.delete(`/api/positions/${id}`)
    },
}