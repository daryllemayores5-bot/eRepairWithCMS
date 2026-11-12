<?php

namespace Tests\Unit;

use App\Models\TechnicianProfile;
use Tests\TestCase;

class ServiceCoordinatesTest extends TestCase
{
    public function test_public_coordinates_require_a_valid_pair_and_preserve_zero(): void
    {
        foreach ([[null, 121], [14, null], [91, 121], [14, -181], [INF, 121]] as [$lat, $lng]) {
            $this->assertNull((new TechnicianProfile(['latitude' => $lat, 'longitude' => $lng]))->publicCoordinates());
        }
        $this->assertSame([0.0, 0.0], (new TechnicianProfile(['latitude' => 0, 'longitude' => 0]))->publicCoordinates());
        $this->assertSame([14.28, 121.42], (new TechnicianProfile(['latitude' => 14.28123, 'longitude' => 121.42123]))->publicCoordinates());
    }
}
