import { useEffect, useRef, useState } from 'react'
import { Button, Form } from 'react-bootstrap'
import { discussionApi, type DiscussionPost } from '../api/discussion'
import Markdown from './Markdown'
import { useAuth } from '../contexts/AuthContext'

const POLL_MS = 3000

interface Props {
    positionId: number
}

/**
 * Position discussion tab. Posts are append-only chronological; new posts
 * arrive via polling every 3s (within the 2–5s window the assignment
 * requires). Updates only fetch `sinceId=lastSeen` so we don't re-render
 * the existing list every poll.
 */
export default function Discussion({ positionId }: Props) {
    const { user } = useAuth()
    const [posts, setPosts] = useState<DiscussionPost[]>([])
    const [draft, setDraft] = useState('')
    const [sending, setSending] = useState(false)
    const latestIdRef = useRef<number>(0)

    useEffect(() => {
        let cancelled = false
        const tick = async () => {
            try {
                const list = await discussionApi.list(positionId, latestIdRef.current)
                if (cancelled) return
                if (list.length > 0) {
                    setPosts((cur) => {
                        const next = latestIdRef.current === 0 ? list : [...cur, ...list]
                        latestIdRef.current = list[list.length - 1].id
                        return next
                    })
                }
            } catch {
                /* swallow; polling is best-effort */
            }
        }
        void tick()
        const id = window.setInterval(() => void tick(), POLL_MS)
        return () => {
            cancelled = true
            window.clearInterval(id)
        }
    }, [positionId])

    const submit = async () => {
        if (draft.trim() === '') return
        setSending(true)
        try {
            const post = await discussionApi.post(positionId, draft.trim())
            setDraft('')
            setPosts((cur) => [...cur, post])
            latestIdRef.current = Math.max(latestIdRef.current, post.id)
        } finally {
            setSending(false)
        }
    }

    return (
        <div>
            {posts.length === 0 ? (
                <div className="text-muted mb-3">No posts yet.</div>
            ) : (
                <ul className="list-group mb-3">
                    {posts.map((p) => (
                        <li key={p.id} className="list-group-item">
                            <div className="d-flex justify-content-between mb-1">
                                <strong>{p.authorName}</strong>
                                <small className="text-muted">
                                    {new Date(p.createdAt).toLocaleString()}
                                </small>
                            </div>
                            <Markdown source={p.content} />
                        </li>
                    ))}
                </ul>
            )}

            {user !== null && (
                <div>
                    <Form.Group className="mb-2">
                        <Form.Label>New post (Markdown)</Form.Label>
                        <Form.Control
                            as="textarea"
                            rows={3}
                            value={draft}
                            onChange={(e) => setDraft(e.target.value)}
                        />
                    </Form.Group>
                    <Button onClick={() => void submit()} disabled={sending || draft.trim() === ''}>
                        {sending ? '…' : 'Post'}
                    </Button>
                </div>
            )}
        </div>
    )
}