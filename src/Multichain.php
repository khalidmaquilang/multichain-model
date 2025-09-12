<?php

namespace EskieGwapo\Multichain;

use EskieGwapo\Multichain\Exceptions\MultichainConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class Multichain
{
    protected string $url;

    /**
     * @var array|string[]
     */
    protected array $auth;

    protected string $chain_name;

    public function __construct()
    {
        $host = config('multichain.host') ?? '127.0.0.1';
        $port = config('multichain.port') ?? 9538;
        $user = config('multichain.user') ?? 'multichainrpc';
        $pass = config('multichain.pass') ?? 'password';
        $chain = config('multichain.chain') ?? 'multichain';

        $this->url = 'http://'.$host.':'.$port;
        $this->auth = [$user, $pass];
        $this->chain_name = $chain;
    }

    public function call($method, $params = [])
    {
        try {
            $response = Http::withBasicAuth(...$this->auth)
                ->post($this->url, [
                    'method' => $method,
                    'params' => $params,
                    'id' => uniqid(),
                    'chain_name' => $this->chain_name,
                ]);
        } catch (Throwable $e) {
            throw new MultichainConnectionException("Failed to connect to MultiChain: {$e->getMessage()}", 0, $e);
        }

        $data = json_decode((string) $response->getBody(), true);

        if (isset($data['error']) && $data['error']) {
            throw new MultichainConnectionException('MultiChain RPC error: '.json_encode($data['error']));
        }

        return $data;
    }
}
