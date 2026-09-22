import { http } from './http'
import type { ApiEnvelope, ListingEntry, StoredObject } from '@/types/api'

export interface ListObjectsParams {
  prefix?: string
  name?: string
  mime_type?: string
  uploaded_by?: number
  from?: string
  to?: string
  page?: number
  per_page?: number
}

export async function listObjects(bucket: string, params: ListObjectsParams = {}) {
  const { data } = await http.get<ApiEnvelope<ListingEntry[]>>(
    `/buckets/${encodeURIComponent(bucket)}/objects`,
    { params },
  )
  return data
}

export async function getObjectMetadata(bucket: string, key: string) {
  const { data } = await http.get<ApiEnvelope<StoredObject>>(
    `/buckets/${encodeURIComponent(bucket)}/objects/${encodePath(key)}/metadata`,
  )
  return data.data
}

export async function uploadObject(
  bucket: string,
  key: string,
  file: File,
  onProgress?: (percent: number) => void,
) {
  const form = new FormData()
  form.append('key', key)
  form.append('file', file)

  const { data } = await http.post<ApiEnvelope<StoredObject>>(
    `/buckets/${encodeURIComponent(bucket)}/objects`,
    form,
    {
      onUploadProgress: (event) => {
        if (onProgress && event.total) {
          onProgress(Math.round((event.loaded / event.total) * 100))
        }
      },
    },
  )
  return data.data
}

export async function makeDirectory(bucket: string, prefix: string) {
  const key = prefix.endsWith('/') ? prefix : `${prefix}/`
  const { data } = await http.post<ApiEnvelope<StoredObject>>(
    `/buckets/${encodeURIComponent(bucket)}/objects`,
    { key },
  )
  return data.data
}

export async function deleteObject(bucket: string, key: string) {
  await http.delete(`/buckets/${encodeURIComponent(bucket)}/objects/${encodePath(key)}`)
}

export async function downloadObject(bucket: string, key: string): Promise<Blob> {
  const { data } = await http.get(`/buckets/${encodeURIComponent(bucket)}/objects/${encodePath(key)}`, {
    responseType: 'blob',
  })
  return data as Blob
}

export function triggerBrowserDownload(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

function encodePath(key: string): string {
  return key.split('/').map(encodeURIComponent).join('/')
}
