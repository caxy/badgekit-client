<?php

namespace Caxy\BadgeKit;

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

    /**
     * JwtMiddleware constructor.
     *
     * @param string $secret
     * @param int $exp
     */
    public function __construct($secret, $exp = 60)
    {
        $this->secret = $secret;
        $this->exp = $exp;
    }

    /**
     * @param RequestInterface $request
     *
     * @return RequestInterface
     */
    public function __invoke(RequestInterface $request)
    {
        $uri = $request->getUri();
        $path = $uri->getPath();
        $path .= $uri->getQuery() != null ? '?'.$uri->getQuery() : '';

        $payload = [
          'key' => 'master',
          'exp' => time() + $this->exp,
          'method' => $request->getMethod(),
          'path' => $path,
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
