<?php

namespace Database\Seeders;

use App\Models\DeviceBrand;
use App\Models\DeviceType;
use App\Models\RepairCategory;
use App\Models\RepairRequest;
use App\Models\Review;
use App\Models\Specialization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() || strlen((string) env('DEMO_PASSWORD')) < 12) {
            throw new \RuntimeException('Demo seeding requires a non-production environment and DEMO_PASSWORD of at least 12 characters.');
        }
        if (User::where('email', 'admin@erepair.test')->exists()) {
            throw new \RuntimeException('Demo accounts already exist. Use a fresh development database.');
        }
        DB::transaction(function () {
            $password = env('DEMO_PASSWORD');
            $admin = new User(['name' => 'Demo Administrator', 'email' => 'admin@erepair.test', 'password' => $password]);
            $admin->role = 'admin';
            $admin->super = true;
            $admin->email_verified_at = now();
            $admin->save();
            app(\App\Services\CmsAccount::class)->provision($admin);
            $types = collect(['Laptop', 'Mobile Phone'])->map(fn ($name) => DeviceType::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]));
            $brands = collect(['Apple', 'Samsung', 'Acer', 'ASUS', 'Lenovo', 'HP', 'Dell', 'OPPO', 'Vivo', 'Xiaomi'])->map(fn ($name) => DeviceBrand::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]));
            $specs = collect(['Screen replacement', 'Battery replacement', 'Charging repair', 'Motherboard repair', 'Software troubleshooting', 'Data recovery'])->map(fn ($name) => Specialization::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'description' => 'Professional '.strtolower($name).' for laptops and mobile devices.']));
            $categories = $specs->map(fn ($s) => RepairCategory::firstOrCreate(['slug' => $s->slug], ['name' => $s->name, 'specialization_id' => $s->id]));
            $places = [['Santa Cruz', 'Laguna', 14.28, 121.42], ['Pagsanjan', 'Laguna', 14.27, 121.46], ['Cavinti', 'Laguna', 14.24, 121.51], ['Calamba', 'Laguna', 14.21, 121.17], ['San Pablo', 'Laguna', 14.07, 121.33], ['Los Baños', 'Laguna', 14.17, 121.24], ['Manila', 'Metro Manila', 14.60, 120.98]];
            $names = ['Miguel Santos', 'Ana Reyes', 'Paolo Dela Cruz', 'Carlo Mendoza', 'Jessa Garcia', 'Rafael Bautista', 'Mark Villanueva', 'Nicole Ramos', 'Adrian Flores', 'Trisha Navarro', 'Joshua Castillo', 'Camille Aquino', 'Luis Torres', 'Bea Santiago', 'Daniel Mercado'];
            $profiles = collect();
            foreach ($names as $i => $name) {
                [$city,$province,$lat,$lng] = $places[$i % count($places)];
                $u = new User(['name' => $name, 'email' => 'technician'.($i + 1).'@erepair.test', 'password' => $password]);
                $u->role = 'technician';
                $u->save();
                $p = $u->technicianProfile()->create(['biography' => 'DEMO PROFILE. I help customers in '.$city.' with careful device diagnosis, clear estimates, and practical repair advice. I specialize in screens, batteries, software issues and board-level troubleshooting.', 'years_experience' => 2 + $i % 11, 'city' => $city, 'province' => $province, 'latitude' => $lat, 'longitude' => $lng, 'availability' => $i % 5 === 4 ? 'busy' : 'available']);
                $p->verification_status = $i < 12 ? 'approved' : ($i === 14 ? 'rejected' : 'pending');
                $p->verified_by = $admin->id;
                $p->verified_at = $i < 12 ? now() : null;
                $p->save();
                $p->deviceTypes()->sync($i % 3 === 0 ? $types->pluck('id')->all() : [$types[$i % 2]->id]);
                $p->specializations()->sync($specs->pluck('id')->all());
                $p->serviceAreas()->create(['city' => $city, 'province' => $province]);
                $profiles->push($p);
            }
            $customers = ['Andrea Cruz', 'John Michael Lopez', 'Maria Liza Perez', 'Angelo Fernandez', 'Patricia Gonzales', 'Francis Lim', 'Sofia Diaz', 'Jerome Salazar', 'Alyssa Domingo', 'Christian Tan'];
            $statuses = ['pending', 'accepted', 'scheduled', 'diagnosing', 'awaiting_customer_approval', 'in_repair', 'ready_for_pickup', 'completed', 'canceled', 'rejected'];
            foreach ($customers as $i => $name) {
                $u = new User(['name' => $name, 'email' => 'customer'.($i + 1).'@erepair.test', 'password' => $password]);
                $u->save();
                foreach ([0, 1] as $job) {
                    $p = $profiles[($i + $job) % 12];
                    $device = $p->deviceTypes()->first();
                    $status = $statuses[($i + $job) % 10];
                    $r = new RepairRequest(['technician_profile_id' => $p->id, 'device_type_id' => $device->id, 'device_brand_id' => $brands[$i % $brands->count()]->id, 'repair_category_id' => $categories[$i % $categories->count()]->id, 'model' => $device->name === 'Laptop' ? 'Aspire / demo unit' : 'Galaxy / demo unit', 'problem_description' => 'DEMO REPAIR. The device has an intermittent fault. Please inspect the device and provide a written estimate before replacing parts.']);
                    $r->customer_id = $u->id;
                    $r->reference = 'DEMO-ER-'.str_pad((string) ($i * 2 + $job + 1), 4, '0', STR_PAD_LEFT);
                    $r->status = $status;
                    $r->completed_at = $status === 'completed' ? now()->subDay() : null;
                    $r->save();
                    $sequence = ['pending'];
                    if ($status === 'canceled' || $status === 'rejected') {
                        $sequence[] = $status;
                    } else {
                        foreach (['accepted', 'scheduled', 'diagnosing', 'awaiting_customer_approval', 'in_repair', 'ready_for_pickup', 'completed'] as $step) {
                            if (end($sequence) === $status) {
                                break;
                            }$sequence[] = $step;
                        }
                    }
                    $previous = null;
                    foreach ($sequence as $step) {
                        $r->history()->create(['previous_status' => $previous, 'new_status' => $step, 'updated_by' => in_array($step, ['pending', 'completed', 'canceled', 'in_repair']) ? $u->id : $p->user_id, 'remarks' => 'Demonstration status history.']);
                        $previous = $step;
                    }
                    $r->appointments()->create(['starts_at' => in_array($status, ['pending', 'accepted', 'scheduled']) ? now()->addDays($i + 2)->addHours($job * 3) : now()->subDays(3), 'ends_at' => in_array($status, ['pending', 'accepted', 'scheduled']) ? now()->addDays($i + 2)->addHours($job * 3 + 1) : now()->subDays(3)->addHour(), 'service_location' => $p->city.' town center (demo location)', 'status' => $status === 'pending' ? 'proposed' : (in_array($status, ['canceled', 'rejected']) ? 'canceled' : 'confirmed')]);
                    if (in_array($status, ['awaiting_customer_approval', 'in_repair', 'ready_for_pickup', 'completed'])) {
                        $r->estimates()->create(['amount' => 750 + $i * 250, 'description' => 'Demo estimate covering labor, diagnosis and replacement parts.', 'status' => $status === 'awaiting_customer_approval' ? 'pending' : 'approved']);
                    }
                    if ($status === 'completed') {
                        Review::create(['repair_request_id' => $r->id, 'customer_id' => $u->id, 'technician_profile_id' => $p->id, 'rating' => 4 + $job, 'body' => 'Demo review: clear explanation, helpful updates, and careful repair work.']);
                    }
                }
            }
        });
    }
}
