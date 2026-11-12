import axios from 'axios'
export function errorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const errors = error.response?.data?.errors as Record<string, string[]> | undefined
    if (errors) return Object.values(errors).flat().join(' ')
    if (error.response?.status === 419) return 'Your session expired. Refresh the page and try again.'
    if (error.response?.status === 429) return 'Too many requests. Please wait a minute and try again.'
    return (error.response?.data?.message as string) || 'Cannot reach the server. Check your connection and try again.'
  }
  return error instanceof Error ? error.message : 'Something went wrong. Please try again.'
}
