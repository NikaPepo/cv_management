import api from './axios'

export interface SalesforceExportRequest {
    accountName: string
    industry?: string | null
    lastName?: string | null
    notes?: string | null
    title?: string | null
    phone?: string | null
}

export interface SalesforceExportResponse {
    success: boolean
    accountId: string
    contactId: string | null
    created: boolean
}

export interface SalesforceExportError {
    error: string
}

export const salesforceApi = {
    /**
     * Export the current user's profile to Salesforce. The backend pulls
     * FirstName / Email out of the Profile+User so we only carry the
     * values the user types in the form.
     */
    async export(payload: SalesforceExportRequest): Promise<SalesforceExportResponse> {
        const { data } = await api.post<SalesforceExportResponse>('/api/profile/salesforce', {
            accountName: payload.accountName,
            industry: payload.industry ?? null,
            lastName: payload.lastName ?? null,
            notes: payload.notes ?? null,
            title: payload.title ?? null,
            phone: payload.phone ?? null,
        })
        return data
    },
}