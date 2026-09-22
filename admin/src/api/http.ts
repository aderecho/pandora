import axios, { AxiosError } from 'axios'
import type { ApiError } from '@/types/api'

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api/v1'
const apiRootUrl = import.meta.env.VITE_API_ROOT_URL ?? 'http://localhost:8000'

export const http = axios.create({
  baseURL: apiBaseUrl,
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
})

/**
 * Sanctum's SPA guard requires an XSRF-TOKEN cookie before any
 * state-changing request; must be called once before login.
 */
export async function ensureCsrfCookie(): Promise<void> {
  await axios.get(`${apiRootUrl}/sanctum/csrf-cookie`, { withCredentials: true })
}

export class ApiRequestError extends Error {
  constructor(
    public readonly status: number,
    public readonly apiError: ApiError,
  ) {
    super(apiError.message)
  }
}

http.interceptors.response.use(
  (response) => response,
  (error: AxiosError<{ error?: ApiError }>) => {
    if (error.response) {
      const apiError = error.response.data?.error ?? {
        code: 'UNKNOWN_ERROR',
        message: error.message,
      }
      return Promise.reject(new ApiRequestError(error.response.status, apiError))
    }
    return Promise.reject(
      new ApiRequestError(0, { code: 'NETWORK_ERROR', message: 'Unable to reach the API.' }),
    )
  },
)
