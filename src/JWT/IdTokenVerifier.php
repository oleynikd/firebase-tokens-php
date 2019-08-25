<?php

declare(strict_types=1);

namespace Kreait\Firebase\JWT;

use InvalidArgumentException;
use Kreait\Clock\SystemClock;
use Kreait\Firebase\JWT\Action\FetchGooglePublicKeys;
use Kreait\Firebase\JWT\Action\VerifyIdToken;
use Kreait\Firebase\JWT\Action\VerifyIdToken\Error\IdTokenVerificationFailed;
use Kreait\Firebase\JWT\Contract\Token;
use Kreait\Firebase\JWT\Keys\GooglePublicKeys;
use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface;

final class IdTokenVerifier
{
    /** @var VerifyIdToken\Handler */
    private $handler;

    public function __construct(VerifyIdToken\Handler $handler)
    {
        $this->handler = $handler;
    }

    public static function createWithProjectId(string $projectId): self
    {
        $clock = new SystemClock();
        $keys = new GooglePublicKeys(new FetchGooglePublicKeys\WithHandlerDiscovery($clock), $clock);
        $handler = new VerifyIdToken\WithHandlerDiscovery($projectId, $keys, $clock);

        return new self($handler);
    }

    public static function createWithProjectIdAndCache(string $projectId, $cache): self
    {
        $clock = new SystemClock();
        $keyHandler = new FetchGooglePublicKeys\WithHandlerDiscovery($clock);

        if ($cache instanceof CacheInterface) {
            $keyHandler = new FetchGooglePublicKeys\WithPsr16SimpleCache($keyHandler, $cache, $clock);
        } elseif ($cache instanceof CacheItemPoolInterface) {
            $keyHandler = new FetchGooglePublicKeys\WithPsr6Cache($keyHandler, $cache, $clock);
        } else {
            throw new InvalidArgumentException(sprintf('The cache must implement %s or %s', CacheInterface::class, CacheItemPoolInterface::class));
        }

        $keys = new GooglePublicKeys($keyHandler, $clock);
        $handler = new VerifyIdToken\WithHandlerDiscovery($projectId, $keys, $clock);

        return new self($handler);
    }

    /**
     * @throws IdTokenVerificationFailed
     */
    public function verifyIdToken(string $token): Token
    {
        return $this->handler->handle(VerifyIdToken::withToken($token));
    }

    /**
     * @throws InvalidArgumentException on invalid leeway
     * @throws IdTokenVerificationFailed
     */
    public function verifyIdTokenWithLeeway(string $token, int $leewayInSeconds): Token
    {
        return $this->handler->handle(VerifyIdToken::withToken($token)->withLeewayInSeconds($leewayInSeconds));
    }
}