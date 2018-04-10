<?php

/**
 * User: Qiujc
 * Date: 2018/3/27
 * Time: 17:23
 */

namespace qiu\kc;
use Hanson\Vbot\Console\QrCode;

class client
{
    private $client;

    public function __construct()
    {
        $this->client = new \swoole_client(SWOOLE_SOCK_TCP | SWOOLE_KEEP);
    }

    public function connect()
    {
        if (!$this->client->connect("0.0.0.0", 9501, 1)) {
            throw new \Exception(sprintf('Swoole Error: %s', $this->client->errCode));
        }
    }

    public function send($data)
    {
        if ($this->client->isConnected()) {
            if (!is_string($data)) {
                $data = json_encode($data);
            }

            return $this->client->send($data);
        } else {
            throw new \Exception('Swoole Server does not connected.');
        }
    }

    public function received()
    {
        return $this->client->recv();
    }

    public function close()
    {
        $this->client->close();
    }
}

$client = new client();
try {
    $client->connect();
    $client->send('child');
    $data = $client->received();
    var_dump($data);
} catch (\Exception $e) {
    echo $e;
}


