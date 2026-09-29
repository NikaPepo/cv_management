export type DataType =
    | 'string'
    | 'text'
    | 'image'
    | 'numeric'
    | 'date'
    | 'period'
    | 'boolean'
    | 'one_of_many'

export interface AttributeCategory {
    id: number
    code: string
    name: string
    sortOrder: number
}

export interface AttributeOption {
    id: number
    value: string
    sortOrder: number
}

export interface AttributeDefinition {
    id: number
    name: string
    description: string
    dataType: DataType
    categoryId: number
    categoryName: string
    required: boolean
    options?: AttributeOption[]
}

export interface AttributeLookupResponse {
    prefix: AttributeDefinition[]
    byCategory: AttributeDefinition[]
    recent: AttributeDefinition[]
}

export type ProfileAttributeTypedValue =
    | { kind: 'string'; value: string | null }
    | { kind: 'text'; value: string | null }
    | { kind: 'numeric'; value: string | null }
    | { kind: 'date'; value: string | null }
    | { kind: 'period'; value: { start: string | null; end: string | null } }
    | { kind: 'boolean'; value: boolean | null }
    | { kind: 'image'; value: string | null }
    | { kind: 'one_of_many'; value: string | null }

export interface ProfileAttributeRow {
    attributeDefinitionId: number
    name: string
    dataType: DataType
    required: boolean
    empty: boolean
    version: number | null
    value: ProfileAttributeTypedValue['value']
}

export interface CurrentUser {
    id: number
    email: string
    isVerified: boolean
    roles: string[]
    /**
     * Server-derived flag. True when the user has a hashed password on
     * file and can therefore sign in via email + password. False for
     * OAuth-only accounts (Google / Facebook created the row, no
     * password was ever set).
     *
     * Drives the "Set password" prompt in Profile / Account settings:
     * the SPA never has to guess, and never has to send the email to
     * the backend just to decide which UI to render.
     */
    hasPassword: boolean
}