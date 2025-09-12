<?php

namespace EskieGwapo\Multichain\Models;

use App\Multichain\Multichain;
use Illuminate\Support\Collection;

class BlockchainModel
{
    protected static string $stream;

    public function __construct(protected array $attributes = [])
    {
    }

    public function getAttribute($key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __get($name): mixed
    {
        return $this->getAttribute($name);
    }

    public function setAttribute($key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    public static function create(array $attributes): static
    {
        $mc = app(Multichain::class);
        $key = static::$stream.':'.uniqid();

        $mc->call('publish', [
            static::$stream,
            $key,
            bin2hex(json_encode($attributes)),
        ]);

        return new static($attributes + ['key' => $key]);
    }

    /**
     * @return Collection<$this>
     */
    public static function all(): Collection
    {
        $mc = app(Multichain::class);
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

    public static function find($key): ?static
    {
        $mc = app(Multichain::class);
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

    public function update(array $attributes): static
    {
        $mc = app(Multichain::class);

        $this->attributes = array_merge($this->attributes, $attributes);

        $mc->call('publish', [
            static::$stream,
            $this->attributes['key'],
            bin2hex(json_encode($this->attributes)),
        ]);

        return $this;
    }

    public function delete(): static
    {
        $mc = app(Multichain::class);

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
        $mc = app(Multichain::class);
        $items = $mc->call('liststreamkeyitems', [static::$stream, $this->attributes['key']])['result'];

        return collect($items)->map(function ($item) {
            $raw = $item['data']['hex'] ?? $item['data'] ?? null;

            return $raw ? json_decode(hex2bin($raw), true) : [];
        });
    }
}
