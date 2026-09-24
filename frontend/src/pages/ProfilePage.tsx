import { useState } from 'react'
import { useTranslation } from '../contexts/AppPreferencesContext'
import MeSection from '../sections/MeSection'
import InfoSection from '../sections/InfoSection'
import ProjectsSection from '../sections/ProjectsSection'
import CvsSection from '../sections/CvsSection'

type Section = 'me' | 'info' | 'projects' | 'cvs'

export default function ProfilePage() {
    const t = useTranslation()
    const [section, setSection] = useState<Section>('me')

    return (
        <div>
            <ul className="nav nav-tabs mb-4">
                {(['me', 'info', 'projects', 'cvs'] as Section[]).map((s) => (
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
            {section === 'info' && <InfoSection />}
            {section === 'projects' && <ProjectsSection />}
            {section === 'cvs' && <CvsSection />}
        </div>
    )
}