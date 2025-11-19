<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Get all users except the authenticated user.
     */
    public function index(): JsonResponse
    {
        $users = \App\Models\User::where('id', '!=', Auth::id())
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return response()->json([
            'users' => $users,
        ]);
    }

    /**
     * Validate if a user ID is valid for transfer.
     */
    public function validateUser(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
        ]);

        $userId = $request->input('user_id');
        $currentUserId = Auth::id();

        if ($userId == $currentUserId) {
            return response()->json([
                'valid' => false,
                'message' => 'You cannot transfer money to yourself.',
            ]);
        }

        $user = \App\Models\User::find($userId);

        if (!$user) {
            return response()->json([
                'valid' => false,
                'message' => 'User not found.',
            ]);
        }

        return response()->json([
            'valid' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Validate if an amount is transferable.
     */
    public function validateAmount(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $amount = $request->input('amount');
        $user = Auth::user();
        $commissionFee = $amount * 0.015;
        $totalRequired = $amount + $commissionFee;

        if ($user->balance < $totalRequired) {
            return response()->json([
                'valid' => false,
                'message' => 'Insufficient balance. Required: '.number_format($totalRequired, 2).', Available: '.number_format($user->balance, 2),
                'required' => $totalRequired,
                'available' => $user->balance,
            ]);
        }

        return response()->json([
            'valid' => true,
            'amount' => $amount,
            'commission_fee' => $commissionFee,
            'total_required' => $totalRequired,
        ]);
    }
}

