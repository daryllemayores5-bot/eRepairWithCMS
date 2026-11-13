import type { Page } from './index';

export type WorkshopAccess = {
  tier: 'free' | 'pro' | 'premium';
  documents: boolean; templates: boolean; inventory: boolean; business_reports: boolean;
};
export type NoteTemplate = { id: number; title: string; body: string; updated_at: string };
export type WorkshopRepair = { id: number; reference: string; model: string; status: string; estimates_count: number; customer: { id: number; name: string } };
export type InventoryPart = { id: number; sku: string; name: string; quantity: number; reorder_level: number; unit_cost: string; active: boolean };
export type InventoryMovement = {
  id: number; kind: 'opening' | 'restock' | 'usage'; quantity: number; unit_cost: string; note: string | null; created_at: string;
  part: { id: number; name: string; sku: string }; repair: { id: number; reference: string } | null;
};
export type InventoryResponse = { data: Page<InventoryPart>; low_stock_count: number };
export type BusinessReport = {
  from: string; through: string; requests_received: number; completed_repairs: number;
  completed_approved_estimates: string; parts_used: number; parts_cost: string;
  low_stock_items: number; status_counts: Record<string, number>; scope: string;
};
