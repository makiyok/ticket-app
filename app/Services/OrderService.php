<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\PurchaseException;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    /**
     * 支払い待ちの注文が枠を押さえておける時間（分）
     */
    public const int PENDING_MINUTES = 15;

    /**
     * 1回の注文で買える、券種ごとの枚数の上限
     */
    public const int MAX_QUANTITY = 10;

    /**
     * 支払い待ちの注文と明細を作る。金額は受け取らず、券種の現在の価格から決める。
     *
     * 枚数は画面から書き換えられる値なので、型も範囲もここで確認する。
     *
     * @param  array<array-key, mixed>  $quantities  券種の ID => 枚数
     *
     * @throws PurchaseException
     */
    public function createOrder(User $user, Event $event, array $quantities): Order
    {
        return DB::transaction(function () use ($user, $event, $quantities): Order {
            if (! $event->isPublished()) {
                throw new PurchaseException('このイベントは現在購入できません。');
            }

            if ($quantities === []) {
                throw new PurchaseException('券種と枚数を選んでください。');
            }

            $ticketTypes = $event->ticketTypes()->whereKey(array_keys($quantities))->get()->keyBy('id');

            $items = [];

            foreach ($quantities as $ticketTypeId => $quantity) {
                $ticketType = $ticketTypes->get($ticketTypeId);

                if ($ticketType === null) {
                    throw new PurchaseException('選択した券種が見つかりません。');
                }

                $quantity = $this->validQuantity($quantity);

                $this->ensurePurchasable($ticketType, $quantity);

                $items[] = [
                    'ticket_type_id' => $ticketType->id,
                    'quantity' => $quantity,
                    'unit_price' => $ticketType->price,
                ];
            }

            $order = $user->orders()->create([
                'event_id' => $event->id,
                'total_amount' => array_sum(array_map(
                    fn (array $item): int => $item['unit_price'] * $item['quantity'],
                    $items,
                )),
            ]);

            $order->items()->createMany($items);

            return $order;
        });
    }

    /**
     * 支払い待ちの注文を支払い済みにし、明細の枚数分のチケットを発行する。
     *
     * Webhook と完了ページが同時に来ても二重にならないよう、status が pending の行だけを更新する。
     * 更新できたほう（1件だけ）がチケットを発行する。
     *
     * @return bool 今回の呼び出しで支払い済みにしたら true、すでに処理済みなら false
     */
    public function markAsPaid(Order $order, ?string $paymentIntentId = null): bool
    {
        return DB::transaction(function () use ($order, $paymentIntentId): bool {
            $updated = Order::query()
                ->whereKey($order->id)
                ->where('status', OrderStatus::Pending)
                ->update([
                    'status' => OrderStatus::Paid,
                    'paid_at' => now(),
                    'payment_intent_id' => $paymentIntentId,
                ]);

            if ($updated === 0) {
                return false;
            }

            foreach ($order->items()->get() as $item) {
                for ($i = 0; $i < $item->quantity; $i++) {
                    $item->tickets()->create(['code' => (string) Str::uuid()]);
                }
            }

            $order->refresh();

            return true;
        });
    }

    /**
     * @throws PurchaseException
     */
    private function validQuantity(mixed $quantity): int
    {
        $isInteger = is_int($quantity) || (is_string($quantity) && ctype_digit($quantity));

        if (! $isInteger || (int) $quantity < 1 || (int) $quantity > self::MAX_QUANTITY) {
            throw new PurchaseException('枚数は1枚から'.self::MAX_QUANTITY.'枚の間で選んでください。');
        }

        return (int) $quantity;
    }

    /**
     * @throws PurchaseException
     */
    private function ensurePurchasable(TicketType $ticketType, int $quantity): void
    {
        if (! $ticketType->isOnSale()) {
            throw new PurchaseException("「{$ticketType->name}」は販売期間外です。");
        }

        $remaining = $ticketType->remaining();

        if ($quantity > $remaining) {
            throw new PurchaseException("「{$ticketType->name}」の残り枚数が足りません（残り{$remaining}枚）。");
        }
    }
}
