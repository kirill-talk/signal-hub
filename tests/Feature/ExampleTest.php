<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_view_welcome_page(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Signal-to-Noise Hub');
        $response->assertSee('Get Started');
    }

    public function test_authenticated_users_can_view_welcome_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
        $response->assertSee('Open Feed Hub');
    }
}
