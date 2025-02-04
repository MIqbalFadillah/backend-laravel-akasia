<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use Laravel\Passport\Passport;
use Tests\TestCase;
use App\Http\Resources\DebitCardResource;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

use function PHPUnit\Framework\assertJson;

class DebitCardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCards()
    {
        // get /debit-cards
        $debitCards = DebitCard::factory()->count(2)->create(['user_id' => $this->user->id]);

        $expectedData = DebitCardResource::collection($debitCards)->resolve();
        

        $response = $this->get('/api/debit-cards');
        // log(json_encode($expectedData));

        $response->assertStatus(200)
                 ->assertJsonFragment([
                     'number' => $debitCards->first()->number,
                     'type' => $debitCards->first()->type,
                 ]);
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        // get /debit-cards
        // Buat dua pengguna
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // log(json_encode($user2));

        // Buat kartu debit untuk pengguna kedua
        $debitCardsForUser2=DebitCard::factory()->count(2)->create([
            'user_id' => $user2->id,
        ]);

        // Log::info('User 1 ID: ' . $user1->id);
        // Log::info('User 2 ID: ' . $user2->id);
        // Log::info('Debit Cards for User 2: ' . $debitCardsForUser2->pluck('id')->toJson());

        // Autentikasi sebagai pengguna pertama
        Passport::actingAs($user1);

        // Panggil endpoint untuk mendapatkan daftar kartu debit
        $response = $this->get('/api/debit-cards/');

        // $response = $this->get('/api/debit-cards/' . $user2->id);

        // Debug respons untuk memastikan struktur JSON
        $response->dump();

        // Periksa bahwa respons memiliki status 200 dan tidak mengandung kartu debit
        // milik pengguna kedua
        $response->assertStatus(200)
                ->assertJsonCount(0); 

        foreach ($debitCardsForUser2 as $debitCard) {
            $response->assertJsonMissing([
                'id' => $debitCard->id,
                'number' => $debitCard->number,
                'type' => $debitCard->type,
            ]);
        }
    }
        

    public function testCustomerCanCreateADebitCard()
    {
        // post /debit-cards
        $data = [
            'type' => '1234567890123456',
            'number' => rand(1000000000000000, 9999999999999999),
        ];

        $response = $this->post('/api/debit-cards', $data);

        $response->assertStatus(201)
                ->assertJsonFragment([
                        'id' => $response['id'],
                        'number' => $response['number'],
                        'type' => $response['type'],
                        'expiration_date' => $response['expiration_date'],
                ]);    
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->get("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(200)
                 ->assertJsonFragment(['id' => $debitCard->id]);
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
        $otherUser = User::factory()->create();
        $debitCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->get("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(403);
    }

    public function testCustomerCanActivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id, 'disabled_at' => now()]);

        $response = $this->put("/api/debit-cards/{$debitCard->id}", ['is_active' => true]);

        $response->assertStatus(200);
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id, 'disabled_at' => null]);

        $response = $this->put("/api/debit-cards/{$debitCard->id}", ['is_active' => false]);

        $response->assertStatus(200);
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        // put api/debit-cards/{debitCard}
        $this->withoutMiddleware();

        $user = User::factory()->create();
        $debitCard = DebitCard::factory()->create(['user_id' => $user->id]);
    
        Passport::actingAs($user);
    
        $response = $this->put("/api/debit-cards/{$debitCard->id}", ['number' => '']);
    
        $response->assertStatus(422);
    }

    public function testCustomerCanDeleteADebitCard()
    {
        // delete api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->delete("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('debit_cards', ['id' => $debitCard->id]);
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        // delete api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);
        DebitCardTransaction::factory()->create(['debit_card_id' => $debitCard->id]);

        $response = $this->delete("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(403);
    }

    // Extra bonus for extra tests :)
}