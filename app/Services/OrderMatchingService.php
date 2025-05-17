<?php
namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderMatchingService
{
    public static function match(Order $order)
    {
        $otherSideType = ($order->type === 'buy') ? 'sell' : 'buy';

        $query = Order::where('type', $otherSideType)
            ->where('price', $order->price)
            ->where('status', 'open')
            ->orderBy('created_at');

        $query->lockForUpdate();

        $matchingOrders = $query->get();

        foreach ($matchingOrders as $matchingOrder) {
            $transactionWeight = min($order->remaining_weight, $matchingOrder->remaining_weight);

            if ($transactionWeight <= 0) continue;

            $totalValue = $transactionWeight * $order->price;
            $fee = self::calculateFee($transactionWeight, $totalValue);

            DB::transaction(function () use ($order, $matchingOrder, $transactionWeight, $totalValue, $fee) {
                // Update Orders
                $order->remaining_weight -= $transactionWeight;
                $matchingOrder->remaining_weight -= $transactionWeight;

                // Update Statuses
                if ($order->remaining_weight <= 0) $order->status = 'matched';
                if ($matchingOrder->remaining_weight <= 0) $matchingOrder->status = 'matched';

                $order->save();
                $matchingOrder->save();

                // Update Balances
                if ($order->type === 'buy') {
                    $buyer = $order->user;
                    $buy_order_id = $order->id;
                    $seller = $matchingOrder->user;
                    $sell_order_id = $matchingOrder->id;
                } else {
                    $buyer = $matchingOrder->user;
                    $buy_order_id = $matchingOrder->id;
                    $seller = $order->user;
                    $sell_order_id = $order->id;
                }

                $buyer->cash_locked -= $totalValue;
                $buyer->gold_balance += $transactionWeight;
                $buyer->save();

                $seller->gold_locked -= $transactionWeight;
                $seller->cash_balance += ($totalValue - $fee);
                $seller->save();

                // Create Transaction
                DB::table('transactions')->insert([
                    'buy_order_id' => $buy_order_id,
                    'sell_order_id' => $sell_order_id,
                    'weight' => $transactionWeight,
                    'price' => $order->price,
                    'fee' => $fee,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        }
    }

    private static function calculateFee($weight, $totalValue)
    {
        if ($weight <= 1) {
            $rate = 0.02;
        } elseif ($weight <= 10) {
            $rate = 0.015;
        } else {
            $rate = 0.01;
        }

        $fee = $totalValue * $rate;
        return max(500000, min(50000000, $fee));
    }
}
