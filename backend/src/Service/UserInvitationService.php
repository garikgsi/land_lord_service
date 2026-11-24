<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ActivationInput;
use App\Entity\User;
use App\Entity\UserInvitation;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class UserInvitationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private TokenService $tokens,
        private MailerInterface $mailer,
        #[Autowire('%env(MAILER_DSN)%')]
        private string $mailerDsn,
        #[Autowire('%env(APP_FRONTEND_URL)%')]
        private string $frontendUrl,
        #[Autowire('%env(MAIL_FROM)%')]
        private string $mailFrom,
        #[Autowire('%env(int:USER_INVITATION_TTL)%')]
        private int $ttl,
    ) {
    }

    public function send(User $user): void
    {
        if ($user->isActive() || $user->isBlocked()) {
            throw new ConflictHttpException('Приглашение доступно только незаблокированному пользователю, ожидающему активации.');
        }
        if (str_starts_with($this->mailerDsn, 'null:')) {
            throw new ServiceUnavailableHttpException();
        }
        try {
            $this->em->wrapInTransaction(function () use ($user): void {
                $this->em->lock($user, LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($user);
                if ($user->isActive() || $user->isBlocked()) {
                    throw new ConflictHttpException('Пользователь уже активирован или заблокирован.');
                }
                $this->em->createQuery('DELETE FROM App\Entity\UserInvitation i WHERE i.user = :user')->setParameter('user', $user)->execute();
                $plain = bin2hex(random_bytes(32));
                $this->em->persist(new UserInvitation($user, $plain, new \DateTimeImmutable('+'.$this->ttl.' seconds')));
                $this->em->flush();
                $this->mailer->send((new TemplatedEmail())
                    ->from($this->mailFrom)->to($user->getEmail())
                    ->subject('Приглашение в Land Lord Service')
                    ->htmlTemplate('emails/user_invitation.html.twig')
                    ->context(['firstName' => $user->getFirstName(), 'invitationUrl' => rtrim($this->frontendUrl, '/').'/activate?token='.$plain, 'expiresHours' => (int) ceil($this->ttl / 3600)]));
            });
        } catch (TransportExceptionInterface) {
            throw new ServiceUnavailableHttpException();
        }
    }

    public function userFromToken(#[\SensitiveParameter] string $token): User
    {
        $invitation = 1 === preg_match('/\A[a-f0-9]{64}\z/', $token)
            ? $this->em->getRepository(UserInvitation::class)->findOneBy(['tokenHash' => hash('sha256', $token)]) : null;
        if (!$invitation instanceof UserInvitation || $invitation->isExpired() || $invitation->getUser()->isActive() || $invitation->getUser()->isBlocked()) {
            throw new BadRequestHttpException('Приглашение недействительно или срок его действия истёк. Обратитесь к администратору за новой ссылкой.');
        }

        return $invitation->getUser();
    }

    public function profile(#[\SensitiveParameter] string $token): array
    {
        $user = $this->userFromToken($token);

        return ['email' => $user->getEmail(), 'firstName' => $user->getFirstName(), 'lastName' => $user->getLastName(), 'phone' => $user->getPhone(), 'position' => $user->getPosition(), 'hasAvatar' => null !== $user->getAvatar()];
    }

    public function withPendingUser(#[\SensitiveParameter] string $token, callable $operation): mixed
    {
        return $this->em->wrapInTransaction(function () use ($token, $operation): mixed {
            $user = $this->userFromToken($token);
            $this->em->lock($user, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($user);

            return $operation($this->userFromToken($token));
        });
    }

    public function complete(ActivationInput $input): void
    {
        $this->withPendingUser($input->token, function (User $user) use ($input): void {
            $user->setFirstName($input->firstName);
            $user->setLastName($input->lastName);
            $user->setPhone($input->phone);
            $user->setPosition($input->position);
            $user->setPassword($this->hasher->hashPassword($user, $input->password));
            $user->setActive(true);
            $this->tokens->revokeAll($user);
        });
    }
}
