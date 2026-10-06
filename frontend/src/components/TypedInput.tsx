import { Form } from 'react-bootstrap'
import type { AttributeDefinition, DataType } from '../types'
import { useT } from '../contexts/AppPreferencesContext'

interface TypedInputProps {
    dataType: DataType
    definition: AttributeDefinition
    value: unknown
    onChange: (v: unknown) => void
    onBlur?: () => void
}

/**
 * Renders the correct input control for a given AttributeDefinition
 * dataType. Used by both Profile/InfoSection and the position editor's
 * Access Rules UI so the value-edit semantics match the project's
 * single Attribute Library.
 *
 * The component is presentation-only — it does NOT enforce value shape
 * (the backend is the source of truth for validation; see
 * PositionService::syncAccessRules and ProfileAttributeController).
 */
export default function TypedInput({
    dataType,
    definition,
    value,
    onChange,
    onBlur,
}: TypedInputProps) {
    const { t } = useT()
    switch (dataType) {
        case 'string':
            return (
                <Form.Control
                    type="text"
                    value={(value as string | null) ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                    onBlur={onBlur}
                />
            )
        case 'text':
            return (
                <Form.Control
                    as="textarea"
                    rows={4}
                    value={(value as string | null) ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                    onBlur={onBlur}
                />
            )
        case 'numeric':
            return (
                <Form.Control
                    type="number"
                    step="any"
                    value={(value as string | null) ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                    onBlur={onBlur}
                />
            )
        case 'date':
            return (
                <Form.Control
                    type="date"
                    value={(value as string | null) ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                    onBlur={onBlur}
                />
            )
        case 'period': {
            const v =
                (value as { start: string | null; end: string | null } | null) ?? {
                    start: null,
                    end: null,
                }
            return (
                <div className="row g-2">
                    <div className="col-6">
                        <Form.Label className="small text-muted mb-1">{t('info.label.from')}</Form.Label>
                        <Form.Control
                            type="date"
                            value={v.start ?? ''}
                            onChange={(e) =>
                                onChange({
                                    ...v,
                                    start: e.target.value === '' ? null : e.target.value,
                                })
                            }
                            onBlur={onBlur}
                        />
                    </div>
                    <div className="col-6">
                        <Form.Label className="small text-muted mb-1">{t('info.label.to')}</Form.Label>
                        <Form.Control
                            type="date"
                            value={v.end ?? ''}
                            onChange={(e) =>
                                onChange({
                                    ...v,
                                    end: e.target.value === '' ? null : e.target.value,
                                })
                            }
                            onBlur={onBlur}
                        />
                    </div>
                </div>
            )
        }
        case 'boolean':
            return (
                <Form.Check
                    type="switch"
                    checked={Boolean(value)}
                    onChange={(e) => onChange(e.target.checked)}
                />
            )
        case 'image':
            return (
                <Form.Control
                    type="url"
                    placeholder={t('info.placeholder.cloudinary')}
                    value={(value as string | null) ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                    onBlur={onBlur}
                />
            )
        case 'one_of_many':
            return (
                <Form.Select
                    value={(value as number | null) ?? ''}
                    onChange={(e) =>
                        onChange(e.target.value === '' ? null : Number(e.target.value))
                    }
                >
                    <option value="">—</option>
                    {(definition.options ?? []).map((o) => (
                        <option key={o.id} value={o.id}>
                            {o.value}
                        </option>
                    ))}
                </Form.Select>
            )
        default:
            return null
    }
}