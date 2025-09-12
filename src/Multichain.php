<?php

namespace EskieGwapo\Multichain;

use Illuminate\Support\Facades\Http;

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
        $response = Http::withBasicAuth(...$this->auth)
            ->post($this->url, [
                'method' => $method,
                'params' => $params,
                'id' => uniqid(),
                'chain_name' => $this->chain_name,
            ]);

        return $response->json();
    }
}
