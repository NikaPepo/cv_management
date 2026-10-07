import { useEffect, useState } from 'react'
import { Alert, Button, Col, Form, Modal, Row, Spinner } from 'react-bootstrap'
import { salesforceApi, type SalesforceExportError } from '../api/salesforce'

interface Props {
    show: boolean
    onClose: () => void
    /**
     * The current profile snapshot — pre-fills FirstName / Email
     * display rows and decides whether LastName is mandatory.
     */
    initial: {
        firstName: string | null
        lastName: string | null
        email: string | null
    }
}

type SubmitState =
    | { kind: 'idle' }
    | { kind: 'submitting' }
    | { kind: 'success' }
    | { kind: 'error', message: string }

/**
 * Modal behind "Export to Salesforce" on the /profile page.
 *
 * Layout:
 *   - Desktop (lg+):  two columns.
 *       Left  — Company name, Industry, Title, Phone (the fields the
 *                 user actually types into).
 *       Right — First name, Last name, Email, Notes (the read-only
 *                 profile data + the long Notes textarea).
 *   - Mobile / tablet (md and under): single column.
 *     Read-only fields are explicitly `readOnly plaintext` and
 *     remain accessible to screen readers via `aria-readonly`.
 *
 * Fields:
 *   - Company name (required)       → Account.Name
 *   - Industry (optional)           → Account.Industry
 *   - Last name (required if Profile.lastName is empty)
 *                                    → Contact.LastName (Salesforce
 *                                      requires this on create)
 *   - Title (optional)              → Contact.Title
 *   - Phone (optional)              → Contact.Phone
 *   - Notes (optional)              → Contact.Description
 *
 * FirstName and Email are *displayed* (read-only) so the user knows
 * what the backend will use, but never editable on this form —
 * changing them mid-export would create a contact unrelated to the
 * profile, which the spec disallows.
 */
export default function SalesforceExportModal({ show, onClose, initial }: Props) {
    const [accountName, setAccountName] = useState('')
    const [industry, setIndustry] = useState('')
    const [lastName, setLastName] = useState('')
    const [title, setTitle] = useState('')
    const [phone, setPhone] = useState('')
    const [notes, setNotes] = useState('')
    const [submit, setSubmit] = useState<SubmitState>({ kind: 'idle' })

    // Reset form state every time the modal is opened. Without
    // this, a successful export followed by a re-open would carry
    // the previous values into a fresh submit.
    useEffect(() => {
        if (show) {
            setAccountName('')
            setIndustry('')
            setLastName('')
            setTitle('')
            setPhone('')
            setNotes('')
            setSubmit({ kind: 'idle' })
        }
    }, [show])

    const profileLastName = initial.lastName ?? ''
    const lastNameIsMissing = profileLastName === ''
    const isBusy = submit.kind === 'submitting'

    const onSubmit = async (e: React.FormEvent) => {
        e.preventDefault()
        if (isBusy) return
        if (accountName.trim() === '') return
        if (lastNameIsMissing && lastName.trim() === '') return

        setSubmit({ kind: 'submitting' })
        try {
            await salesforceApi.export({
                accountName: accountName.trim(),
                industry: industry.trim() === '' ? null : industry.trim(),
                lastName: profileLastName !== '' ? profileLastName : lastName.trim(),
                notes: notes.trim() === '' ? null : notes.trim(),
                title: title.trim() === '' ? null : title.trim(),
                phone: phone.trim() === '' ? null : phone.trim(),
            })
            // Success: no Salesforce IDs surfaced to the user. The IDs
            // stay in the backend response so future technical code paths
            // (debug, idempotency, support tools) can still read them.
            setSubmit({ kind: 'success' })
        } catch (e: unknown) {
            const status = (e as { response?: { status?: number } }).response?.status
            const data = (e as { response?: { data?: SalesforceExportError } }).response?.data
            const message =
                (data?.error !== undefined && data.error !== '')
                    ? data.error
                    : (status === 401)
                        ? 'You need to sign in to export to Salesforce.'
                        : 'Salesforce export failed. Please retry.'
            setSubmit({ kind: 'error', message })
        }
    }

    return (
        // size="lg" ≈ 800px wide → fits the requested ~700–850px range.
        // centered keeps it visually balanced at any viewport height.
        // scrollable activates internal scroll only when content
        // overflows (typical on mobile in single-column mode).
        <Modal
            show={show}
            onHide={isBusy ? undefined : onClose}
            size="lg"
            centered
            scrollable
            aria-labelledby="salesforce-export-modal-title"
        >
            <Modal.Header closeButton={!isBusy}>
                <Modal.Title id="salesforce-export-modal-title">Export to Salesforce</Modal.Title>
            </Modal.Header>
            <Form onSubmit={onSubmit}>
                <Modal.Body>
                    {submit.kind === 'success' ? (
                        <Alert variant="success" className="mb-0">
                            Data exported to Salesforce successfully.
                        </Alert>
                    ) : (
                        <>
                            <p className="text-muted small mb-3">
                                We will create or update a Salesforce Account and Contact
                                for your profile. First name and email are taken from
                                your account.
                            </p>

                            {/*
                              Two real columns, each with its own vertical
                              stack. The outer <Row> puts both columns
                              side-by-side on desktop (lg+); on mobile /
                              tablet (xs=12) each Col occupies the full row
                              width and the two columns stack naturally.

                              The inner `d-flex flex-column gap-3` provides
                              uniform vertical spacing between fields within
                              each column. Each column has an independent
                              stack, so the <Form.Text> under Last name
                              pushes Email and Notes inside the right
                              column only — the left column (Company name,
                              Industry, Title, Phone) is unaffected and
                              stays aligned at the bottom.
                            */}
                            <Row className="gx-3">
                                <Col xs={12} lg={6}>
                                    <div className="d-flex flex-column gap-3">
                                        <Form.Group controlId="sf-account-name">
                                            <Form.Label>Company name *</Form.Label>
                                            <Form.Control
                                                value={accountName}
                                                onChange={(e) => setAccountName(e.target.value)}
                                                placeholder="e.g. Acme Inc"
                                                required
                                                disabled={isBusy}
                                                autoFocus
                                            />
                                        </Form.Group>
                                        <Form.Group controlId="sf-industry">
                                            <Form.Label>Industry</Form.Label>
                                            <Form.Control
                                                value={industry}
                                                onChange={(e) => setIndustry(e.target.value)}
                                                placeholder="e.g. Software"
                                                disabled={isBusy}
                                            />
                                        </Form.Group>
                                        <Form.Group controlId="sf-title">
                                            <Form.Label>Title</Form.Label>
                                            <Form.Control
                                                value={title}
                                                onChange={(e) => setTitle(e.target.value)}
                                                placeholder="e.g. Software Engineer"
                                                disabled={isBusy}
                                            />
                                        </Form.Group>
                                        <Form.Group controlId="sf-phone">
                                            <Form.Label>Phone</Form.Label>
                                            <Form.Control
                                                value={phone}
                                                onChange={(e) => setPhone(e.target.value)}
                                                placeholder="e.g. +995 555 123 456"
                                                disabled={isBusy}
                                            />
                                        </Form.Group>
                                    </div>
                                </Col>
                                <Col xs={12} lg={6}>
                                    <div className="d-flex flex-column gap-3">
                                        <Form.Group controlId="sf-first-name">
                                            <Form.Label>First name</Form.Label>
                                            <Form.Control
                                                value={initial.firstName ?? ''}
                                                readOnly
                                                plaintext
                                                aria-readonly="true"
                                            />
                                        </Form.Group>
                                        <Form.Group controlId="sf-last-name">
                                            <Form.Label>
                                                Last name {lastNameIsMissing && '*'}
                                            </Form.Label>
                                            {lastNameIsMissing ? (
                                                <Form.Control
                                                    value={lastName}
                                                    onChange={(e) => setLastName(e.target.value)}
                                                    placeholder="Your last name"
                                                    required
                                                    disabled={isBusy}
                                                />
                                            ) : (
                                                <Form.Control
                                                    value={profileLastName}
                                                    readOnly
                                                    plaintext
                                                    aria-readonly="true"
                                                />
                                            )}
                                        </Form.Group>
                                        <Form.Group controlId="sf-email">
                                            <Form.Label>Email</Form.Label>
                                            <Form.Control
                                                value={initial.email ?? ''}
                                                readOnly
                                                plaintext
                                                aria-readonly="true"
                                            />
                                        </Form.Group>
                                        <Form.Group controlId="sf-notes">
                                            <Form.Label>Notes</Form.Label>
                                            <Form.Control
                                                as="textarea"
                                                rows={3}
                                                value={notes}
                                                onChange={(e) => setNotes(e.target.value)}
                                                disabled={isBusy}
                                            />
                                        </Form.Group>
                                    </div>
                                </Col>
                            </Row>

                            {submit.kind === 'error' && (
                                <Alert variant="danger" className="mb-0 mt-3">
                                    {submit.message}
                                </Alert>
                            )}
                        </>
                    )}
                </Modal.Body>
                <Modal.Footer>
                    {submit.kind === 'success' ? (
                        <Button variant="primary" onClick={onClose}>
                            Close
                        </Button>
                    ) : (
                        <>
                            <Button
                                variant="secondary"
                                onClick={onClose}
                                disabled={isBusy}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant="primary"
                                disabled={isBusy}
                            >
                                {isBusy ? (
                                    <>
                                        <Spinner size="sm" animation="border" /> Exporting…
                                    </>
                                ) : (
                                    'Export'
                                )}
                            </Button>
                        </>
                    )}
                </Modal.Footer>
            </Form>
        </Modal>
    )
}
