<?php
namespace App\Http\Controllers\Api\V1;

use App\Models\Transaction;
use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $transactions = Transaction::with(['buyOrder.user', 'sellOrder.user'])
            ->whereHas('buyOrder', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->orWhereHas('sellOrder', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->latest()
            ->paginate(10);

        return TransactionResource::collection($transactions);
    }
}
