import { useEffect, useState } from 'react'
import { Alert, Button, Form, Modal, Spinner } from 'react-bootstrap'
import { positionApi, type ApiTokenMetadata } from '../api/positions'
import { useT } from '../contexts/AppPreferencesContext'

interface Props {
    positionId: number
}

/**
 * API token management section for the Position editor. Read-only from
 * the rest of the editor: it never participates in the Save action,
 * has its own loading/error state, and never blocks the user from
 * saving the Position itself.
 */
export default function PositionApiTokenSection({ positionId }: Props) {
    const { t } = useT()
    const [items, setItems] = useState<ApiTokenMetadata[] | null>(null)
    const [error, setError] = useState('')
    const [generating, setGenerating] = useState(false)
    const [label, setLabel] = useState('')

    // The freshly-issued plaintext, shown exactly once.
    const [newSecret, setNewSecret] = useState<string | null>(null)
    const [copied, setCopied] = useState(false)

    const load = async () => {
        setError('')
        try {
            const { items } = await positionApi.listApiTokens(positionId)
            setItems(items)
        } catch (e: unknown) {
            const msg = (e as { response?: { data?: { error?: string } } })?.response?.data?.error
            setError(msg ?? t('common.error'))
        }
    }

    useEffect(() => {
        void load()
    }, [positionId])

    const onGenerate = async () => {
        setGenerating(true)
        setError('')
        try {
            const result = await positionApi.createApiToken(positionId, label === '' ? undefined : label)
            setNewSecret(result.secret)
            setLabel('')
            await load()
        } catch (e: unknown) {
            const msg = (e as { response?: { data?: { error?: string } } })?.response?.data?.error
            setError(msg ?? t('common.error'))
        } finally {
            setGenerating(false)
        }
    }

    const onRevoke = async (tokenId: number) => {
        if (!window.confirm(t('editor.api_token.revoke_confirm'))) return
        setError('')
        try {
            await positionApi.revokeApiToken(positionId, tokenId)
            await load()
        } catch (e: unknown) {
            const msg = (e as { response?: { data?: { error?: string } } })?.response?.data?.error
            setError(msg ?? t('common.error'))
        }
    }

    const onDelete = async (tokenId: number) => {
        if (!window.confirm(t('editor.api_token.delete_confirm'))) return
        setError('')
        try {
            await positionApi.deleteApiToken(positionId, tokenId)
            await load()
        } catch (e: unknown) {
            const msg = (e as { response?: { data?: { error?: string } } })?.response?.data?.error
            setError(msg ?? t('common.error'))
        }
    }

    const onCopy = async () => {
        if (newSecret === null) return
        try {
            await navigator.clipboard.writeText(newSecret)
            setCopied(true)
        } catch {
            // Clipboard API not available or denied — the user can
            // still copy manually from the visible text.
        }
    }

    return (
        <div className="mb-4">
            <h3 className="mb-3">{t('editor.api_token.title')}</h3>
            {error !== '' && (
                <Alert variant="danger" className="mb-2">{error}</Alert>
            )}

            {items === null ? (
                <Spinner animation="border" size="sm" />
            ) : items.length === 0 ? (
                <div className="text-muted mb-3">{t('editor.api_token.empty')}</div>
            ) : (
                <div className="mb-3">
                    {items.map((item) => (
                        <div key={item.id} className="d-flex align-items-center gap-2 py-2 border-bottom">
                            <div className="flex-grow-1">
                                <div>
                                    <strong>{item.label ?? item.prefix}</strong>
                                    <span className="text-muted ms-2 small">{item.prefix}…</span>
                                </div>
                                <div className="small text-muted">
                                    {item.isActive ? t('editor.api_token.status.active') : t('editor.api_token.status.revoked')}
                                    {' · '}
                                    {item.lastUsedAt === null
                                        ? t('editor.api_token.never_used')
                                        : new Date(item.lastUsedAt).toLocaleString()}
                                </div>
                            </div>
                            {item.isActive && (
                                <Button
                                    size="sm"
                                    variant="outline-danger"
                                    onClick={() => void onRevoke(item.id)}
                                >
                                    {t('editor.api_token.revoke')}
                                </Button>
                            )}
                            {!item.isActive && (
                                <Button
                                    size="sm"
                                    variant="outline-secondary"
                                    onClick={() => void onDelete(item.id)}
                                >
                                    {t('editor.api_token.delete')}
                                </Button>
                            )}
                        </div>
                    ))}
                </div>
            )}

            <div className="d-flex gap-2 align-items-end">
                <Form.Group className="flex-grow-1">
                    <Form.Label className="small mb-1">{t('editor.api_token.label')}</Form.Label>
                    <Form.Control
                        size="sm"
                        value={label}
                        onChange={(e) => setLabel(e.target.value)}
                        placeholder={t('editor.api_token.label')}
                        disabled={generating}
                    />
                </Form.Group>
                <Button onClick={() => void onGenerate()} disabled={generating}>
                    {generating ? '…' : t('editor.api_token.generate')}
                </Button>
            </div>

            <Modal show={newSecret !== null} onHide={() => { setNewSecret(null); setCopied(false) }} centered>
                <Modal.Header closeButton>
                    <Modal.Title>{t('editor.api_token.title')}</Modal.Title>
                </Modal.Header>
                <Modal.Body>
                    <Alert variant="warning">{t('editor.api_token.shown_once')}</Alert>
                    <pre className="bg-light p-2 user-select-all" style={{ wordBreak: 'break-all' }}>
                        {newSecret}
                    </pre>
                </Modal.Body>
                <Modal.Footer>
                    <Button variant="secondary" onClick={() => void onCopy()}>
                        {copied ? t('editor.api_token.copied') : t('editor.api_token.copy')}
                    </Button>
                    <Button variant="primary" onClick={() => { setNewSecret(null); setCopied(false) }}>
                        {t('editor.api_token.close')}
                    </Button>
                </Modal.Footer>
            </Modal>
        </div>
    )
}
