export function technicianSelection(value: unknown): number[] {
  if (typeof value !== 'string') return [];
  return [...new Set(value.split(',').filter(id => /^[1-9]\d*$/.test(id))
    .map(Number).filter(Number.isSafeInteger))].slice(0, 3);
}
