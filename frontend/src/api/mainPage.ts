import api from './axios'
import type { Position } from './positions'

export interface MainPage {
    latest: Position[]
    popular: (Position & { submittedCvs: number })[]
    tagCloud: { name: string; count: number }[]
    statistics: {
        cvsLast24h: number
        totalPositions: number
        totalCandidates: number
        totalRecruiters: number
        totalPublishedCvs: number
    }
}

export const mainPageApi = {
    async load(): Promise<MainPage> {
        const { data } = await api.get<MainPage>('/api/main-page')
        return data
    },
}