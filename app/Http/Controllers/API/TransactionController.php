<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    public function __construct(
        private TransactionService $transactionService
    ) {
    }

    /**
     * Get the authenticated user's transaction history and balance.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        $transactions = Transaction::where('sender_id', $user->id)
            ->orWhere('receiver_id', $user->id)
            ->with(['sender:id,name,email', 'receiver:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'balance' => $user->balance,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Execute a new money transfer.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'receiver_id' => [
                    'required',
                    'integer',
                    'exists:users,id',
                    function ($attribute, $value, $fail) {
                        if ($value == Auth::id()) {
                            $fail('You cannot transfer money to yourself.');
                        }
                    },
                ],
                'amount' => [
                    'required',
                    'numeric',
                    'min:0.01',
                    function ($attribute, $value, $fail) {
                        $user = Auth::user();
                        $commissionFee = $value * 0.015;
                        $totalRequired = $value + $commissionFee;

                        if ($user->balance < $totalRequired) {
                            $fail('Insufficient balance. Required: '.number_format($totalRequired, 2).', Available: '.number_format($user->balance, 2));
                        }
                    },
                ],
            ]);

            $transaction = $this->transactionService->transfer(
                Auth::user(),
                $validated['receiver_id'],
                $validated['amount']
            );

            $transaction->load(['sender:id,name,email', 'receiver:id,name,email']);

            return response()->json([
                'message' => 'Transfer completed successfully.',
                'transaction' => $transaction,
                'balance' => Auth::user()->fresh()->balance,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while processing the transfer.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
