import { useState } from 'react'
import { Button, Card, Container } from 'react-bootstrap'
import { useNavigate } from 'react-router-dom'
import api from '../api/axios'
import { useAuth } from '../contexts/AuthContext'
import { useTranslation } from '../contexts/AppPreferencesContext'

export default function ChooseAccountTypePage() {
    const t = useTranslation()
    const navigate = useNavigate()
    const { refresh } = useAuth()
    const [loading, setLoading] = useState(false)
    const [error, setError] = useState('')

    const chooseRole = async (role: 'candidate' | 'recruiter') => {
        setLoading(true)
        setError('')

        try {
            await api.post('/api/oauth/complete-registration', {
                accountType: role,
            })
            await refresh()
            navigate('/', { replace: true })
        } catch (e: unknown) {
            const msg = (e as { response?: { data?: { error?: string } } })?.response?.data?.error
            setError(msg ?? t('account_type.error.save'))
        } finally {
            setLoading(false)
        }
    }

    return (
        <Container className="py-5">
            <Card className="mx-auto" style={{ maxWidth: 500 }}>
                <Card.Body>
                    <Card.Title>{t('account_type.choose')}</Card.Title>

                    {error && <p className="text-danger">{error}</p>}

                    <div className="d-grid gap-2">
                        <Button
                            disabled={loading}
                            onClick={() => void chooseRole('candidate')}
                        >
                            {t('account_type.candidate')}
                        </Button>

                        <Button
                            variant="outline-primary"
                            disabled={loading}
                            onClick={() => void chooseRole('recruiter')}
                        >
                            {t('account_type.recruiter')}
                        </Button>
                    </div>
                </Card.Body>
            </Card>
        </Container>
    )
}