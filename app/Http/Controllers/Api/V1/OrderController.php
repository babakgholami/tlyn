<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Order;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use Illuminate\Support\Facades\DB;
use App\Services\OrderMatchingService;

class OrderController extends Controller
{
    public function index()
    {
        return OrderResource::collection(Order::where('status', 'open')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:buy,sell',
            'price' => 'required|numeric|min:1',
            'weight' => 'required|numeric|min:0.0001',
        ]);

        $user = auth()->user();

        DB::transaction(function () use ($data, $user) {
            if ($data['type'] === 'buy') {
                $requiredCash = $data['price'] * $data['weight'];
                if ($user->cash_balance < $requiredCash) {
                    abort(400, 'There is not enough cash to place an order.');
                }
                $user->cash_balance -= $requiredCash;
                $user->cash_locked += $requiredCash;
            } else {
                if ($user->gold_balance < $data['weight']) {
                    abort(400, 'There is not enough gold available to place an order.');
                }
                $user->gold_balance -= $data['weight'];
                $user->gold_locked += $data['weight'];
            }
            $user->save();

            $order = Order::create([
                'user_id' => $user->id,
                'type' => $data['type'],
                'price' => $data['price'],
                'weight' => $data['weight'],
                'remaining_weight' => $data['weight'],
                'status' => 'open',
            ]);

            OrderMatchingService::match($order);
        });

        return response()->json(['message' => 'Order successfully placed']);
    }


    public function cancel($id)
    {
        $user = auth()->user();
        $order = Order::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        if ($order->status !== 'open') {
            return response()->json(['message' => 'Only open orders can be canceled'], 400);
        }

        $order->update(['status' => 'canceled']);

        return response()->json(['message' => 'Order canceled']);
    }

    public function history()
    {
        $user = auth()->user();
        return Order::where('user_id', $user->id)->get();
    }

}
