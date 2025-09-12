<?php

namespace EskieGwapo\Multichain\Models;

use EskieGwapo\Multichain\Multichain;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BlockchainModel
{
    protected static string $stream;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(protected array $attributes = []) {}

    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __get(string $name): mixed
    {
        return $this->getAttribute($name);
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function create(array $attributes): static
    {
        if (count($attributes) <= 0) {
            throw new InvalidArgumentException('Attributes cannot be empty when creating a blockchain model.');
        }

        $mc = new Multichain;

        $stream = static::$stream;
        $uuid = (string) Str::uuid(); // use uuid4 for uniqueness

        $key = $uuid; // <-- just use uuid, not stream:uuid (avoid odd chars)

        $encoded_attributes = json_encode($attributes) ?: '';

        $mc->call('publish', [
            $stream,
            $key,
            bin2hex($encoded_attributes),
        ]);

        return new static(array_merge($attributes, ['id' => $key]));
    }

    /**
     * @return Collection<$this>
     */
    public static function all(int $count = 10): Collection
    {
        $mc = new Multichain;
        $items = $mc->call('liststreamitems', [static::$stream, true, $count, 0, false])['result'];

        $records = [];

        foreach ($items as $item) {
            $raw = $item['data']['hex'] ?? $item['data'] ?? null;
            $data = $raw ? json_decode(hex2bin((string) $raw), true) : [];

            $key = $item['keys'][0] ?? null;

            if (! $key) {
                continue; // skip items without keys
            }

            // If this key already exists, merge previous attributes with new one
            if (isset($records[$key])) {
                $data = array_merge($records[$key], $data);
            }

            $records[$key] = array_merge(
                is_array($data) ? $data : [],
                [
                    'id' => $key,
                    'txid' => $item['txid'] ?? null,
                ]
            );
        }

        // Convert each record into BlockchainModel instance and filter deleted ones
        return collect($records)
            ->map(fn ($attributes): static => new static($attributes))
            ->filter(fn ($model): bool => empty($model->getAttribute('deleted')))
            ->values();
    }

    public static function find(string $key): ?static
    {
        $mc = new Multichain;
        $items = $mc->call('liststreamkeyitems', [static::$stream, $key])['result'];

        if (empty($items)) {
            return null;
        }

        $latest = end($items);

        // MultiChain always returns hex in ['data']['hex']
        $raw = $latest['data']['hex'] ?? $latest['data'] ?? null;

        if (! $raw) {
            return null;
        }

        $data = json_decode(hex2bin((string) $raw), true);

        if (! empty($data['deleted'])) {
            return null;
        }

        return new static($data + ['id' => $key]);

    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return $this
     */
    public function update(array $attributes): static
    {
        if (count($attributes) <= 0) {
            throw new InvalidArgumentException('Attributes cannot be empty when creating a blockchain model.');
        }

        $mc = new Multichain;

        $this->attributes = array_merge($this->attributes, $attributes);

        $encoded_attributes = json_encode($this->attributes);
        if ($encoded_attributes === false) {
            $encoded_attributes = '';
        }

        $mc->call('publish', [
            static::$stream,
            $this->attributes['id'],
            bin2hex($encoded_attributes),
        ]);

        return $this;
    }

    public function delete(): static
    {
        $mc = new Multichain;

        $this->attributes['deleted'] = true;

        $encoded_attributes = json_encode($this->attributes);
        if ($encoded_attributes === false) {
            $encoded_attributes = '';
        }

        $mc->call('publish', [
            static::$stream,
            $this->attributes['id'],
            bin2hex($encoded_attributes),
        ]);

        return $this;
    }

    /**
     * @return Collection<$this>
     */
    public function history(): Collection
    {
        $mc = new Multichain;
        $items = $mc->call('liststreamkeyitems', [static::$stream, $this->attributes['id']])['result'];

        return collect($items)->map(function ($item): static {
            $raw = $item['data']['hex'] ?? $item['data'] ?? null;
            $data = $raw ? json_decode(hex2bin($raw), true) : [];

            return new static(array_merge(
                is_array($data) ? $data : [],
                [
                    'id' => $item['keys'][0] ?? null,
                    'txid' => $item['txid'] ?? null,
                ]
            ));
        });
    }
}
