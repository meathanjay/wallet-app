<?php

namespace Database\Seeders;

use App\Models\Transaction;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Secure password for all seeded users (will be automatically hashed by User model)
        $securePassword = 'SecurePassword123!';

        // Create 5 users with initial balances (without 2FA for easier testing)
        $users = [
            User::factory()->withoutTwoFactor()->create([
                'name' => 'Alice Johnson',
                'email' => 'alice@example.com',
                'password' => $securePassword,
                'balance' => 5000.00,
            ]),
            User::factory()->withoutTwoFactor()->create([
                'name' => 'Bob Smith',
                'email' => 'bob@example.com',
                'password' => $securePassword,
                'balance' => 3000.00,
            ]),
            User::factory()->withoutTwoFactor()->create([
                'name' => 'Charlie Brown',
                'email' => 'charlie@example.com',
                'password' => $securePassword,
                'balance' => 2500.00,
            ]),
            User::factory()->withoutTwoFactor()->create([
                'name' => 'Diana Prince',
                'email' => 'diana@example.com',
                'password' => $securePassword,
                'balance' => 4000.00,
            ]),
            User::factory()->withoutTwoFactor()->create([
                'name' => 'Eve Williams',
                'email' => 'eve@example.com',
                'password' => $securePassword,
                'balance' => 3500.00,
            ]),
        ];

        // Create some transactions between users
        // Note: We'll manually adjust balances to reflect transactions

        // Transaction 1: Alice sends 500 to Bob
        $this->createTransaction($users[0], $users[1], 500.00);
        $users[0]->balance -= (500.00 + 7.50); // 500 + 1.5% commission
        $users[0]->save();
        $users[1]->balance += 500.00;
        $users[1]->save();

        // Transaction 2: Bob sends 200 to Charlie
        $this->createTransaction($users[1], $users[2], 200.00);
        $users[1]->balance -= (200.00 + 3.00);
        $users[1]->save();
        $users[2]->balance += 200.00;
        $users[2]->save();

        // Transaction 3: Diana sends 1000 to Alice
        $this->createTransaction($users[3], $users[0], 1000.00);
        $users[3]->balance -= (1000.00 + 15.00);
        $users[3]->save();
        $users[0]->balance += 1000.00;
        $users[0]->save();

        // Transaction 4: Charlie sends 150 to Eve
        $this->createTransaction($users[2], $users[4], 150.00);
        $users[2]->balance -= (150.00 + 2.25);
        $users[2]->save();
        $users[4]->balance += 150.00;
        $users[4]->save();

        // Transaction 5: Eve sends 300 to Bob
        $this->createTransaction($users[4], $users[1], 300.00);
        $users[4]->balance -= (300.00 + 4.50);
        $users[4]->save();
        $users[1]->balance += 300.00;
        $users[1]->save();

        // Transaction 6: Alice sends 250 to Diana
        $this->createTransaction($users[0], $users[3], 250.00);
        $users[0]->balance -= (250.00 + 3.75);
        $users[0]->save();
        $users[3]->balance += 250.00;
        $users[3]->save();

        // Transaction 7: Bob sends 100 to Diana
        $this->createTransaction($users[1], $users[3], 100.00);
        $users[1]->balance -= (100.00 + 1.50);
        $users[1]->save();
        $users[3]->balance += 100.00;
        $users[3]->save();

        // Transaction 8: Charlie sends 75 to Alice
        $this->createTransaction($users[2], $users[0], 75.00);
        $users[2]->balance -= (75.00 + 1.125);
        $users[2]->save();
        $users[0]->balance += 75.00;
        $users[0]->save();

        $this->command->info('Created 5 users with balances and 8 transactions!');
        $this->command->info('Password for all users: SecurePassword123!');
        $this->command->info('Final balances:');
        foreach ($users as $user) {
            $user->refresh();
            $this->command->info("  {$user->name}: $" . number_format($user->balance, 2));
        }
    }

    /**
     * Create a transaction between two users.
     */
    private function createTransaction(User $sender, User $receiver, float $amount): Transaction
    {
        $commissionFee = $amount * 0.015;

        return Transaction::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'amount' => $amount,
            'commission_fee' => $commissionFee,
            'status' => 'completed',
        ]);
    }
}
