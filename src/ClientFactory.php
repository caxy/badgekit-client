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
        if (!isset($this->api[$name])) {
            throw new CommandException('Command not found', $command);
        }
        $action = $this->api[$name];

        $prefix = '';
        if (isset($action['public']) && $command->hasParam('public')) {
            $prefix = '/public';
        }

        $prefixes = [
            'system' => '/systems/{system}',
            'issuer' => '/issuers/{issuer}',
            'program' => '/programs/{program}',
        ];
        if (isset($action['admin_contexts'])) {
            $prefix .= implode('', array_intersect_key($prefixes, array_flip($this->api[$name]['admin_contexts']), $command->toArray()));
        }
        $path = \GuzzleHttp\uri_template($prefix.$action['path'], $command->toArray());

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
