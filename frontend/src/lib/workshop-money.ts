export function formatWorkshopMoney(amount: string): string {
  const parts = amount.match(/^(\d+)(?:\.(\d{1,2}))?$/);
  if (!parts) return '—';
  return '₱' + BigInt(parts[1]).toLocaleString('en-PH') + '.' + (parts[2] ?? '').padEnd(2, '0');
}
