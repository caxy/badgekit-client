<?php

namespace Caxy\BadgeKit\Middleware;

use Namshi\JOSE\JWS;
use Psr\Http\Message\RequestInterface;

/**
 * Class JwtMiddleware.
 */
class JwtMiddleware
{
    /**
     * @var string
     */
    private $secret;

    /**
     * @var int
     */
    private $exp;

    public function __construct($secret, $exp = 60)
    {
        $this->secret = $secret;
        $this->exp = $exp;
    }

    public function __invoke(RequestInterface $request)
    {
        $payload = [
          'key' => 'master',
          'exp' => time() + $this->exp,
          'method' => $request->getMethod(),
          'path' => $request->getUri()->getPath(),
        ];

        if (in_array($request->getMethod(), ['PUT', 'POST'])) {
            $body = $request->getBody();
            $computedHash = \GuzzleHttp\Psr7\hash($body, 'sha256');
            $payload['body'] = [
              'alg' => 'sha256',
              'hash' => $computedHash,
            ];
        }

        $jws = new JWS([
          'typ' => 'JWT',
          'alg' => 'HS256',
        ]);
        $jws->setPayload($payload)->sign($this->secret);
        $token = $jws->getTokenString();

        return $request->withHeader('Authorization', 'JWT token="'.$token.'"');
    }
}
