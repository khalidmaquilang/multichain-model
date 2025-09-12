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

        /** @var string $stream */
        $stream = static::$stream;
        $uuid = Str::uuid7()->toString();

        $key = $stream.':'.$uuid;

        $encoded_attributes = json_encode($attributes);
        if ($encoded_attributes === false) {
            $encoded_attributes = '';
        }

        $mc->call('publish', [
            static::$stream,
            $key,
            bin2hex($encoded_attributes),
        ]);

        return new static($attributes + ['key' => $key]);
    }

    /**
     * @return Collection<$this>
     */
    public static function all(): Collection
    {
        $mc = new Multichain;
        $items = $mc->call('liststreamitems', [static::$stream])['result'];

        return collect($items)
            ->map(function ($item): static {
                $raw = $item['data']['hex'] ?? $item['data'] ?? null;
                $data = $raw ? json_decode(hex2bin($raw), true) : [];

                return new static($data + [
                    'key' => $item['key'] ?? null,
                    'txid' => $item['txid'] ?? null,
                ]);
            })
            ->filter(fn ($model): bool => empty($model->getAttribute('deleted')));
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

        return new static($data + ['key' => $key]);

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
            $this->attributes['key'],
            bin2hex(json_encode($encoded_attributes)),
        ]);

        return $this;
    }

    public function delete(): static
    {
        $mc = new Multichain;

        $this->attributes['deleted'] = true;

        $mc->call('publish', [
            static::$stream,
            $this->attributes['key'],
            bin2hex(json_encode($this->attributes)),
        ]);

        return $this;
    }

    /**
     * @return Collection<$this>
     */
    public function history(): Collection
    {
        $mc = new Multichain;
        $items = $mc->call('liststreamkeyitems', [static::$stream, $this->attributes['key']])['result'];

        return collect($items)->map(function ($item) {
            $raw = $item['data']['hex'] ?? $item['data'] ?? null;

            return $raw ? json_decode(hex2bin($raw), true) : [];
        });
    }
}
