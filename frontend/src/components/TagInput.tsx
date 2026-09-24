import { useEffect, useRef, useState } from 'react'
import { Form } from 'react-bootstrap'
import { projectApi } from '../api/projects'

interface Props {
    value: string[]
    onChange: (next: string[]) => void
    placeholder?: string
}

/**
 * Tag input with prefix-based autocomplete. The list of suggestions is
 * fetched from the server (which uses a unique index on tag.name), so
 * the input can be safely case-insensitive: we lowercase before sending.
 */
export default function TagInput({ value, onChange, placeholder }: Props) {
    const [draft, setDraft] = useState('')
    const [suggestions, setSuggestions] = useState<{ id: number; name: string }[]>([])
    const [open, setOpen] = useState(false)
    const containerRef = useRef<HTMLDivElement>(null)

    useEffect(() => {
        let cancelled = false
        const t = setTimeout(async () => {
            if (draft.trim() === '') {
                setSuggestions([])
                return
            }
            const list = await projectApi.tagAutocomplete(draft.trim().toLowerCase())
            if (!cancelled) {
                setSuggestions(list.filter((s) => !value.includes(s.name)))
                setOpen(true)
            }
        }, 150)
        return () => {
            cancelled = true
            clearTimeout(t)
        }
    }, [draft, value])

    useEffect(() => {
        const onClick = (e: MouseEvent) => {
            if (containerRef.current === null) return
            if (!containerRef.current.contains(e.target as Node)) {
                setOpen(false)
            }
        }
        document.addEventListener('mousedown', onClick)
        return () => document.removeEventListener('mousedown', onClick)
    }, [])

    const commit = (raw: string) => {
        const name = raw.trim().toLowerCase()
        if (name === '') return
        if (value.includes(name)) return
        onChange([...value, name])
        setDraft('')
        setSuggestions([])
    }

    const onKey = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault()
            commit(draft)
        } else if (e.key === 'Backspace' && draft === '' && value.length > 0) {
            onChange(value.slice(0, -1))
        }
    }

    return (
        <div ref={containerRef} className="position-relative">
            <div className="d-flex flex-wrap gap-2 border rounded p-2">
                {value.map((tag) => (
                    <span key={tag} className="badge text-bg-primary d-flex align-items-center gap-1">
                        {tag}
                        <button
                            type="button"
                            className="btn-close btn-close-white btn-close-sm"
                            aria-label="Remove"
                            style={{ fontSize: 10 }}
                            onClick={() => onChange(value.filter((t) => t !== tag))}
                        />
                    </span>
                ))}
                <Form.Control
                    type="text"
                    size="sm"
                    className="flex-grow-1"
                    style={{ minWidth: 100, border: 'none', boxShadow: 'none' }}
                    placeholder={placeholder ?? 'Add tag…'}
                    value={draft}
                    onChange={(e) => setDraft(e.target.value)}
                    onKeyDown={onKey}
                    onFocus={() => setOpen(true)}
                />
            </div>
            {open && suggestions.length > 0 && (
                <div
                    className="position-absolute top-100 start-0 end-0 mt-1 shadow-sm border bg-body rounded"
                    style={{ zIndex: 1080 }}
                >
                    {suggestions.map((s) => (
                        <button
                            key={s.id}
                            type="button"
                            className="d-block w-100 text-start px-3 py-1 border-bottom btn btn-link text-decoration-none"
                            onClick={() => commit(s.name)}
                        >
                            {s.name}
                        </button>
                    ))}
                </div>
            )}
        </div>
    )
}