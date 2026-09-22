export interface ApiEnvelope<T> {
  data: T
  error: ApiError | null
  meta?: { pagination?: Pagination } & Record<string, unknown>
}

export interface ApiError {
  code: string
  message: string
  details?: Record<string, unknown> | null
}

export interface Pagination {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface Role {
  id: number
  name: string
  slug: string
  description?: string | null
  permissions?: string[]
}

export interface User {
  id: number
  name: string
  email: string
  role: Role | null
  status: 'active' | 'disabled'
  must_reset_password: boolean
  last_login_at: string | null
  last_login_ip: string | null
  created_at: string
}

export interface Bucket {
  name: string
  slug: string
  storage_path: string
  driver: string
  quota_bytes: number | null
  max_file_size_bytes: number | null
  allowed_extensions: string[] | null
  allowed_mime_types: string[] | null
  versioning_enabled: boolean
  public_access: boolean
  status: 'active' | 'archived'
  created_by?: string | null
  created_at: string
  updated_at: string
}

export interface BucketUsage {
  file_count: number
  total_bytes: number
  quota_bytes: number | null
}

export interface ObjectVersion {
  version_number: number
  size_bytes: number
  checksum_sha256: string
  is_current: boolean
  uploaded_by: string | null
  created_at: string
}

export interface StoredObject {
  type: 'file'
  key: string
  basename: string
  size_bytes: number
  mime_type: string | null
  checksum_sha256: string
  etag: string | null
  uploaded_by: string | null
  uploaded_via_token: string | null
  metadata: Record<string, unknown> | null
  versions: ObjectVersion[]
  created_at: string
  updated_at: string
}

export interface FolderEntry {
  type: 'folder'
  key: string
}

export type ListingEntry = StoredObject | FolderEntry

export interface TokenBucketScope {
  bucket: string
  path_prefix: string | null
  permissions: string[]
}

export interface ApiToken {
  id: number
  name: string
  token_prefix: string
  owner: string | null
  abilities: string[]
  status: 'active' | 'revoked'
  expires_at: string | null
  allowed_ips: string[] | null
  bucket_scopes: TokenBucketScope[]
  last_used_at: string | null
  last_used_ip: string | null
  created_at: string
}

export interface AuditLog {
  id: number
  actor_type: 'user' | 'token' | 'system'
  actor_id: number | null
  actor: string
  action: string
  bucket: string | null
  object_key: string | null
  ip_address: string | null
  status: 'success' | 'failure'
  metadata: Record<string, unknown> | null
  created_at: string
}

export interface DashboardMetrics {
  buckets: { total: number }
  storage: { total_bytes: number; file_count: number }
  users: { active: number; disabled: number }
  tokens: { active: number; revoked: number }
  activity: {
    uploads_last_24h: number
    downloads_last_24h: number
    failed_requests_last_24h: number
  }
  recent_uploads: Array<{
    id: number
    actor_type: string
    actor_id: number | null
    bucket_id: number | null
    object_key: string | null
    created_at: string
  }>
}

export const PERMISSION_KEYS = [
  'bucket:list',
  'bucket:read',
  'bucket:write',
  'bucket:delete',
  'object:list',
  'object:read',
  'object:write',
  'object:delete',
] as const

export type PermissionKey = (typeof PERMISSION_KEYS)[number]
