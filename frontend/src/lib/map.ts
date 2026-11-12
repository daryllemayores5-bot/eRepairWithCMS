import type { Technician } from '@/types';
export function hasMapLocation(technician: Technician): boolean {
  return Number.isFinite(technician.latitude) && Number.isFinite(technician.longitude) && technician.latitude! >= -90 && technician.latitude! <= 90 && technician.longitude! >= -180 && technician.longitude! <= 180;
}
