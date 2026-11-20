<?php

namespace App;

use App\Models\Item;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockManager
{
    private const int QuantityScale = 10000;

    private const int MaximumQuantity = 999999999999999999;

    public function receive(
        Item $item,
        Location $location,
        mixed $quantity,
        ?User $user = null,
        ?string $reason = null,
        ?string $reference = null,
        ?DateTimeInterface $occurredAt = null,
        ?string $submissionKey = null,
    ): StockMovement {
        return $this->record('receipt', $item, $location, $quantity, $user, $reason, $reference, $occurredAt, $submissionKey);
    }

    public function issue(
        Item $item,
        Location $location,
        mixed $quantity,
        ?User $user = null,
        ?string $reason = null,
        ?string $reference = null,
        ?DateTimeInterface $occurredAt = null,
        ?string $submissionKey = null,
    ): StockMovement {
        return $this->record('issue', $item, $location, $quantity, $user, $reason, $reference, $occurredAt, $submissionKey);
    }

    public function adjust(
        Item $item,
        Location $location,
        mixed $quantity,
        ?User $user = null,
        ?string $reason = null,
        ?string $reference = null,
        ?DateTimeInterface $occurredAt = null,
        ?string $submissionKey = null,
    ): StockMovement {
        return $this->record('adjustment', $item, $location, $quantity, $user, $reason, $reference, $occurredAt, $submissionKey);
    }

    private function record(
        string $type,
        Item $item,
        Location $location,
        mixed $quantity,
        ?User $user,
        ?string $reason,
        ?string $reference,
        ?DateTimeInterface $occurredAt,
        ?string $submissionKey,
    ): StockMovement {
        $change = $this->parseQuantity($quantity);

        if ($change === 0 || ($type !== 'adjustment' && $change < 0)) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be non-zero and positive for receipts and issues.']);
        }

        $reason = $reason === null ? null : trim($reason);

        if ($type === 'adjustment' && ($reason === null || $reason === '')) {
            throw ValidationException::withMessages(['reason' => 'An adjustment requires a reason.']);
        }

        if ($reference !== null && mb_strlen($reference) > 255) {
            throw ValidationException::withMessages(['reference' => 'The reference must not exceed 255 characters.']);
        }

        if ($reason !== null && strlen($reason) > 65535) {
            throw ValidationException::withMessages(['reason' => 'The reason must not exceed 65535 bytes.']);
        }

        if ($user !== null && (! $user->exists || $user->getKey() === null)) {
            throw ValidationException::withMessages(['user_id' => 'The responsible user must exist.']);
        }

        if ($type === 'issue') {
            $change = -$change;
        }

        $occurredAt = $occurredAt === null ? CarbonImmutable::now('UTC') : CarbonImmutable::instance($occurredAt)->utc();

        if ($submissionKey !== null && ! Str::isUuid($submissionKey)) {
            throw ValidationException::withMessages(['submission_key' => 'Invalid submission. Please open a new stock form.']);
        }
        $identity = [
            'item_id' => $item->getKey(), 'location_id' => $location->getKey(),
            'user_id' => $user?->getKey(), 'type' => $type,
            'quantity_change' => $this->formatQuantity($change),
            'reason' => $reason === '' ? null : $reason, 'reference' => $reference,
        ];
        try {
            return DB::transaction(function () use ($type, $item, $location, $change, $user, $reason, $reference, $occurredAt, $submissionKey, $identity): StockMovement {
                $lockedItem = Item::withTrashed()->whereKey($item->getKey())->lockForUpdate()->first();
                $lockedLocation = Location::withTrashed()->whereKey($location->getKey())->lockForUpdate()->first();

                if ($submissionKey !== null && ($previous = $this->previousSubmission($submissionKey, $identity)) !== null) {
                    return $previous;
                }

                if ($lockedItem === null || $lockedItem->trashed() || ! $lockedItem->is_active || $lockedItem->tracking_type !== 'stock') {
                    throw ValidationException::withMessages(['item_id' => 'An active, unarchived stock item is required.']);
                }

                if ($lockedLocation === null || $lockedLocation->trashed() || ! $lockedLocation->is_active) {
                    throw ValidationException::withMessages(['location_id' => 'An active, unarchived location is required.']);
                }

                $balance = StockBalance::query()
                    ->where('item_id', $lockedItem->id)
                    ->where('location_id', $lockedLocation->id)
                    ->lockForUpdate()
                    ->first();

                $before = $balance === null ? 0 : $this->parseQuantity($balance->quantity);
                $after = $before + $change;

                if ($after < 0) {
                    throw ValidationException::withMessages(['quantity' => 'Insufficient stock at this location.']);
                }

                if ($after > self::MaximumQuantity) {
                    throw ValidationException::withMessages(['quantity' => 'The resulting balance exceeds the supported quantity range.']);
                }

                $balance ??= new StockBalance(['item_id' => $lockedItem->id, 'location_id' => $lockedLocation->id]);
                $balance->quantity = $this->formatQuantity($after);
                $balance->save();

                return StockMovement::create([
                    'submission_key' => $submissionKey,
                    'item_id' => $lockedItem->id,
                    'location_id' => $lockedLocation->id,
                    'user_id' => $user?->getKey(),
                    'type' => $type,
                    'quantity_change' => $this->formatQuantity($change),
                    'quantity_before' => $this->formatQuantity($before),
                    'quantity_after' => $this->formatQuantity($after),
                    'occurred_at' => $occurredAt,
                    'reason' => $reason === '' ? null : $reason,
                    'reference' => $reference,
                ]);
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            if ($submissionKey !== null && ($previous = $this->previousSubmission($submissionKey, $identity)) !== null) {
                return $previous;
            }
            throw $exception;
        }
    }

    /** @param array<string, int|string|null> $identity */
    private function previousSubmission(string $key, array $identity): ?StockMovement
    {
        $movement = StockMovement::where('submission_key', $key)->first();
        if ($movement === null) {
            return null;
        }
        foreach ($identity as $field => $value) {
            if ($movement->getAttribute($field) !== $value) {
                throw ValidationException::withMessages(['submission_key' => 'This submission was already used for different details. Open a new stock form.']);
            }
        }

        return $movement;
    }

    private function parseQuantity(mixed $quantity): int
    {
        if ((! is_string($quantity) && ! is_int($quantity))
            || ! preg_match('/\\A(-?)([0-9]{1,14})(?:\\.([0-9]{1,4}))?\\z/', (string) $quantity, $matches)) {
            throw ValidationException::withMessages(['quantity' => 'Use an integer or decimal string with up to 14 integer digits and 4 decimal places.']);
        }

        $scaled = (int) $matches[2] * self::QuantityScale
            + (int) str_pad($matches[3] ?? '', 4, '0');

        return $matches[1] === '-' ? -$scaled : $scaled;
    }

    private function formatQuantity(int $quantity): string
    {
        $absolute = abs($quantity);

        return ($quantity < 0 ? '-' : '').intdiv($absolute, self::QuantityScale)
            .'.'.str_pad((string) ($absolute % self::QuantityScale), 4, '0', STR_PAD_LEFT);
    }
}
