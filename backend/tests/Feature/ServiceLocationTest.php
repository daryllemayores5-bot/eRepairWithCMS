<?php

namespace Tests\Feature;

use App\Services\ServiceLocation;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ServiceLocationTest extends TestCase
{
    public function test_bundled_locations_handle_city_aliases_accents_and_metro_manila_without_network(): void
    {
        Http::preventStrayRequests();
        $locations = app(ServiceLocation::class);
        $this->assertSame([14.28, 121.42], $locations->resolve(' Santa Cruz ', 'LAGUNA'));
        $this->assertSame([14.22, 121.17], $locations->resolve('Calamba City', 'Laguna'));
        $this->assertSame([14.17, 121.22], $locations->resolve('Los Banos', 'Laguna'));
        $this->assertSame([14.6, 120.98], $locations->resolve('Manila', 'NCR'));
        $this->assertSame([13.14, 121.44], $locations->resolve('Pola', 'Oriental Mindoro'));
        $this->assertNull($locations->resolve('Santa Cruz', 'Not Laguna', false));
    }

    private function locationResult(array $overrides = []): array
    {
        return array_replace(['name' => 'Liliw', 'country_code' => 'PH', 'admin1' => 'Calabarzon', 'admin2' => 'Laguna', 'feature_code' => 'PPLA3', 'latitude' => 14.13623, 'longitude' => 121.43485], $overrides);
    }

    public function test_online_lookup_checks_country_province_name_and_coordinates_then_caches_the_city(): void
    {
        Http::preventStrayRequests();
        Http::fake(['geocoding-api.open-meteo.com/*' => Http::response(['results' => [
            $this->locationResult(['country_code' => 'US']), $this->locationResult(['admin2' => 'Quezon']),
            $this->locationResult(['name' => 'Liliw Heights']), $this->locationResult(['latitude' => 200]), $this->locationResult(),
        ]])]);
        $locations = app(ServiceLocation::class);
        $this->assertSame([14.14, 121.43], $locations->resolve('Liliw', 'Laguna'));
        $this->assertSame([14.14, 121.43], $locations->resolve('Liliw', 'Laguna'));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['countryCode'] === 'PH' && $request['name'] === 'Liliw' && ! isset($request['email']));
    }

    public function test_ambiguous_results_are_not_guessed(): void
    {
        Http::fake(['*' => Http::response(['results' => [$this->locationResult(), $this->locationResult(['latitude' => 15.12])]])]);
        $this->assertNull(app(ServiceLocation::class)->resolve('Liliw', 'Laguna'));
    }

    public function test_connection_failure_and_bad_responses_return_an_unresolved_location(): void
    {
        Http::fake(['*' => Http::failedConnection()]);
        $this->assertNull(app(ServiceLocation::class)->resolve('Liliw', 'Laguna'));
        Http::fake(['*' => Http::response(['error' => true], 503)]);
        $this->assertNull(app(ServiceLocation::class)->resolve('Liliw', 'Laguna'));
        Http::fake(['*' => Http::response(['results' => 'invalid'])]);
        $this->assertNull(app(ServiceLocation::class)->resolve('Liliw', 'Laguna'));
    }
}
