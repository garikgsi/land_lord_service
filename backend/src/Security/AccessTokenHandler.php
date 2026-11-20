<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\AccessToken;
use App\Repository\AccessTokenRepository;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final readonly class AccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(private AccessTokenRepository $tokens)
    {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $token = $this->tokens->findOneBy(['tokenHash' => hash('sha256', $accessToken)]);
        if (!$token instanceof AccessToken || $token->isExpired()) {
            throw new BadCredentialsException();
        }
        $user = $token->getUser();
        if ($user->isBlocked() || !$user->isActive()) {
            throw new BadCredentialsException();
        }

        return new UserBadge($user->getUserIdentifier(), static fn () => $user);
    }
}
