<?php

namespace Database\Seeders;

use App\Models\DeviceBrand;
use App\Models\DeviceType;
use App\Models\RepairCategory;
use App\Models\Specialization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Production reference data only. Never creates people, reviews or repair jobs. */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Laptop', 'Mobile Phone'] as $name) {
            DeviceType::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
        }
        foreach (['Apple', 'Samsung', 'Acer', 'ASUS', 'Lenovo', 'HP', 'Dell', 'OPPO', 'Vivo', 'Xiaomi'] as $name) {
            DeviceBrand::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
        }
        foreach (['Screen replacement', 'Battery replacement', 'Charging repair', 'Motherboard repair', 'Software troubleshooting', 'Data recovery'] as $name) {
            $specialization = Specialization::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'description' => 'Professional '.strtolower($name).' for laptops and mobile devices.']);
            RepairCategory::firstOrCreate(['slug' => $specialization->slug], ['name' => $name, 'specialization_id' => $specialization->id]);
        }
    }
}
