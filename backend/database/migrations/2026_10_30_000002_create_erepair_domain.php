<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['device_types', 'device_brands', 'specializations'] as $name) {
            Schema::create($name, function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('slug')->unique();
                $t->text('description')->nullable();
                $t->boolean('active')->default(true);
                $t->timestampsTz();
            });
        }
        Schema::create('repair_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->foreignId('device_type_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('specialization_id')->nullable()->constrained()->restrictOnDelete();
            $t->boolean('active')->default(true);
            $t->timestampsTz();
        });
        Schema::create('technician_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $t->text('biography')->nullable();
            $t->unsignedSmallInteger('years_experience')->default(0);
            $t->string('city')->index();
            $t->string('province')->index();
            $t->decimal('latitude', 8, 5)->nullable();
            $t->decimal('longitude', 8, 5)->nullable();
            $t->string('photo_path')->nullable();
            $t->string('availability')->default('available')->index();
            $t->string('verification_status')->default('pending')->index();
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('verification_notes')->nullable();
            $t->timestampTz('verified_at')->nullable();
            $t->timestampsTz();
        });
        foreach (['technician_specializations' => 'specialization_id', 'technician_device_types' => 'device_type_id'] as $name => $column) {
            Schema::create($name, function (Blueprint $t) use ($column) {
                $t->id();
                $t->foreignId('technician_profile_id')->constrained()->cascadeOnDelete();
                $t->foreignId($column)->constrained()->restrictOnDelete();
                $t->unique(['technician_profile_id', $column]);
            });
        }
        Schema::create('technician_service_areas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('technician_profile_id')->constrained()->cascadeOnDelete();
            $t->string('city');
            $t->string('province');
            $t->unique(['technician_profile_id', 'city', 'province']);
        });
        Schema::create('repair_requests', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->unique();
            $t->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('technician_profile_id')->constrained()->restrictOnDelete();
            $t->foreignId('device_type_id')->constrained()->restrictOnDelete();
            $t->foreignId('device_brand_id')->constrained()->restrictOnDelete();
            $t->foreignId('repair_category_id')->constrained()->restrictOnDelete();
            $t->string('model');
            $t->text('serial_number')->nullable();
            $t->text('problem_description');
            $t->string('status')->default('pending');
            $t->timestampTz('completed_at')->nullable();
            $t->timestampsTz();
            $t->index(['customer_id', 'status', 'created_at']);
            $t->index(['technician_profile_id', 'status']);
        });
        Schema::create('repair_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('repair_request_id')->constrained()->cascadeOnDelete();
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->string('path');
            $t->string('original_name');
            $t->string('mime');
            $t->unsignedBigInteger('size');
            $t->timestampsTz();
        });
        Schema::create('repair_status_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('repair_request_id')->constrained()->cascadeOnDelete();
            $t->string('previous_status')->nullable();
            $t->string('new_status');
            $t->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $t->text('remarks')->nullable();
            $t->timestampsTz();
        });
        Schema::create('repair_estimates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('repair_request_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 12, 2);
            $t->string('currency', 3)->default('PHP');
            $t->text('description');
            $t->string('status')->default('pending');
            $t->timestampsTz();
        });
        Schema::create('appointments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('repair_request_id')->constrained()->cascadeOnDelete();
            $t->timestampTz('starts_at')->index();
            $t->timestampTz('ends_at');
            $t->text('service_location');
            $t->string('status')->default('proposed');
            $t->timestampsTz();
        });
        Schema::create('reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('repair_request_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('technician_profile_id')->constrained()->restrictOnDelete();
            $t->unsignedSmallInteger('rating');
            $t->text('body');
            $t->boolean('hidden')->default(false);
            $t->timestampsTz();
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestampTz('read_at')->nullable();
            $t->timestampsTz();
        });
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action')->index();
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('metadata')->nullable();
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        foreach (['activity_logs', 'notifications', 'reviews', 'appointments', 'repair_estimates', 'repair_status_histories', 'repair_attachments', 'repair_requests', 'technician_service_areas', 'technician_device_types', 'technician_specializations', 'technician_profiles', 'repair_categories', 'specializations', 'device_brands', 'device_types'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
