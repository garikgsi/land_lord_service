<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AccessToken;
use App\Entity\PasswordReset;
use App\Entity\User;
use App\Entity\UserInvitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class TokenService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserView $view,
        #[Autowire('%env(int:ACCESS_TOKEN_TTL)%')]
        private int $ttl,
    ) {
    }

    /** @return array{accessToken:string,tokenType:string,expiresAt:string,user:array} */
    public function issue(User $user): array
    {
        $plainToken = bin2hex(random_bytes(32));
        $expires = new \DateTimeImmutable('+'.$this->ttl.' seconds');
        $this->em->persist(new AccessToken($user, $plainToken, $expires));
        $this->em->flush();

        return ['accessToken' => $plainToken, 'tokenType' => 'Bearer', 'expiresAt' => $expires->format(DATE_ATOM), 'user' => $this->view->present($user)];
    }

    public function revokeAll(User $user): void
    {
        foreach ([AccessToken::class, PasswordReset::class, UserInvitation::class] as $class) {
            $this->em->createQuery('DELETE FROM '.$class.' t WHERE t.user = :user')->setParameter('user', $user)->execute();
        }
    }
}
