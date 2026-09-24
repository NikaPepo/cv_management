import api from './axios'

export interface Project {
    id: number
    name: string
    periodStart: string | null
    periodEnd: string | null
    markdownDescription: string | null
    technologyTags: { id: number; name: string }[]
    createdAt: string
}

export interface CreateProjectPayload {
    name: string
    periodStart?: string | null
    periodEnd?: string | null
    markdownDescription?: string | null
    technologyTagNames: string[]
}

export interface UpdateProjectPayload {
    name?: string
    periodStart?: string | null
    periodEnd?: string | null
    markdownDescription?: string | null
    technologyTagNames?: string[]
}

export const projectApi = {
    async list(): Promise<Project[]> {
        const { data } = await api.get<Project[]>('/api/projects')
        return data
    },
    async create(payload: CreateProjectPayload): Promise<Project> {
        const { data } = await api.post<Project>('/api/projects', payload)
        return data
    },
    async update(id: number, payload: UpdateProjectPayload): Promise<Project> {
        const { data } = await api.put<Project>(`/api/projects/${id}`, payload)
        return data
    },
    async remove(id: number): Promise<void> {
        await api.delete(`/api/projects/${id}`)
    },
    async tagAutocomplete(prefix: string): Promise<{ id: number; name: string }[]> {
        const { data } = await api.get<{ id: number; name: string }[]>('/api/projects/tags', {
            params: { prefix },
        })
        return data
    },
}

export const cloudinaryApi = {
    async signature(folder: string): Promise<{
        cloudName: string
        apiKey: string
        timestamp: number
        signature: string
        folder: string
    }> {
        const { data } = await api.post('/api/cloudinary/signature', { folder })
        return data
    },
    async uploadImage(
        file: File,
        folder: string,
    ): Promise<string> {
        const sig = await this.signature(folder)
        const formData = new FormData()
        formData.append('file', file)
        formData.append('api_key', sig.apiKey)
        formData.append('timestamp', String(sig.timestamp))
        formData.append('signature', sig.signature)
        formData.append('folder', sig.folder)
        const url = `https://api.cloudinary.com/v1_1/${sig.cloudName}/image/upload`
        const response = await fetch(url, { method: 'POST', body: formData })
        const json = (await response.json()) as { secure_url: string }
        return json.secure_url
    },
}