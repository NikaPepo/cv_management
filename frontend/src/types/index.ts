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
}