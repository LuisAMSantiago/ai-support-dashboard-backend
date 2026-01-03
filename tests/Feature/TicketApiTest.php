<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginacao_retorna_10_items_quando_per_page_10(): void
    {
        $user = User::factory()->create();
        Ticket::factory()->count(30)->create(['created_by' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/tickets?per_page=10');

        $response->assertOk();
        $response->assertJsonCount(10, 'data');
        $response->assertJsonStructure([
            'data',
            'links',
            'meta',
        ]);
    }

    public function test_filtro_priority_high_retorna_high(): void
    {
        $user = User::factory()->create();
        Ticket::factory()->count(5)->highPriority()->create(['created_by' => $user->id]);
        Ticket::factory()->count(7)->lowPriority()->create(['created_by' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/tickets?priority=high');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        foreach ($data as $item) {
            $this->assertSame('high', $item['priority']);
        }
    }

    public function test_validacao_post_sem_title_retorna_422(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson('/api/tickets', [
            // 'title' => faltando
            'description' => 'Qualquer descrição',
            'priority' => 'low',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title']);
    }

    public function test_ordenacao_sort_created_at_desc_retorna_mais_recente_primeiro(): void
    {
        $user = User::factory()->create();
        $old = Ticket::factory()->create([
            'title' => 'Old',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
            'created_by' => $user->id,
        ]);

        $new = Ticket::factory()->create([
            'title' => 'New',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/tickets?sort=-created_at&per_page=10');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $firstId = $response->json('data.0.id');
        $this->assertSame($new->id, $firstId);
    }
}