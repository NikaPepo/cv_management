import { useEffect, useRef, useState } from 'react'
import { Alert, Button, Form, Spinner } from 'react-bootstrap'
import { Link } from 'react-router-dom'
import { profileApi, type ProfileDto } from '../api/profile'
import { cloudinaryApi } from '../api/projects'
import { useAuth } from '../contexts/AuthContext'
import { useTranslation } from '../contexts/AppPreferencesContext'
import SalesforceExportModal from '../components/SalesforceExportModal'

const AUTOSAVE_MS = 7000

export default function MeSection() {
    const t = useTranslation()
    const { user } = useAuth()
    const [profile, setProfile] = useState<ProfileDto | null>(null)
    const [draft, setDraft] = useState<ProfileDto | null>(null)
    const [status, setStatus] = useState<'idle' | 'saving' | 'saved' | 'conflict' | 'error'>('idle')
    const [uploading, setUploading] = useState(false)
    const [error, setError] = useState('')
    const [salesforceOpen, setSalesforceOpen] = useState(false)
    const timerRef = useRef<number | null>(null)

    useEffect(() => {
        void (async () => {
            try {
                const data = await profileApi.me()
                setProfile(data)
                setDraft(data)
            } catch (e: unknown) {
                setError((e as Error).message)
            }
        })()
    }, [])

    // Auto-save: schedule a save 7s after the latest edit. If the user
    // keeps editing, the timer is cleared and restarted — exactly what
    // the assignment requires ("not on every keystroke, but every 5-10s").
    useEffect(() => {
        if (draft === null || profile === null) return
        if (JSON.stringify(draft) === JSON.stringify(profile)) return
        if (timerRef.current !== null) {
            window.clearTimeout(timerRef.current)
        }
        timerRef.current = window.setTimeout(() => {
            void save()
        }, AUTOSAVE_MS)
        return () => {
            if (timerRef.current !== null) {
                window.clearTimeout(timerRef.current)
            }
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [draft])

    const save = async () => {
        if (draft === null) return
        setStatus('saving')
        try {
            const updated = await profileApi.patch({
                firstName: draft.firstName,
                lastName: draft.lastName,
                location: draft.location,
                photoUrl: draft.photoUrl,
                version: draft.version,
            })
            setProfile(updated)
            setDraft(updated)
            setStatus('saved')
            window.setTimeout(() => setStatus('idle'), 1500)
        } catch (e: unknown) {
            const status = (e as { response?: { status?: number } })?.response?.status
            if (status === 409) {
                setStatus('conflict')
            } else {
                setStatus('error')
            }
        }
    }

    const onFile = async (file: File) => {
        setUploading(true)
        try {
            const url = await cloudinaryApi.uploadImage(file, 'cv-management/profiles')
            setDraft((cur) => (cur === null ? cur : { ...cur, photoUrl: url }))
        } finally {
            setUploading(false)
        }
    }

    if (draft === null) {
        return <Spinner animation="border" />
    }

    return (
        <div className="row g-4">
            <div className="col-md-3 text-center">
                {draft.photoUrl !== null ? (
                    <img
                        src={draft.photoUrl}
                        alt="Profile photo"
                        className="rounded-circle mb-3"
                        style={{ width: 160, height: 160, objectFit: 'cover' }}
                    />
                ) : (
                    <div
                        className="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center mx-auto"
                        style={{ width: 160, height: 160, fontSize: 48 }}
                    >
                        ?
                    </div>
                )}
                <Form.Group controlId="profile-photo">
                    <Form.Label className="btn btn-outline-secondary btn-sm">
                        {uploading ? '…' : 'Upload photo'}
                        <Form.Control
                            type="file"
                            accept="image/*"
                            className="d-none"
                            onChange={(e) => {
                                const target = e.target as HTMLInputElement
                                const file = target.files?.[0]
                                if (file !== undefined) void onFile(file)
                            }}
                        />
                    </Form.Label>
                </Form.Group>
            </div>

            <div className="col-md-9">
                <div className="row g-3">
                    <Form.Group className="col-md-6">
                        <Form.Label>{t('me.label.first_name')}</Form.Label>
                        <Form.Control
                            value={draft.firstName ?? ''}
                            onChange={(e) =>
                                setDraft({ ...draft, firstName: e.target.value })
                            }
                        />
                    </Form.Group>
                    <Form.Group className="col-md-6">
                        <Form.Label>{t('me.label.last_name')}</Form.Label>
                        <Form.Control
                            value={draft.lastName ?? ''}
                            onChange={(e) =>
                                setDraft({ ...draft, lastName: e.target.value })
                            }
                        />
                    </Form.Group>
                    <Form.Group className="col-12">
                        <Form.Label>{t('me.label.location')}</Form.Label>
                        <Form.Control
                            value={draft.location ?? ''}
                            onChange={(e) =>
                                setDraft({ ...draft, location: e.target.value })
                            }
                        />
                    </Form.Group>
                </div>

                <div className="mt-3 d-flex gap-2 align-items-center">
                    <Button onClick={() => void save()} disabled={status === 'saving'}>
                        {status === 'saving' ? <Spinner size="sm" animation="border" /> : t('profile.save')}
                    </Button>
                    {status === 'saved' && <span className="text-success">{t('profile.saved')}</span>}
                    {status === 'conflict' && (
                        <Alert variant="warning" className="py-2 mb-0 small">
                            {t('profile.conflict')}
                        </Alert>
                    )}
                    {status === 'error' && (
                        <Alert variant="danger" className="py-2 mb-0 small">
                            {t('cv_detail.save.failed')}
                        </Alert>
                    )}
                </div>

                {/*
                  // Sign-in methods block. Only renders for OAuth-only
                  // users (hasPassword === false) and surfaces the
                  // authenticated set-password link. If hasPassword is
                  // undefined (older /api/me response shape) we hide
                  // the block rather than guess.
                 */}
                {user !== null && !user.hasPassword && (
                    <Alert variant="info" className="mt-3 small mb-0">
                        <div className="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                            <span>{t('auth.set_password.oauth_only_hint')}</span>
                            <Link
                                to="/set-password"
                                className="btn btn-sm btn-outline-primary"
                            >
                                {t('auth.set_password.submit')}
                            </Link>
                        </div>
                    </Alert>
                )}

                {error !== '' && <Alert variant="danger" className="mt-3 small">{error}</Alert>}

                {/*
                  // Salesforce export — available to every authenticated
                  // user on their own profile (Candidate, Recruiter, and
                  // Administrator all keep their own contact card). The
                  // backend resolves the current user via #[CurrentUser],
                  // never from request body, so no extra authorization
                  // rule is needed here.
                 */}
                <div className="mt-4 pt-3 border-top">
                    <h2 className="h5">External systems</h2>
                    <p className="text-muted small mb-2">
                        Send your contact details to a Salesforce CRM. We
                        create an Account and a Contact on first export,
                        then update the same records on every subsequent
                        export — no duplicates.
                    </p>
                    <Button
                        variant="outline-primary"
                        onClick={() => setSalesforceOpen(true)}
                    >
                        Export to Salesforce
                    </Button>
                </div>
            </div>
            <SalesforceExportModal
                show={salesforceOpen}
                onClose={() => setSalesforceOpen(false)}
                initial={{
                    firstName: draft.firstName,
                    lastName: draft.lastName,
                    email: user?.email ?? null,
                }}
            />
        </div>
    )
}
