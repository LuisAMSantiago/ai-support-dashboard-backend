<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_receives_401_for_ticket_endpoints(): void
    {
        $response = $this->getJson('/api/tickets');
        $response->assertStatus(401);

        $response = $this->postJson('/api/tickets', [
            'title' => 'Test',
            'description' => 'Desc',
        ]);
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_create_and_view_own_ticket(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $post = $this->postJson('/api/tickets', [
            'title' => 'My ticket',
            'description' => 'Ticket description',
            'priority' => 'low',
        ]);

        $post->assertStatus(201);
        $id = $post->json('data.id');

        $this->assertDatabaseHas('tickets', [
            'id' => $id,
            'created_by' => $user->id,
        ]);

        $show = $this->getJson("/api/tickets/{$id}");
        $show->assertOk();

        $index = $this->getJson('/api/tickets');
        $index->assertOk();
        $index->assertJsonCount(1, 'data');
    }

    public function test_other_user_cannot_view_update_or_delete_ticket(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $ticket = Ticket::factory()->create(['created_by' => $owner->id]);

        Sanctum::actingAs($other);

        $this->getJson("/api/tickets/{$ticket->id}")->assertStatus(403);

        $this->patchJson("/api/tickets/{$ticket->id}", ['title' => 'Changed'])->assertStatus(403);

        $this->deleteJson("/api/tickets/{$ticket->id}")->assertStatus(403);
    }
}
