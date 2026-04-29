<?php

namespace Tests\Feature;

use App\Models\SourcingRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSourcingRequestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_all_sourcing_requests_with_customer_details(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create([
            'name' => 'Ayesha Khan',
            'email' => 'ayesha@example.com',
            'organization_name' => 'Northwind Traders',
        ]);

        $request = SourcingRequest::query()->create([
            'user_id' => $customer->id,
            'reference' => 'REQ-2026-001',
            'type' => 'custom',
            'status' => 'submitted',
            'title' => 'Industrial gloves',
            'details' => 'Need 500 pairs of industrial gloves.',
            'quantity' => 500,
        ]);

        $request->links()->create(['url' => 'https://example.com/gloves']);

        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/sourcing-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', 'REQ-2026-001')
            ->assertJsonPath('data.0.user.name', 'Ayesha Khan')
            ->assertJsonPath('data.0.user.email', 'ayesha@example.com')
            ->assertJsonPath('data.0.links.0', 'https://example.com/gloves');
    }

    public function test_admin_can_accept_or_reject_a_sourcing_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create();
        $sourcingRequest = SourcingRequest::query()->create([
            'user_id' => $customer->id,
            'reference' => 'REQ-2026-002',
            'type' => 'links',
            'status' => 'submitted',
            'title' => 'Medical masks',
            'details' => 'Need mask supplier options.',
            'quantity' => 1000,
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson("/api/admin/sourcing-requests/{$sourcingRequest->id}", [
                'status' => 'accepted',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.status_label', 'Accepted');

        $this->assertDatabaseHas('sourcing_requests', [
            'id' => $sourcingRequest->id,
            'status' => 'accepted',
        ]);
    }

    public function test_non_admin_cannot_access_admin_sourcing_request_routes(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/sourcing-requests')
            ->assertForbidden();
    }
}
