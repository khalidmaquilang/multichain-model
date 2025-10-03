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
        $uuid = (string) Str::uuid7(); // use uuid4 for uniqueness

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
    public static function all(): Collection
    {
        $mc = new Multichain;
        $start = 0;
        $batchSize = 500; // fetch in chunks
        $hasMore = true;
        $records = [];

        while ($hasMore) {
            $items = $mc->call('liststreamitems', [static::$stream, true, $batchSize, $start, false])['result'];

            if (empty($items)) {
                break;
            }

            // reverse so newest → oldest
            foreach (array_reverse($items) as $item) {
                $raw = $item['data']['hex'] ?? $item['data'] ?? null;
                $data = $raw ? json_decode(hex2bin((string) $raw), true) : [];

                $key = $item['keys'][0] ?? null;
                if (! $key) {
                    continue;
                }

                if (! isset($records[$key])) {
                    // skip deleted
                    if (!empty($data['deleted'])) {
                        $records[$key] = null;
                        continue;
                    }

                    $records[$key] = array_merge(
                        is_array($data) ? $data : [],
                        [
                            'id'   => $key,
                            'txid' => $item['txid'] ?? null,
                        ]
                    );
                }
            }

            // increment to next batch
            $start += $batchSize;
            $hasMore = count($items) === $batchSize;
        }

        return collect(array_filter($records))
            ->map(fn ($attributes): static => new static($attributes))
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

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getStream(): string
    {
        return self::$stream ?? Str::snake(Str::pluralStudly(class_basename($this)));
    }
}
