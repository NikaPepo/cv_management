import { useState } from 'react'
import { useTranslation } from '../contexts/AppPreferencesContext'
import { useAuth } from '../contexts/AuthContext'
import MeSection from '../sections/MeSection'
import InfoSection from '../sections/InfoSection'
import ProjectsSection from '../sections/ProjectsSection'
import CvsSection from '../sections/CvsSection'

type Section = 'me' | 'info' | 'projects' | 'cvs'

/**
 * Per-role tab list for the personal profile page. ROLE_ADMIN inherits
 * ROLE_RECRUITER, so admin keeps the candidate-style tabs; recruiters
 * own only the `Me` section (their own contact card) and reach CVs
 * through PositionCvsPage instead of this profile.
 */
const CANDIDATE_TABS: Section[] = ['me', 'info', 'projects', 'cvs']
const RECRUITER_TABS: Section[] = ['me']

function tabsFor(hasRole: (...roles: string[]) => boolean): Section[] {
    if (hasRole('ROLE_ADMIN')) return CANDIDATE_TABS
    if (hasRole('ROLE_RECRUITER')) return RECRUITER_TABS
    return CANDIDATE_TABS
}

export default function ProfilePage() {
    const t = useTranslation()
    const { hasRole } = useAuth()
    const tabs = tabsFor(hasRole)
    const [section, setSection] = useState<Section>(tabs[0])

    return (
        <div>
            <ul className="nav nav-tabs mb-4">
                {tabs.map((s) => (
                    <li key={s} className="nav-item">
                        <button
                            className={`nav-link ${section === s ? 'active' : ''}`}
                            onClick={() => setSection(s)}
                        >
                            {t(`profile.${s}.title`)}
                        </button>
                    </li>
                ))}
            </ul>

            {section === 'me' && <MeSection />}
            {section === 'info' && tabs.includes('info') && <InfoSection />}
            {section === 'projects' && tabs.includes('projects') && <ProjectsSection />}
            {section === 'cvs' && tabs.includes('cvs') && <CvsSection />}
        </div>
    )
}