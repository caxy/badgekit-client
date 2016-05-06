<?php

namespace Caxy\BadgeKit;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Command\Result;
use GuzzleHttp\Psr7;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class ServiceClient extends \GuzzleHttp\Command\ServiceClient
{
    /**
     * @var array
     */
    private $api;

    /**
     * BadgeKitClient constructor.
     *
     * @param ClientInterface $client
     */
    public function __construct(ClientInterface $client)
    {
        $this->api = json_decode(file_get_contents(__DIR__.'/../res/badgekit.json'), true);
        parent::__construct($client, [$this, 'commandToRequestTransformer'], [$this, 'responseToResultTransformer']);
    }

    /**
     * @param CommandInterface $command
     *
     * @return RequestInterface
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

        return new Psr7\Request($action['method'], $path, $headers, $body);
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
