<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ServiceLocation
{
    private static ?array $locations = null;

    public function resolve(string $city, string $province, bool $online = true): ?array
    {
        $cityKey = $this->normalize($city);
        $provinceKey = $this->province($province);
        if ($cityKey === '' || $provinceKey === '') {
            return null;
        }
        self::$locations ??= json_decode(file_get_contents(resource_path('data/service-locations.json')), true, flags: JSON_THROW_ON_ERROR)['locations'];
        foreach (self::$locations as $location) {
            if ($this->normalize($location['city']) === $cityKey && $this->province($location['province']) === $provinceKey) {
                return [round($location['latitude'], 2), round($location['longitude'], 2)];
            }
        }
        if (! $online || ! config('services.geocoding.enabled')) {
            return null;
        }
        $key = 'service-location:v1:'.hash('sha256', $cityKey.'|'.$provinceKey);
        if (is_array($cached = Cache::get($key))) {
            return $cached ?: null;
        }
        try {
            $response = Http::acceptJson()->connectTimeout(2)->timeout(5)
                ->get(config('services.geocoding.url'), array_filter([
                    'name' => trim($city), 'countryCode' => 'PH', 'count' => 100,
                    'language' => 'en', 'apikey' => config('services.geocoding.key'),
                ]));
        } catch (ConnectionException) {
            return null;
        }
        if (! $response->successful() || ! is_array($response->json('results'))) {
            return null;
        }
        $matches = [];
        foreach ($response->json('results') as $result) {
            if (! is_array($result) || ($result['country_code'] ?? '') !== 'PH'
                || $this->normalize((string) ($result['name'] ?? '')) !== $cityKey
                || ! in_array($result['feature_code'] ?? '', ['PPLC', 'PPLA', 'PPLA2', 'PPLA3', 'PPLA4', 'PPL', 'ADM3'], true)
                || ! in_array($provinceKey, array_map(fn ($name) => $this->province((string) $name), [$result['admin1'] ?? '', $result['admin2'] ?? '']), true)
                || ! is_numeric($result['latitude'] ?? null) || ! is_numeric($result['longitude'] ?? null)) {
                continue;
            }
            $latitude = (float) $result['latitude'];
            $longitude = (float) $result['longitude'];
            if (is_finite($latitude) && is_finite($longitude) && $latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180) {
                $point = [round($latitude, 2), round($longitude, 2)];
                $matches[implode('|', $point)] = $point;
            }
        }
        $point = count($matches) === 1 ? array_values($matches)[0] : null;
        Cache::put($key, $point ?? [], $point ? now()->addDays(30) : now()->addMinutes(5));

        return $point;
    }

    private function normalize(string $name): string
    {
        $name = strtolower(Str::ascii(trim($name)));
        $name = preg_replace('/^(city|municipality|province) of\s+|\s+(city|province)$/', '', $name);

        return preg_replace('/[^a-z0-9]/', '', $name);
    }

    private function province(string $name): string
    {
        $name = $this->normalize($name);

        return in_array($name, ['ncr', 'nationalcapitalregion', 'metromanila'], true) ? 'metromanila' : $name;
    }
}
