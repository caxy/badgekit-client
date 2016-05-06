<?php

namespace Caxy\BadgeKit;

use Caxy\BadgeKit\Middleware\JwtMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Exception\CommandException;
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
        $cmpFunction = function ($a, $b) {
            if (count($a['parameters']) == count($b['parameters'])) {
                return 0;
            }

            return (count($a['parameters']) < count($b['parameters'])) ? 1 : -1;
        };
        foreach ($this->api as &$actions) {
            usort($actions, $cmpFunction);
        }

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
        $name = $command->getName();
        $actions = $this->api[$name];
        if (!isset($this->api[$name])) {
            throw new CommandException('Command not found', $command);
        }

        if (count($actions) > 1) {
            $eligible = [];
            foreach ($actions as $action) {
                foreach ($action['parameters'] as $parameter) {
                    if (!$command->hasParam($parameter)) {
                        continue 2;
                    }
                }
                $eligible[] = $action;
            }
        } else {
            $eligible = $actions;
        }

        if (empty($eligible)) {
            throw new CommandException('Missing parameters for command', $command);
        }

        $action = $eligible[0];
        $path = \GuzzleHttp\uri_template($action['path'], $command->toArray());

        $headers = [];
        $body = null;
        if ($command->hasParam('body')) {
            $headers = ['Content-Type' => 'application/json'];
            $body = \GuzzleHttp\json_encode($command['body']);
        }

        return new Request($action['method'], $path, $headers, $body);
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
