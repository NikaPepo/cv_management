import api from './axios'
import type { DataType, ProfileAttributeRow } from '../types'

export type ProfileAttributePayload = {
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
}

export const profileAttributeApi = {
    async list(): Promise<ProfileAttributeRow[]> {
        const { data } = await api.get<ProfileAttributeRow[]>('/api/profile-attributes')
        return data
    },
    async set(payload: ProfileAttributePayload): Promise<ProfileAttributeRow> {
        const { data } = await api.post<ProfileAttributeRow>('/api/profile-attributes', payload)
        return data
    },
    async remove(definitionId: number): Promise<void> {
        await api.delete(`/api/profile-attributes/${definitionId}`)
    },
}

/**
 * Builds the request payload for a given attribute. Each dataType
 * writes only the column it owns — the backend resets the others.
 */
export function buildPayload(
    dataType: DataType,
    attributeDefinitionId: number,
    version: number | null,
    rawValue: unknown,
): ProfileAttributePayload {
    const payload: ProfileAttributePayload = { attributeDefinitionId, version }
    switch (dataType) {
        case 'string':
            payload.stringValue = (rawValue as string | null) ?? null
            break
        case 'text':
            payload.markdownText = (rawValue as string | null) ?? null
            break
        case 'numeric':
            payload.numericValue = (rawValue as string | null) ?? null
            break
        case 'date':
            payload.dateValue = (rawValue as string | null) ?? null
            break
        case 'period':
            {
                const v = (rawValue as { start: string | null; end: string | null } | null) ?? null
                payload.periodStart = v?.start ?? null
                payload.periodEnd = v?.end ?? null
            }
            break
        case 'boolean':
            payload.booleanValue = (rawValue as boolean | null) ?? null
            break
        case 'image':
            payload.imageUrl = (rawValue as string | null) ?? null
            break
        case 'one_of_many':
            // rawValue here is the option id (number) — caller resolves it.
            payload.selectedOptionId = (rawValue as number | null) ?? null
            break
    }
    return payload
}