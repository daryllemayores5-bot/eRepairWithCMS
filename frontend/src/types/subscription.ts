import type { Page, Technician } from './index';

export type SubscriptionTier = 'free' | 'pro' | 'premium';
export type SubscriptionTechnician = Technician & { is_pro?: boolean; is_premium?: boolean; subscription_tier?: SubscriptionTier };

export type SubscriptionPlan = {
  id: number; name: string; slug: SubscriptionTier; description: string;
  price: string; duration_days: number; features: string[]; is_active: boolean;
};
export type SubscriptionCycle = {
  id: number; status: 'pending' | 'active' | 'scheduled' | 'expired' | 'cancelled';
  starts_at: string | null; expires_at: string | null;
};
export type DemoPayment = {
  id: number; amount: string; plan_name: string; duration_days: number;
  transaction_reference: string; display_reference: string; status: 'pending' | 'approved' | 'rejected';
  is_demo: true; created_at: string; approved_at: string | null; rejected_at: string | null;
  subscription: SubscriptionCycle;
};
export type SubscriptionOverview = {
  is_demo: true; is_pro: boolean; current_plan: string; status: 'free' | 'active' | 'expired';
  is_premium?: boolean; current_plan_slug?: SubscriptionTier;
  scheduled_subscription?: (SubscriptionCycle & { plan: SubscriptionPlan }) | null;
  starts_at: string | null; expires_at: string | null; days_remaining: number;
  plans: SubscriptionPlan[]; pending_payment: DemoPayment | null; payments: Page<DemoPayment>;
};
export type SubscriptionAnalytics = {
  total_profile_views: number; total_customer_inquiries: number;
  months: { month: string; views: number; inquiries: number }[];
  recent_activity: { viewed_on: string; views: number }[];
  premium?: {
    range_days: number; from: string; through: string; views: number; inquiries: number;
    previous_period: { views: number; inquiries: number };
    daily: { date: string; views: number; inquiries: number }[];
  };
};
