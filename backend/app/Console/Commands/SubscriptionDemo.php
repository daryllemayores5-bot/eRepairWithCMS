<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\SubscriptionPlan;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SubscriptionDemo extends Command
{
    protected $signature = 'erepair:subscriptions-demo {action=setup : setup, expire or reset} {--technician= : Existing .test technician profile ID} {--reference= : Exact DEMO payment reference for expire} {--confirm : Required for resetting only command-generated sample cycles}';

    protected $description = 'Local/testing-only subscription samples and explicit DEMO expiration; never creates or deletes users';

    public function handle(SubscriptionService $service): int
    {
        if (! app()->environment(['local', 'testing']) || ! in_array(DB::connection()->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)) {
            $this->error('DEMO controls require local/testing and a loopback database.');

            return self::FAILURE;
        }
        $profile = TechnicianProfile::with('user')->find($this->option('technician'));
        if (! $profile || $profile->user->role !== 'technician' || ! str_ends_with($profile->user->email, '.test')) {
            $this->error('Specify an existing demonstration technician profile ID with a .test email. No users will be created.');

            return self::FAILURE;
        }
        $action = $this->argument('action');
        if ($action === 'reset') {
            if (! $this->option('confirm')) {
                $this->error('Use --confirm to remove only is_seeded_demo payment cycles for this technician.');

                return self::FAILURE;
            }
            DB::transaction(function () use ($profile) {
                $profile->newQuery()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
                $ids = $profile->demoPayments()->where('is_seeded_demo', true)->pluck('technician_subscription_id');
                $profile->subscriptions()->whereIn('id', $ids)->delete();
            });
            $this->info('Only command-generated DEMO cycles/payments removed. Users, repairs, views and manually requested payments preserved.');

            return self::SUCCESS;
        }
        if ($action === 'expire') {
            $payment = $profile->demoPayments()->where('transaction_reference', $this->option('reference'))->where('is_demo', true)->where('status', 'approved')->first();
            if (! $payment || $payment->subscription->status !== 'active') {
                $this->error('Specify the exact reference of an approved active DEMO cycle belonging to this technician.');

                return self::FAILURE;
            }
            $expired = DB::transaction(function () use ($profile, $payment) {
                $profile->newQuery()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
                $cycle = $payment->subscription()->lockForUpdate()->firstOrFail();
                if ($cycle->status !== 'active') {
                    return false;
                }
                // This deliberate local demo time jump keeps its approved payment.
                $cycle->update(['starts_at' => now()->subDays(31), 'expires_at' => now()->subSecond(), 'status' => 'expired']);
                ActivityLog::create(['actor_id' => $profile->user_id, 'action' => 'subscription.demo_expiration', 'subject_type' => 'technician_subscription', 'subject_id' => $cycle->id]);

                return true;
            });
            if (! $expired) {
                $this->error('This DEMO cycle was replaced or expired while the command was waiting. No history was changed.');

                return self::FAILURE;
            }
            $this->info('DEMO cycle expired. Historical approved revenue is unchanged. Renew through My Subscription.');

            return self::SUCCESS;
        }
        if ($action !== 'setup') {
            $this->error('Use setup, expire or reset.');

            return self::FAILURE;
        }
        $admin = User::where('role', 'admin')->where('super', true)->where('account_status', 'active')->first();
        if (! $admin) {
            $this->error('An existing active administrator is required.');

            return self::FAILURE;
        }
        $created = DB::transaction(function () use ($profile, $admin, $service) {
            $profile->newQuery()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            if ($profile->demoPayments()->where('is_seeded_demo', false)->exists()) {
                return false;
            }
            $price = SubscriptionPlan::where('slug', 'pro')->firstOrFail()->price;
            foreach (['expired', 'approved', 'rejected', 'pending'] as $index => $scenario) {
                $key = '00000000-0000-4000-8000-'.str_pad((string) ($index + 1), 12, '0', STR_PAD_LEFT);
                if ($profile->demoPayments()->where('idempotency_key', $key)->exists()) {
                    continue;
                }
                $payment = $service->request($profile->user, $key, $price, true);
                if ($scenario !== 'pending') {
                    $payment = $service->decide($admin, $payment, $scenario === 'rejected' ? 'rejected' : 'approved');
                }
                if ($scenario === 'expired') {
                    $start = now()->subDays(61);
                    $payment->subscription->update(['starts_at' => $start, 'expires_at' => now()->subDays(31), 'status' => 'expired', 'created_at' => $start]);
                    $payment->update(['created_at' => $start, 'approved_at' => $start]);
                }
            }

            return true;
        });
        if (! $created) {
            $this->error('Setup requires a demo technician without manually requested subscription payments. Use another existing .test technician.');

            return self::FAILURE;
        }
        $profile->demoPayments()->where('is_seeded_demo', true)->get()->each(fn ($payment) => $this->line('DEMO SETUP '.$payment->subscription->status.': '.$payment->transaction_reference));
        $this->info('Idempotent DEMO samples ready. Setup adds approved simulated revenue; use a Free technician separately for the upgrade scenario.');

        return self::SUCCESS;
    }
}
