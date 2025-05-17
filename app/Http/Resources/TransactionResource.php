<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'price' => (float) $this->price,
            'total' => (float) $this->amount * $this->price,
            'fee' => (float) $this->fee,
            'type' => $this->getUserRole(),
            'created_at' => $this->created_at,
            'counterparty' => $this->getCounterparty(),
        ];
    }

    private function getUserRole()
    {
        $user = auth()->user();
        return $this->buyOrder->user_id === $user->id ? 'خرید' : 'فروش';
    }

    private function getCounterparty()
    {
        $user = auth()->user();
        return $this->buyOrder->user_id === $user->id
            ? $this->sellOrder->user->name
            : $this->buyOrder->user->name;
    }
}
