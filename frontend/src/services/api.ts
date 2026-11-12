import axios from 'axios'

// Session cookies and Laravel's XSRF cookie are the only SPA auth mechanism.
// No bearer-token persistence, localStorage, or API response persistence.
export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api/v1',
  withCredentials: true,
  withXSRFToken: true,
  timeout: 15000,
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
})

export async function initializeCsrf() {
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
}

export type HealthResponse = {
  data: { application: string; status: 'ok' | 'degraded'; database: 'connected' | 'unavailable' }
}

export async function getHealth(): Promise<HealthResponse['data']> {
  const response = await api.get<HealthResponse>('/health')
  return response.data.data
}
