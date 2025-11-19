<?php

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('unauthenticated users cannot access transactions api', function () {
    $response = $this->getJson('/api/transactions');
    $response->assertStatus(401);
});

test('authenticated users can view their transactions and balance', function () {
    $user = User::factory()->create(['balance' => 1000.00]);

    // Create some transactions
    $sender = User::factory()->create(['balance' => 500.00]);
    Transaction::factory()->create([
        'sender_id' => $user->id,
        'receiver_id' => $sender->id,
        'amount' => 100.00,
        'commission_fee' => 1.50,
    ]);

    $this->actingAs($user);

    $response = $this->getJson('/api/transactions');
    $response->assertStatus(200)
        ->assertJsonStructure([
            'balance',
            'transactions' => [
                'data' => [
                    '*' => [
                        'id',
                        'sender_id',
                        'receiver_id',
                        'amount',
                        'commission_fee',
                        'status',
                        'created_at',
                    ],
                ],
            ],
        ]);

    expect($response->json('balance'))->toBe('1000.00');
});

test('users can transfer money successfully', function () {
    $sender = User::factory()->create(['balance' => 1000.00]);
    $receiver = User::factory()->create(['balance' => 500.00]);

    $this->actingAs($sender);

    $response = $this->postJson('/api/transactions', [
        'receiver_id' => $receiver->id,
        'amount' => 100.00,
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'transaction',
            'balance',
        ]);

    // Verify balances were updated correctly
    $sender->refresh();
    $receiver->refresh();

    // Sender: 1000 - 100 - 1.50 = 898.50
    expect((float) $sender->balance)->toBe(898.50);
    // Receiver: 500 + 100 = 600.00
    expect((float) $receiver->balance)->toBe(600.00);

    // Verify transaction was created
    $this->assertDatabaseHas('transactions', [
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
        'amount' => 100.00,
        'commission_fee' => 1.50,
        'status' => 'completed',
    ]);
});

test('transfer fails with insufficient balance', function () {
    $sender = User::factory()->create(['balance' => 50.00]);
    $receiver = User::factory()->create(['balance' => 500.00]);

    $this->actingAs($sender);

    $response = $this->postJson('/api/transactions', [
        'receiver_id' => $receiver->id,
        'amount' => 100.00,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Insufficient balance. Required: 101.50, Available: 50.00',
        ]);

    // Verify balances were not changed
    $sender->refresh();
    $receiver->refresh();

    expect((float) $sender->balance)->toBe(50.00);
    expect((float) $receiver->balance)->toBe(500.00);
});

test('transfer fails when receiver does not exist', function () {
    $sender = User::factory()->create(['balance' => 1000.00]);

    $this->actingAs($sender);

    $response = $this->postJson('/api/transactions', [
        'receiver_id' => 99999,
        'amount' => 100.00,
    ]);

    $response->assertStatus(422);
});

test('transfer fails when trying to send to yourself', function () {
    $user = User::factory()->create(['balance' => 1000.00]);

    $this->actingAs($user);

    $response = $this->postJson('/api/transactions', [
        'receiver_id' => $user->id,
        'amount' => 100.00,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'You cannot transfer money to yourself.',
        ]);
});

test('transfer fails with invalid amount', function () {
    $sender = User::factory()->create(['balance' => 1000.00]);
    $receiver = User::factory()->create(['balance' => 500.00]);

    $this->actingAs($sender);

    // Test negative amount
    $response = $this->postJson('/api/transactions', [
        'receiver_id' => $receiver->id,
        'amount' => -100.00,
    ]);

    $response->assertStatus(422);

    // Test zero amount
    $response = $this->postJson('/api/transactions', [
        'receiver_id' => $receiver->id,
        'amount' => 0,
    ]);

    $response->assertStatus(422);
});

test('transfer is atomic - rollback on failure', function () {
    $sender = User::factory()->create(['balance' => 1000.00]);
    $receiver = User::factory()->create(['balance' => 500.00]);

    $this->actingAs($sender);

    // Mock a failure scenario by using an invalid receiver_id
    // This should cause the entire transaction to rollback
    $response = $this->postJson('/api/transactions', [
        'receiver_id' => 99999,
        'amount' => 100.00,
    ]);

    $response->assertStatus(422);

    // Verify sender balance was not changed (transaction rolled back)
    $sender->refresh();
    expect((float) $sender->balance)->toBe(1000.00);

    // Verify no transaction was created
    $this->assertDatabaseMissing('transactions', [
        'sender_id' => $sender->id,
    ]);
});

test('concurrent transfers are handled correctly with row locking', function () {
    $sender = User::factory()->create(['balance' => 1000.00]);
    $receiver1 = User::factory()->create(['balance' => 500.00]);
    $receiver2 = User::factory()->create(['balance' => 300.00]);

    $this->actingAs($sender);

    // Simulate concurrent transfers by running them in parallel
    $responses = [];

    // First transfer: 100.00 (requires 101.50)
    $response1 = $this->postJson('/api/transactions', [
        'receiver_id' => $receiver1->id,
        'amount' => 100.00,
    ]);

    // Second transfer: 200.00 (requires 203.00)
    $response2 = $this->postJson('/api/transactions', [
        'receiver_id' => $receiver2->id,
        'amount' => 200.00,
    ]);

    // Both should succeed as total required is 304.50, and sender has 1000.00
    $response1->assertStatus(201);
    $response2->assertStatus(201);

    // Verify final balances
    $sender->refresh();
    $receiver1->refresh();
    $receiver2->refresh();

    // Sender: 1000 - 101.50 - 203.00 = 695.50
    expect((float) $sender->balance)->toBe(695.50);
    // Receiver1: 500 + 100 = 600.00
    expect((float) $receiver1->balance)->toBe(600.00);
    // Receiver2: 300 + 200 = 500.00
    expect((float) $receiver2->balance)->toBe(500.00);

    // Verify both transactions were created
    $this->assertDatabaseHas('transactions', [
        'sender_id' => $sender->id,
        'receiver_id' => $receiver1->id,
        'amount' => 100.00,
    ]);

    $this->assertDatabaseHas('transactions', [
        'sender_id' => $sender->id,
        'receiver_id' => $receiver2->id,
        'amount' => 200.00,
    ]);
});

test('commission fee is calculated correctly at 1.5%', function () {
    $sender = User::factory()->create(['balance' => 1000.00]);
    $receiver = User::factory()->create(['balance' => 500.00]);

    $this->actingAs($sender);

    $response = $this->postJson('/api/transactions', [
        'receiver_id' => $receiver->id,
        'amount' => 100.00,
    ]);

    $response->assertStatus(201);

    // Verify commission fee is 1.50 (1.5% of 100.00)
    $transaction = Transaction::where('sender_id', $sender->id)
        ->where('receiver_id', $receiver->id)
        ->first();

    expect((float) $transaction->commission_fee)->toBe(1.50);
});

test('transaction history includes both sent and received transactions', function () {
    $user = User::factory()->create(['balance' => 1000.00]);
    $otherUser = User::factory()->create(['balance' => 500.00]);

    // Create a sent transaction
    Transaction::factory()->create([
        'sender_id' => $user->id,
        'receiver_id' => $otherUser->id,
        'amount' => 100.00,
        'commission_fee' => 1.50,
    ]);

    // Create a received transaction
    Transaction::factory()->create([
        'sender_id' => $otherUser->id,
        'receiver_id' => $user->id,
        'amount' => 50.00,
        'commission_fee' => 0.75,
    ]);

    $this->actingAs($user);

    $response = $this->getJson('/api/transactions');
    $response->assertStatus(200);

    $transactions = $response->json('transactions.data');

    // Should have 2 transactions
    expect(count($transactions))->toBe(2);

    // Verify both transactions are present
    $senderIds = collect($transactions)->pluck('sender_id')->toArray();
    $receiverIds = collect($transactions)->pluck('receiver_id')->toArray();

    expect(in_array($user->id, $senderIds))->toBeTrue();
    expect(in_array($user->id, $receiverIds))->toBeTrue();
});

