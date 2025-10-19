<?php

namespace EskieGwapo\Multichain\Traits;

use EskieGwapo\Multichain\Multichain;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

trait HasAuditChainTrait
{
    /**
     * Boot the trait to hook into Eloquent model events.
     */
    public static function bootHasAuditChainTrait(): void
    {
        // After a model is created
        static::created(function (Model $model) {
            $model->addHistory('created');
        });

        // After a model is updated
        static::updated(function (Model $model) {
            $model->addHistory('updated');
        });

        // After a model is deleted
        static::deleted(function (Model $model) {
            $model->addHistory('deleted');
        });
    }

    /**
     * Adds a history record to the model's history stream.
     * This is now a private method, called automatically by the events.
     *
     * @param  string  $action  The action performed (created, updated, deleted).
     * @return string|null The transaction ID of the history record.
     */
    protected function addHistory(string $action): ?string
    {
        $mc = app(Multichain::class);

        // The key in the history stream is the primary key of the Eloquent model.
        $key = $this->getKey();

        $payload = [
            'action' => $action,
            'user_responsible' => auth()->id(), // Gets the ID of the logged-in user
            'model_attributes' => $this->getDirtyAttributesForHistory(),
            'history_recorded_at' => now()->toIso8601String(),
        ];

        $encodedPayload = json_encode($payload) ?: '';

        $result = $mc->call('publish', [
            $this->getHistoryStreamName(),
            (string) $key,
            bin2hex($encodedPayload),
        ]);

        $hash = $result['result'] ?? null;

        // Update transaction_hash if the field exists in the model
        if (array_key_exists('transaction_hash', $this->getAttributes())) {
            $this->{$this->getTransactionHashKey()} = $hash;
            $this->saveQuietly();
        }

        return $hash;
    }

    /**
     * Fetches the complete history for this model instance.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function fetchHistory(): Collection
    {
        $mc = app(Multichain::class);
        $key = $this->getKey();

        $items = $mc->call('liststreamkeyitems', [$this->getHistoryStreamName(), $key])['result'];

        if (empty($items)) {
            return collect();
        }

        return collect($items)->map(function ($item) {
            $raw = $item['data']['hex'] ?? $item['data'] ?? null;
            $data = $raw ? json_decode(hex2bin((string)$raw), true) : [];

            return array_merge(
                is_array($data) ? $data : [],
                [
                    'txid' => $item['txid'],
                    'blocktime' => createFromTimestamp($item['blocktime']),
                ]
            );
        });
    }

    /**
     * Gets the attributes that have changed, or all attributes for creation.
     *
     * @return array<string, mixed>
     */
    protected function getDirtyAttributesForHistory(): array
    {
        // For a 'created' event, log all attributes. For 'updated', log only what changed.
        $attributes = $this->wasRecentlyCreated ? $this->getAttributes() : $this->getDirty();

        // Avoid logging sensitive information in the audit trail.
        return Arr::except($attributes, ['password', 'remember_token']);
    }

    /**
     * Gets the dedicated stream name for this model's history.
     */
    protected function getHistoryStreamName(): string
    {
        return $this->getTable().'_history';
    }

    abstract function getTransactionHashKey(): string;
}