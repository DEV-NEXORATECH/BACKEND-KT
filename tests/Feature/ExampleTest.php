<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_health_response_does_not_expose_environment_or_database_exception_details(): void
    {
        $response = $this->getJson('/api/health')->assertOk();

        $response->assertJsonStructure(['status', 'timestamp'])
            ->assertJsonMissing(['environment' => config('app.env')])
            ->assertJsonMissingPath('database');
    }
}
