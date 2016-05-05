<?php

namespace Caxy\BadgeKit;

use Caxy\BadgeKit\Middleware\JwtMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Result;
use GuzzleHttp\Command\ServiceClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class ClientFactory
{
    /**
     * @var string
     */
    private $base_uri;

    /**
     * @var string
     */
    private $secret;

    /**
     * @var int
     */
    private $exp;

    /**
     * @var array
     */
    private $api;

    /**
     * BadgeKitClient constructor.
     *
     * @param $base_uri
     * @param $secret
     * @param int $exp
     */
    public function __construct($base_uri, $secret, $exp = 60)
    {
        $this->base_uri = $base_uri;
        $this->secret = $secret;
        $this->exp = $exp;
    }

    /**
     * @return ServiceClient
     */
    public function createServiceClient()
    {
        $middleware = new JwtMiddleware($this->secret, $this->exp);

        $stack = HandlerStack::create();
        $stack->push(Middleware::mapRequest($middleware));

        $this->api = json_decode(file_get_contents(__DIR__.'/../res/badgekit.json'), true);

        $client = new Client(['base_uri' => $this->base_uri, 'handler' => $stack]);

        return new ServiceClient($client, [$this, 'commandToRequestTransformer'], [$this, 'responseToResultTransformer']);
    }

    /**
     * @param CommandInterface $command
     *
     * @return Request
     */
    public function commandToRequestTransformer(CommandInterface $command)
    {
        $actions = $this->api[$command->getName()];
        $eligible = [];
        foreach ($actions as $action) {
            foreach ($action['parameters'] as $parameter) {
                if (!$command->hasParam($parameter)) {
                    continue 2;
                }
            }
            $eligible[] = $action;
        }

        $action = $eligible[0];
        $path = \GuzzleHttp\uri_template($action['path'], $command->toArray());

        return new Request($action['method'], $path);
    }

    /**
     * @param ResponseInterface $response
     * @param RequestInterface  $request
     *
     * @return Result
     */
    public function responseToResultTransformer(ResponseInterface $response, RequestInterface $request)
    {
        $data = json_decode($response->getBody(), true);

        return new Result($data);
    }
}
