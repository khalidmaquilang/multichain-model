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
        $this->url = 'http://'.config('multichain.host').':'.config('multichain.port');
        $this->auth = [config('multichain.user'), config('multichain.pass')];
        $this->chain_name = config('multichain.chain');
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
