<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PasswordReset;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class PasswordResetService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $users,
        private UserPasswordHasherInterface $hasher,
        private TokenService $tokens,
        private MailerInterface $mailer,
        #[Autowire('%env(MAILER_DSN)%')]
        private string $mailerDsn,
        #[Autowire('%env(APP_FRONTEND_URL)%')]
        private string $frontendUrl,
        #[Autowire('%env(MAIL_FROM)%')]
        private string $mailFrom,
        #[Autowire('%env(int:PASSWORD_RESET_TTL)%')]
        private int $ttl,
    ) {
    }

    public function request(string $email): void
    {
        if (str_starts_with($this->mailerDsn, 'null:')) {
            throw new ServiceUnavailableHttpException();
        }
        $user = $this->users->findOneBy(['email' => mb_strtolower(trim($email))]);
        if (!$user instanceof User || ($user->isBlocked() || !$user->isActive())) {
            return;
        }
        $plainToken = bin2hex(random_bytes(32));
        $reset = $this->em->wrapInTransaction(function () use ($user, $plainToken): PasswordReset {
            $this->em->lock($user, LockMode::PESSIMISTIC_WRITE);
            $this->em->createQuery('DELETE FROM App\Entity\PasswordReset r WHERE r.user = :user')->setParameter('user', $user)->execute();
            $reset = new PasswordReset($user, $plainToken, new \DateTimeImmutable('+'.$this->ttl.' seconds'));
            $this->em->persist($reset);

            return $reset;
        });
        try {
            $this->mailer->send((new TemplatedEmail())
                ->from($this->mailFrom)
                ->to($user->getEmail())
                ->subject('Восстановление доступа к Land Lord Service')
                ->htmlTemplate('emails/password_reset.html.twig')
                ->context(['resetUrl' => rtrim($this->frontendUrl, '/').'/#/reset-password?token='.rawurlencode($plainToken), 'expiresMinutes' => (int) ceil($this->ttl / 60)]));
        } catch (TransportExceptionInterface) {
            $this->em->remove($reset);
            $this->em->flush();
            throw new ServiceUnavailableHttpException();
        }
    }

    public function complete(#[\SensitiveParameter] string $token, #[\SensitiveParameter] string $password): void
    {
        $this->em->wrapInTransaction(function () use ($token, $password): void {
            $reset = $this->em->createQuery('SELECT r, u FROM App\Entity\PasswordReset r JOIN r.user u WHERE r.tokenHash = :hash')
                ->setParameter('hash', hash('sha256', $token))
                ->setLockMode(LockMode::PESSIMISTIC_WRITE)
                ->getOneOrNullResult();
            if (!$reset instanceof PasswordReset || $reset->isExpired() || ($reset->getUser()->isBlocked() || !$reset->getUser()->isActive())) {
                throw new BadRequestHttpException('Ссылка недействительна или срок её действия истёк.');
            }
            $this->changePassword($reset->getUser(), $password);
        });
    }

    public function changePassword(User $user, #[\SensitiveParameter] string $password): void
    {
        $this->em->wrapInTransaction(function () use ($user, $password): void {
            $user->setPassword($this->hasher->hashPassword($user, $password));
            $this->tokens->revokeAll($user);
        });
    }
}
