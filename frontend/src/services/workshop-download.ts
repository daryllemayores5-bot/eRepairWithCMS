import { api } from '@/services/api';

export async function downloadWorkshopFile(path: string, filename: string, params?: Record<string, string>) {
  const response = await api.get<Blob>(path, { responseType: 'blob', params });
  const expected = filename.endsWith('.pdf') ? 'application/pdf' : 'text/csv';
  if (!String(response.headers['content-type'] ?? '').toLowerCase().includes(expected)) {
    throw new Error('The document could not be downloaded. Refresh your plan and try again.');
  }
  const url = URL.createObjectURL(response.data);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = filename.replace(/[^a-zA-Z0-9._-]/g, '_');
  document.body.append(anchor);
  anchor.click();
  anchor.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
