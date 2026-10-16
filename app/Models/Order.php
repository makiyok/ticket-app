<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $event_id
 * @property OrderStatus $status
 * @property int $total_amount
 * @property string|null $stripe_checkout_session_id
 * @property string|null $payment_intent_id
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['event_id', 'total_amount'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * DB の既定値と同じ。作成直後のモデルでも status が null にならないようにする。
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => OrderStatus::Pending->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total_amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * 注文者（主催者ではない）
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isPending(): bool
    {
        return $this->status === OrderStatus::Pending;
    }

    public function isPaid(): bool
    {
        return $this->status === OrderStatus::Paid;
    }

    /**
     * 支払い待ちのときだけキャンセルにする。支払い済みの注文は壊さない。
     * キャンセルにすると残り枚数の計算から外れ、押さえていた枠が戻る。
     */
    public function cancelIfPending(): bool
    {
        $updated = static::query()
            ->whereKey($this->id)
            ->where('status', OrderStatus::Pending)
            ->update(['status' => OrderStatus::Cancelled]);

        $this->refresh();

        return $updated === 1;
    }
}
