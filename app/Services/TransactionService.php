<?php

namespace App\Services;

use App\Events\TransactionCreated;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionService
{
    /**
     * Commission rate (1.5%)
     */
    private const COMMISSION_RATE = 0.015;

    /**
     * Execute a money transfer between users.
     *
     * @param  User  $sender
     * @param  int  $receiverId
     * @param  float  $amount
     * @return Transaction
     *
     * @throws \Exception
     */
    public function transfer(User $sender, int $receiverId, float $amount): Transaction
    {
        if ($sender->id === $receiverId) {
            throw new \InvalidArgumentException('Cannot transfer money to yourself.');
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Transfer amount must be greater than zero.');
        }

        $receiver = User::findOrFail($receiverId);
        $commissionFee = $amount * self::COMMISSION_RATE;
        $totalDebit = $amount + $commissionFee;

        return DB::transaction(function () use ($sender, $receiver, $amount, $commissionFee, $totalDebit) {
            $lockedSender = User::where('id', $sender->id)->lockForUpdate()->first();
            $lockedReceiver = User::where('id', $receiver->id)->lockForUpdate()->first();

            if ($lockedSender->balance < $totalDebit) {
                throw new \InvalidArgumentException('Insufficient balance. Required: '.number_format($totalDebit, 2).', Available: '.number_format($lockedSender->balance, 2));
            }

            $lockedSender->balance -= $totalDebit;
            $lockedSender->save();

            $lockedReceiver->balance += $amount;
            $lockedReceiver->save();

            $transaction = Transaction::create([
                'sender_id' => $lockedSender->id,
                'receiver_id' => $lockedReceiver->id,
                'amount' => $amount,
                'commission_fee' => $commissionFee,
                'status' => 'completed',
            ]);

            $transaction->load(['sender', 'receiver']);

            $lockedSender->refresh();
            $lockedReceiver->refresh();

            event(new TransactionCreated(
                $transaction,
                (float) $lockedSender->balance,
                (float) $lockedReceiver->balance
            ));

            return $transaction;
        });
    }
}

