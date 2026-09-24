import ReactMarkdown from 'react-markdown'

interface Props {
    source: string | null
    className?: string
}

export default function Markdown({ source, className }: Props) {
    if (source === null || source === '') {
        return <span className={className}>—</span>
    }
    return (
        <div className={className}>
            <ReactMarkdown>{source}</ReactMarkdown>
        </div>
    )
}