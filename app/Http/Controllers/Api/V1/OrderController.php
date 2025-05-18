<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Order;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use Illuminate\Support\Facades\DB;
use App\Services\OrderMatchingService;


/**
 * @OA\Tag(
 *     name="Orders",
 *     description="مدیریت سفارشات خرید و فروش طلا"
 * )
 *
 * @OA\Schema(
 *     schema="Order",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="type", type="string", enum={"buy", "sell"}, example="buy"),
 *     @OA\Property(property="price", type="number", format="float", example=10000000),
 *     @OA\Property(property="weight", type="number", format="float", example=2.5),
 *     @OA\Property(property="remaining_weight", type="number", format="float", example=2.5),
 *     @OA\Property(property="status", type="string", enum={"open", "filled", "canceled"}, example="open"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class OrderController extends Controller
{

    /**
     * دریافت لیست سفارشات باز
     * @OA\Get(
     *     path="/api/v1/orders",
     *     tags={"Orders"},
     *     summary="دریافت لیست سفارشات باز",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="لیست سفارشات باز",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Order")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="عدم احراز هویت"
     *     )
     * )
     */
    public function index()
    {
        return OrderResource::collection(Order::where('status', 'open')->get());
    }

    /**
     * ثبت سفارش جدید
     * @OA\Post(
     *     path="/api/v1/orders",
     *     tags={"Orders"},
     *     summary="ثبت سفارش جدید",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type","price","weight"},
     *             @OA\Property(property="type", type="string", enum={"buy", "sell"}, example="buy"),
     *             @OA\Property(property="price", type="number", format="float", example=100000),
     *             @OA\Property(property="weight", type="number", format="float", example=2)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="سفارش با موفقیت ثبت شد",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Order successfully placed")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="خطا در ثبت سفارش",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="There is not enough cash to place an order.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="عدم احراز هویت"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="خطای اعتبارسنجی",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="price", type="array", @OA\Items(type="string", example="The price field is required."))
     *             )
     *         )
     *     )
     * )
     */
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


    /**
     * لغو سفارش
     * @OA\Delete(
     *     path="/api/v1/orders/{id}/cancel",
     *     tags={"Orders"},
     *     summary="لغو سفارش",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="شناسه سفارش",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="سفارش با موفقیت لغو شد",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Order canceled")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="خطا در لغو سفارش",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Only open orders can be canceled")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="عدم احراز هویت"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="سفارش یافت نشد"
     *     )
     * )
     */
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


    /**
     * تاریخچه سفارشات کاربر
     * @OA\Get(
     *     path="/api/v1/orders/history",
     *     tags={"Orders"},
     *     summary="تاریخچه سفارشات کاربر",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="لیست تاریخچه سفارشات",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Order")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="عدم احراز هویت"
     *     )
     * )
     */
    public function history()
    {
        $user = auth()->user();
        return Order::where('user_id', $user->id)->get();
    }

}
