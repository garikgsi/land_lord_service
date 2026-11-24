<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AccessToken;
use App\Entity\PasswordReset;
use App\Entity\User;
use App\Entity\UserInvitation;
use App\Service\PasswordResetService;
use App\Service\UserInvitationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserAccessTest extends WebTestCase
{
    private KernelBrowser $client;
    private array $invitationMessages = [];
    private bool $failInvitationMail = false;
    private const PASSWORD = 'fixture-password-123';
    private static int $requestAddress = 0;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        static::getContainer()->get('cache.rate_limiter')->clear();
        $this->client->setServerParameter('REMOTE_ADDR', '192.0.2.'.(++self::$requestAddress));
        $connection = $this->em()->getConnection();
        if (!str_ends_with($connection->getDatabase(), '_test')) {
            throw new \LogicException('Auth fixtures require a separate test database.');
        }
        $connection->executeStatement('DELETE FROM users');
    }

    protected function tearDown(): void
    {
        $this->em()->getConnection()->executeStatement('DELETE FROM users');
        parent::tearDown();
    }

    public function testAdminCanListUsersWithoutQueryString(): void
    {
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $token = $this->login($admin->getEmail());
        $this->call('GET', '/api/admin/users', null, $token);
        self::assertSame([200, $admin->getEmail()], [$this->client->getResponse()->getStatusCode(), $this->body()['items'][0]['email'] ?? null]);
    }

    public function testAdministratorCanReadAUserDirectlyWithoutPrivateCredentials(): void
    {
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $staff = $this->user('staff@test.example', ['ROLE_TECHNICIAN']);
        $token = $this->login($admin->getEmail());
        $this->call('GET', '/api/admin/users/'.$staff->getId(), null, $token);
        $body = $this->body();
        self::assertSame([200, $staff->getId(), 'staff@test.example', false], [$this->client->getResponse()->getStatusCode(), $body['id'] ?? null, $body['email'] ?? null, isset($body['password']) || isset($body['tokenHash'])]);
    }

    public function testUserDetailRequiresAnAdministrator(): void
    {
        $staff = $this->user('staff@test.example', []);
        $this->call('GET', '/api/admin/users/'.$staff->getId());
        $anonymous = $this->client->getResponse()->getStatusCode();
        $token = $this->login($staff->getEmail());
        $this->call('GET', '/api/admin/users/'.$staff->getId(), null, $token);
        self::assertSame([401, 403], [$anonymous, $this->client->getResponse()->getStatusCode()]);
    }

    #[DataProvider('invalidUserProfiles')]
    public function testInvalidProfileIsRejectedWithFieldErrorsAndDoesNotChangeTheUser(string $field, string $value): void
    {
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $staff = $this->user('staff@test.example', []);
        $token = $this->login($admin->getEmail());
        $payload = ['email' => $staff->getEmail(), 'firstName' => 'Before', 'phone' => '+79991234567'];
        $payload[$field] = $value;
        $this->call('PUT', '/api/admin/users/'.$staff->getId(), $payload, $token);
        $status = $this->client->getResponse()->getStatusCode();
        $hasError = isset($this->body()['errors'][$field]);
        $this->call('GET', '/api/admin/users/'.$staff->getId(), null, $token);
        self::assertSame([422, true, 'staff@test.example', null], [$status, $hasError, $this->body()['email'] ?? null, $this->body()['phone'] ?? null]);
    }

    public static function invalidUserProfiles(): iterable
    {
        yield 'email' => ['email', 'invalid'];
        yield 'phone incomplete' => ['phone', '+7999'];
        yield 'phone invalid country' => ['phone', '+12345678901'];
        yield 'empty name' => ['firstName', ''];
        yield 'blank name' => ['firstName', '   '];
    }

    public function testLoginAndLogoutUseHashedRevocableBearerTokens(): void
    {
        $user = $this->user('accountant@test.example', ['ROLE_ACCOUNTANT', 'ROLE_DIRECTOR']);
        $token = $this->login($user->getEmail());
        $storedHash = $this->em()->getConnection()->fetchOne('SELECT tokenHash FROM access_tokens');
        $this->call('GET', '/api/auth/me', null, $token);
        $me = $this->body();
        $this->call('POST', '/api/auth/logout', null, $token);
        $logout = $this->client->getResponse()->getStatusCode();
        $this->call('GET', '/api/auth/me', null, $token);
        self::assertSame([hash('sha256', $token), ['ROLE_ACCOUNTANT', 'ROLE_DIRECTOR'], false, 204, 401], [$storedHash, $me['roles'] ?? null, array_key_exists('password', $me), $logout, $this->client->getResponse()->getStatusCode()]);
    }

    public function testAnonymousAndNonAdminCannotManageUsers(): void
    {
        $user = $this->user('staff@test.example', ['ROLE_TECHNICIAN']);
        $this->call('GET', '/api/admin/users');
        $anonymous = $this->client->getResponse()->getStatusCode();
        $token = $this->login($user->getEmail());
        $this->call('GET', '/api/admin/users', null, $token);
        self::assertSame([401, 403], [$anonymous, $this->client->getResponse()->getStatusCode()]);
    }

    public function testWrongPasswordAndBlockedAccountCannotLogIn(): void
    {
        $user = $this->user('blocked@test.example', ['ROLE_LAWYER'], true);
        $this->call('POST', '/api/auth/login', ['email' => $user->getEmail(), 'password' => self::PASSWORD]);
        $blocked = $this->client->getResponse()->getContent();
        $blockedStatus = $this->client->getResponse()->getStatusCode();
        $this->call('POST', '/api/auth/login', ['email' => 'unknown@test.example', 'password' => 'wrong']);
        self::assertSame([401, $blocked], [$blockedStatus, $this->client->getResponse()->getContent()]);
    }

    public function testExpiredAndInvalidTokensAreRejected(): void
    {
        $user = $this->user('expired@test.example', []);
        $plain = bin2hex(random_bytes(32));
        $this->em()->persist(new AccessToken($user, $plain, new \DateTimeImmutable('-1 minute')));
        $this->em()->flush();
        $this->call('GET', '/api/auth/me', null, $plain);
        $expired = $this->client->getResponse()->getStatusCode();
        $this->call('GET', '/api/auth/me', null, 'invalid-token');
        self::assertSame([401, 401], [$expired, $this->client->getResponse()->getStatusCode()]);
    }

    public function testAdminCreatesNormalisedUserWithCombinedRoles(): void
    {
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $token = $this->login($admin->getEmail());
        $this->call('POST', '/api/admin/users', ['email' => 'New.User@Test.Example', 'password' => self::PASSWORD, 'phone' => '+7 999 123-45-67', 'position' => 'Инженер', 'roles' => ['ROLE_TECHNICIAN', 'ROLE_DIRECTOR']], $token);
        $created = $this->body();
        $status = $this->client->getResponse()->getStatusCode();
        $user = $this->em()->getRepository(User::class)->find($created['id'] ?? 0);
        self::assertSame([201, 'new.user@test.example', ['ROLE_TECHNICIAN', 'ROLE_DIRECTOR'], 'Инженер', false, true], [$status, $created['email'] ?? null, $created['roles'] ?? null, $created['position'] ?? null, array_key_exists('password', $created), $user instanceof User && '' === $user->getPassword() && !$user->isActive()]);
    }

    public function testDuplicateEmailAndUnknownRoleAreRejected(): void
    {
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $token = $this->login($admin->getEmail());
        $this->call('POST', '/api/admin/users', ['email' => 'other@test.example', 'password' => self::PASSWORD, 'roles' => ['ROLE_UNKNOWN']], $token);
        $invalid = $this->client->getResponse()->getStatusCode();
        $this->call('POST', '/api/admin/users', ['email' => 'ADMIN@test.example', 'password' => self::PASSWORD], $token);
        self::assertSame([422, 409], [$invalid, $this->client->getResponse()->getStatusCode()]);
    }

    public function testBlockingRevokesAccessAndUnblockingDoesNotRestoreOldTokens(): void
    {
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $user = $this->user('staff@test.example', ['ROLE_ACCOUNTANT']);
        $adminToken = $this->login($admin->getEmail());
        $staffToken = $this->login($user->getEmail());
        $payload = ['email' => $user->getEmail(), 'roles' => ['ROLE_ACCOUNTANT'], 'blocked' => true];
        $this->call('PUT', '/api/admin/users/'.$user->getId(), $payload, $adminToken);
        $blocked = $this->client->getResponse()->getStatusCode();
        $this->call('GET', '/api/auth/me', null, $staffToken);
        $denied = $this->client->getResponse()->getStatusCode();
        $payload['blocked'] = false;
        $this->call('PUT', '/api/admin/users/'.$user->getId(), $payload, $adminToken);
        $this->call('GET', '/api/auth/me', null, $staffToken);
        self::assertSame([200, 401, 401], [$blocked, $denied, $this->client->getResponse()->getStatusCode()]);
    }

    public function testChangingEmailAndRolesInvalidatesSessions(): void
    {
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $user = $this->user('old@test.example', ['ROLE_LAWYER']);
        $adminToken = $this->login($admin->getEmail());
        $oldToken = $this->login($user->getEmail());
        $this->call('PUT', '/api/admin/users/'.$user->getId(), ['email' => 'changed@test.example', 'roles' => ['ROLE_DIRECTOR']], $adminToken);
        $updated = $this->body();
        $this->call('GET', '/api/auth/me', null, $oldToken);
        $revoked = $this->client->getResponse()->getStatusCode();
        $this->call('POST', '/api/auth/login', ['email' => 'changed@test.example', 'password' => self::PASSWORD]);
        self::assertSame(['changed@test.example', ['ROLE_DIRECTOR'], 401, 200], [$updated['email'] ?? null, $updated['roles'] ?? null, $revoked, $this->client->getResponse()->getStatusCode()]);
    }

    public function testLastActiveAdministratorCannotBeBlockedOrDemoted(): void
    {
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $token = $this->login($admin->getEmail());
        $this->call('PUT', '/api/admin/users/'.$admin->getId(), ['email' => $admin->getEmail(), 'roles' => [], 'blocked' => true], $token);
        self::assertSame(409, $this->client->getResponse()->getStatusCode());
    }

    public function testAdministratorCannotSetAnotherUsersPasswordDirectly(): void
    {
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $user = $this->user('staff@test.example', []);
        $adminToken = $this->login($admin->getEmail());
        $this->call('POST', '/api/admin/users/'.$user->getId().'/password', ['password' => 'changed-password-123'], $adminToken);
        $status = $this->client->getResponse()->getStatusCode();
        $this->call('POST', '/api/auth/login', ['email' => $user->getEmail(), 'password' => self::PASSWORD]);
        self::assertSame([404, 200], [$status, $this->client->getResponse()->getStatusCode()]);
    }

    public function testPasswordResetIsSingleUseAndRevokesAllSessions(): void
    {
        $user = $this->user('reset@test.example', []);
        $token = $this->login($user->getEmail());
        $user = $this->em()->find(User::class, $user->getId());
        $plain = bin2hex(random_bytes(32));
        $this->em()->persist(new PasswordReset($user, $plain, new \DateTimeImmutable('+1 hour')));
        $this->em()->flush();
        $payload = ['token' => $plain, 'password' => 'recovered-password-123'];
        $this->call('POST', '/api/auth/reset-password', $payload);
        $reset = $this->client->getResponse()->getStatusCode();
        $this->call('GET', '/api/auth/me', null, $token);
        $revoked = $this->client->getResponse()->getStatusCode();
        $this->call('POST', '/api/auth/reset-password', $payload);
        $reused = $this->client->getResponse()->getStatusCode();
        $this->call('POST', '/api/auth/login', ['email' => $user->getEmail(), 'password' => 'recovered-password-123']);
        self::assertSame([200, 401, 400, 200], [$reset, $revoked, $reused, $this->client->getResponse()->getStatusCode()]);
    }

    public function testExpiredPasswordResetCannotChangePassword(): void
    {
        $user = $this->user('expired-reset@test.example', []);
        $plain = bin2hex(random_bytes(32));
        $this->em()->persist(new PasswordReset($user, $plain, new \DateTimeImmutable('-1 minute')));
        $this->em()->flush();
        $this->call('POST', '/api/auth/reset-password', ['token' => $plain, 'password' => 'recovered-password-123']);
        self::assertSame(400, $this->client->getResponse()->getStatusCode());
    }

    public function testRecoveryReportsUnconfiguredSmtpWithoutAccountDisclosure(): void
    {
        $this->user('recovery@test.example', []);
        $this->call('POST', '/api/auth/forgot-password', ['email' => 'recovery@test.example']);
        $known = $this->client->getResponse()->getContent();
        $status = $this->client->getResponse()->getStatusCode();
        $this->call('POST', '/api/auth/forgot-password', ['email' => 'unknown@test.example']);
        self::assertSame([503, $known], [$status, $this->client->getResponse()->getContent()]);
    }

    public function testRecoveryCreatesHashedTokenAndMailLinkWithoutSendingExternalEmail(): void
    {
        $user = $this->user('mail@test.example', []);
        $mailer = new class implements MailerInterface {
            public ?RawMessage $message = null;

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->message = $message;
            }
        };
        $container = static::getContainer();
        $service = new PasswordResetService($this->em(), $container->get(\App\Repository\UserRepository::class), $container->get(UserPasswordHasherInterface::class), $container->get(\App\Service\TokenService::class), $mailer, 'smtp://localhost', 'https://landlord.example', 'no-reply@landlord.example', 3600);
        $service->request($user->getEmail());
        $context = $mailer->message?->getContext() ?? [];
        $url = $context['resetUrl'] ?? '';
        $plain = substr($url, strrpos($url, '=') + 1);
        self::assertSame([true, hash('sha256', $plain), ['mail@test.example']], [str_starts_with($url, 'https://landlord.example/reset-password?token='), $this->em()->getConnection()->fetchOne('SELECT tokenHash FROM password_resets'), array_map(static fn ($address) => $address->getAddress(), $mailer->message?->getTo() ?? [])]);
    }

    public function testRecoveryRateLimitsRequests(): void
    {
        for ($i = 0; $i < 4; ++$i) {
            $this->call('POST', '/api/auth/forgot-password', ['email' => 'rate-limit@test.example']);
        }
        self::assertSame(429, $this->client->getResponse()->getStatusCode());
    }

    public function testAdminCanUploadAvatarAndOtherUsersCannotReadIt(): void
    {
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $owner = $this->user('owner@test.example', []);
        $other = $this->user('other@test.example', []);
        $adminToken = $this->login($admin->getEmail());
        $ownerToken = $this->login($owner->getEmail());
        $otherToken = $this->login($other->getEmail());
        $path = tempnam(sys_get_temp_dir(), 'avatar');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a/0cAAAAASUVORK5CYII='));
        try {
            $this->client->request('POST', '/api/admin/users/'.$owner->getId().'/avatar', files: ['avatar' => new UploadedFile($path, 'avatar.png', 'image/png', test: true)], server: ['HTTP_AUTHORIZATION' => 'Bearer '.$adminToken]);
            $uploaded = $this->client->getResponse()->getStatusCode();
            $url = $this->body()['avatarUrl'] ?? '/invalid';
            $this->call('GET', $url, null, $ownerToken);
            $own = $this->client->getResponse()->getStatusCode();
            $this->call('GET', $url, null, $otherToken);
            $denied = $this->client->getResponse()->getStatusCode();
            $this->call('DELETE', '/api/admin/users/'.$owner->getId().'/avatar', null, $adminToken);
            self::assertSame([200, 200, 403, 204], [$uploaded, $own, $denied, $this->client->getResponse()->getStatusCode()]);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testUserListSearchAndBlockedFilter(): void
    {
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $this->user('active@test.example', ['ROLE_TECHNICIAN']);
        $this->user('blocked@test.example', ['ROLE_ACCOUNTANT'], true);
        $token = $this->login($admin->getEmail());
        $this->call('GET', '/api/admin/users?state=blocked&query=BLOCKED&page=1', null, $token);
        self::assertSame([200, 1, 'blocked@test.example'], [$this->client->getResponse()->getStatusCode(), $this->body()['total'] ?? null, $this->body()['items'][0]['email'] ?? null]);
    }

    public function testLoginIsRateLimited(): void
    {
        for ($i = 0; $i < 6; ++$i) {
            $this->call('POST', '/api/auth/login', ['email' => 'throttled@test.example', 'password' => 'invalid']);
        }
        self::assertSame(429, $this->client->getResponse()->getStatusCode());
    }

    public function testAvatarRejectsExecutableContent(): void
    {
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $token = $this->login($admin->getEmail());
        $path = tempnam(sys_get_temp_dir(), 'invalid-avatar');
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>');
        try {
            $this->client->request('POST', '/api/admin/users/'.$admin->getId().'/avatar', files: ['avatar' => new UploadedFile($path, 'image.png', 'image/png', test: true)], server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
            self::assertSame(422, $this->client->getResponse()->getStatusCode());
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testUserNamesAreSavedReturnedAndEditableWithoutRevokingSession(): void
    {
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $adminToken = $this->login($admin->getEmail());
        $named = $this->user('named@test.example', []);
        $this->call('PUT', '/api/admin/users/'.$named->getId(), ['email' => 'named@test.example', 'firstName' => ' Иван ', 'lastName' => ' Петров '], $adminToken);
        $created = $this->body();
        $userToken = $this->login('named@test.example');
        $this->call('PUT', '/api/admin/users/'.$created['id'], ['email' => 'named@test.example', 'firstName' => 'Иван', 'lastName' => 'Сидоров'], $adminToken);
        $this->em()->clear();
        $this->call('GET', '/api/auth/me', null, $userToken);
        self::assertSame(['Иван', 'Петров', 200, 'Иван', 'Сидоров'], [$created['firstName'] ?? null, $created['lastName'] ?? null, $this->client->getResponse()->getStatusCode(), $this->body()['firstName'] ?? null, $this->body()['lastName'] ?? null]);
    }

    public function testFirstNameIsRequiredAndOtherProfileFieldsAreOptional(): void
    {
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $token = $this->login($admin->getEmail());
        $this->call('POST', '/api/admin/users', ['email' => 'missing-name@test.example', 'firstName' => null], $token);
        $missingName = $this->client->getResponse()->getStatusCode();
        $this->call('POST', '/api/admin/users', ['email' => 'optional@test.example', 'firstName' => 'Анна'], $token);
        $created = $this->body();
        $this->call('POST', '/api/admin/users', ['email' => 'invalid-name@test.example', 'password' => self::PASSWORD, 'firstName' => str_repeat('a', 101)], $token);
        self::assertSame([422, 'Анна', null, 422], [$missingName, $created['firstName'] ?? null, $created['lastName'] ?? null, $this->client->getResponse()->getStatusCode()]);
    }

    public function testAdministratorCreatesPendingUserWithoutChoosingPassword(): void
    {
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $token = $this->login($admin->getEmail());
        $this->call('POST', '/api/admin/users', ['email' => 'invited@test.example', 'firstName' => 'Анна'], $token);
        $body = $this->body();
        self::assertSame([201, 'Анна', false, false], [$this->client->getResponse()->getStatusCode(), $body['firstName'] ?? null, $body['active'] ?? null, $body['invitationSent'] ?? null]);
    }

    public function testAdministratorCannotEditAnotherAdministrator(): void
    {
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $other = $this->user('other-admin@test.example', ['ROLE_ADMIN']);
        $token = $this->login($admin->getEmail());
        $this->call('PUT', '/api/admin/users/'.$other->getId(), ['email' => 'changed@test.example', 'firstName' => 'Другое имя', 'roles' => ['ROLE_ADMIN']], $token);
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testInvitationActivatesOnceAndCannotChangeEmailOrRoles(): void
    {
        $this->captureInvitations();
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $adminToken = $this->login($admin->getEmail());
        $this->call('POST', '/api/admin/users', ['email' => 'invited@test.example', 'firstName' => 'Анна', 'roles' => ['ROLE_TECHNICIAN']], $adminToken);
        $sent = $this->body()['invitationSent'] ?? false;
        $plain = $this->invitationToken();
        $hash = $this->em()->getConnection()->fetchOne('SELECT tokenHash FROM user_invitations');
        $this->call('POST', '/api/auth/activate', ['token' => $plain, 'firstName' => ' Анна ', 'lastName' => 'Иванова', 'phone' => '+70000000000', 'position' => 'Инженер', 'password' => self::PASSWORD, 'email' => 'attacker@test.example', 'roles' => ['ROLE_ADMIN']]);
        $activated = $this->client->getResponse()->getStatusCode();
        $this->call('POST', '/api/auth/activate', ['token' => $plain, 'firstName' => 'Анна', 'password' => self::PASSWORD]);
        $replay = $this->client->getResponse()->getStatusCode();
        $this->call('POST', '/api/auth/login', ['email' => 'invited@test.example', 'password' => self::PASSWORD]);
        $body = $this->body();
        self::assertSame([true, hash('sha256', $plain), 200, 400, 200, 'invited@test.example', ['ROLE_TECHNICIAN'], true, 'Иванова'], [$sent, $hash, $activated, $replay, $this->client->getResponse()->getStatusCode(), $body['user']['email'] ?? null, $body['user']['roles'] ?? null, $body['user']['active'] ?? null, $body['user']['lastName'] ?? null]);
    }

    public function testResendingInvitationInvalidatesThePreviousLink(): void
    {
        $this->captureInvitations();
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $adminToken = $this->login($admin->getEmail());
        $this->call('POST', '/api/admin/users', ['email' => 'invited@test.example', 'firstName' => 'Анна'], $adminToken);
        $id = $this->body()['id'];
        $first = $this->invitationToken();
        $this->call('POST', '/api/admin/users/'.$id.'/invitation', [], $adminToken);
        $resent = $this->client->getResponse()->getStatusCode();
        $second = $this->invitationToken();
        $this->call('POST', '/api/auth/invitation', ['token' => $first]);
        $old = $this->client->getResponse()->getStatusCode();
        $this->call('POST', '/api/auth/invitation', ['token' => $second]);
        self::assertSame([200, true, 400, 200, 'invited@test.example', 2], [$resent, $first !== $second, $old, $this->client->getResponse()->getStatusCode(), $this->body()['email'] ?? null, count($this->invitationMessages)]);
    }

    public function testPendingAccountCannotLogInUseBearerOrResetPassword(): void
    {
        $user = $this->user('pending@test.example', []);
        $user->setActive(false);
        $plain = bin2hex(random_bytes(32));
        $reset = bin2hex(random_bytes(32));
        $this->em()->persist(new AccessToken($user, $plain, new \DateTimeImmutable('+1 hour')));
        $this->em()->persist(new PasswordReset($user, $reset, new \DateTimeImmutable('+1 hour')));
        $this->em()->flush();
        $this->call('POST', '/api/auth/login', ['email' => $user->getEmail(), 'password' => self::PASSWORD]);
        $login = $this->client->getResponse()->getStatusCode();
        $this->call('GET', '/api/auth/me', null, $plain);
        $bearer = $this->client->getResponse()->getStatusCode();
        $this->call('POST', '/api/auth/reset-password', ['token' => $reset, 'password' => self::PASSWORD]);
        self::assertSame([401, 401, 400], [$login, $bearer, $this->client->getResponse()->getStatusCode()]);
    }

    public function testExpiredInvitationCannotActivateAccount(): void
    {
        $user = $this->user('expired-invite@test.example', []);
        $user->setActive(false);
        $plain = bin2hex(random_bytes(32));
        $this->em()->persist(new UserInvitation($user, $plain, new \DateTimeImmutable('-1 minute')));
        $this->em()->flush();
        $this->call('POST', '/api/auth/activate', ['token' => $plain, 'firstName' => 'Анна', 'password' => self::PASSWORD]);
        self::assertSame(400, $this->client->getResponse()->getStatusCode());
    }

    public function testBlockedInvitationCannotActivateAccount(): void
    {
        $user = $this->user('blocked-invite@test.example', [], true);
        $user->setActive(false);
        $plain = bin2hex(random_bytes(32));
        $this->em()->persist(new UserInvitation($user, $plain, new \DateTimeImmutable('+1 hour')));
        $this->em()->flush();
        $this->call('POST', '/api/auth/activate', ['token' => $plain, 'firstName' => 'Анна', 'password' => self::PASSWORD]);
        self::assertSame(400, $this->client->getResponse()->getStatusCode());
    }

    public function testAdministratorCanEditOwnProfileAndStaffCannotEditTheirs(): void
    {
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $staff = $this->user('staff@test.example', ['ROLE_DIRECTOR']);
        $adminToken = $this->login($admin->getEmail());
        $staffToken = $this->login($staff->getEmail());
        $this->call('PUT', '/api/admin/users/'.$admin->getId(), ['email' => $admin->getEmail(), 'firstName' => 'Новое имя', 'roles' => ['ROLE_ADMIN']], $adminToken);
        $own = $this->client->getResponse()->getStatusCode();
        $this->call('PUT', '/api/admin/users/'.$staff->getId(), ['email' => $staff->getEmail(), 'firstName' => 'Изменено'], $staffToken);
        $denied = $this->client->getResponse()->getStatusCode();
        $this->call('GET', '/api/auth/me', null, $adminToken);
        self::assertSame([200, 403, 'Новое имя'], [$own, $denied, $this->body()['firstName'] ?? null]);
    }

    public function testAnotherAdministratorsAvatarAndPasswordResetAreProtected(): void
    {
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $other = $this->user('other-admin@test.example', ['ROLE_ADMIN']);
        $token = $this->login($admin->getEmail());
        $this->call('DELETE', '/api/admin/users/'.$other->getId().'/avatar', null, $token);
        $avatar = $this->client->getResponse()->getStatusCode();
        $this->call('POST', '/api/admin/users/'.$other->getId().'/password-reset', null, $token);
        self::assertSame([403, 403], [$avatar, $this->client->getResponse()->getStatusCode()]);
    }

    public function testInvitationAvatarSavesImmediatelyWithoutActivatingAccount(): void
    {
        $user = $this->user('avatar-invite@test.example', []);
        $user->setActive(false);
        $plain = bin2hex(random_bytes(32));
        $this->em()->persist(new UserInvitation($user, $plain, new \DateTimeImmutable('+1 hour')));
        $this->em()->flush();
        $path = tempnam(sys_get_temp_dir(), 'invitation-avatar');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a/0cAAAAASUVORK5CYII='));
        try {
            $this->client->request('POST', '/api/auth/invitation/avatar', files: ['avatar' => new UploadedFile($path, 'avatar.png', 'image/png', test: true)], server: ['HTTP_X_INVITATION_TOKEN' => $plain]);
            $uploaded = $this->client->getResponse()->getStatusCode();
            $this->client->request('GET', '/api/auth/invitation/avatar', server: ['HTTP_X_INVITATION_TOKEN' => $plain]);
            $read = $this->client->getResponse()->getStatusCode();
            $this->call('POST', '/api/auth/invitation', ['token' => $plain]);
            $hasAvatar = $this->body()['hasAvatar'] ?? null;
            $this->client->request('DELETE', '/api/auth/invitation/avatar', server: ['HTTP_X_INVITATION_TOKEN' => $plain]);
            $deleted = $this->client->getResponse()->getStatusCode();
            $this->em()->clear();
            $stillPending = !$this->em()->find(User::class, $user->getId())->isActive();
            self::assertSame([200, 200, true, 204, true], [$uploaded, $read, $hasAvatar, $deleted, $stillPending]);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testInvitationAvatarRequiresAValidToken(): void
    {
        $this->call('GET', '/api/auth/invitation/avatar');
        $missing = $this->client->getResponse()->getStatusCode();
        $this->client->request('DELETE', '/api/auth/invitation/avatar', server: ['HTTP_X_INVITATION_TOKEN' => bin2hex(random_bytes(32))]);
        self::assertSame([400, 400], [$missing, $this->client->getResponse()->getStatusCode()]);
    }

    public function testFailedResendPreservesThePreviouslySentInvitation(): void
    {
        $this->captureInvitations();
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $adminToken = $this->login($admin->getEmail());
        $this->call('POST', '/api/admin/users', ['email' => 'delivery@test.example', 'firstName' => 'Анна'], $adminToken);
        $id = $this->body()['id'];
        $original = $this->invitationToken();
        $this->failInvitationMail = true;
        $this->call('POST', '/api/admin/users/'.$id.'/invitation', [], $adminToken);
        $failed = $this->client->getResponse()->getStatusCode();
        $this->failInvitationMail = false;
        $this->call('POST', '/api/auth/invitation', ['token' => $original]);
        self::assertSame([503, 200, 'delivery@test.example'], [$failed, $this->client->getResponse()->getStatusCode(), $this->body()['email'] ?? null]);
    }

    public function testOrdinaryAdministratorCannotRemoveOwnAdministrativeRole(): void
    {
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $pending = $this->user('pending-admin@test.example', ['ROLE_ADMIN']);
        $pending->setActive(false);
        $this->em()->flush();
        $token = $this->login($admin->getEmail());
        $this->call('PUT', '/api/admin/users/'.$admin->getId(), ['email' => $admin->getEmail(), 'firstName' => 'Анна', 'roles' => []], $token);
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testInvalidActivationInputDoesNotConsumeInvitation(): void
    {
        $user = $this->user('invalid-activation@test.example', []);
        $user->setActive(false);
        $plain = bin2hex(random_bytes(32));
        $this->em()->persist(new UserInvitation($user, $plain, new \DateTimeImmutable('+1 hour')));
        $this->em()->flush();
        $this->call('POST', '/api/auth/activate', ['token' => $plain, 'firstName' => ' ', 'password' => 'short']);
        $invalid = $this->client->getResponse()->getStatusCode();
        $this->call('POST', '/api/auth/invitation', ['token' => $plain]);
        self::assertSame([422, 200], [$invalid, $this->client->getResponse()->getStatusCode()]);
    }

    public function testAdministratorCannotUploadAnotherAdministratorsAvatar(): void
    {
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $other = $this->user('other-admin@test.example', ['ROLE_ADMIN']);
        $token = $this->login($admin->getEmail());
        $path = tempnam(sys_get_temp_dir(), 'protected-avatar');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a/0cAAAAASUVORK5CYII='));
        try {
            $this->client->request('POST', '/api/admin/users/'.$other->getId().'/avatar', files: ['avatar' => new UploadedFile($path, 'avatar.png', 'image/png', test: true)], server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
            self::assertSame(403, $this->client->getResponse()->getStatusCode());
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testAdministratorCannotBlockOwnAccountEvenWithAnotherAdministratorPresent(): void
    {
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $this->user('other@test.example', ['ROLE_ADMIN']);
        $token = $this->login($admin->getEmail());
        $this->call('PUT', '/api/admin/users/'.$admin->getId(), ['email' => $admin->getEmail(), 'firstName' => 'Тест', 'roles' => ['ROLE_ADMIN'], 'blocked' => true], $token);
        self::assertSame(409, $this->client->getResponse()->getStatusCode());
    }

    public function testSuperAdministratorCanEditAnotherAdministratorsProfile(): void
    {
        $super = $this->user('super@test.example', ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $token = $this->login($super->getEmail());
        $this->call('PUT', '/api/admin/users/'.$admin->getId(), ['email' => $admin->getEmail(), 'firstName' => 'Новое имя', 'roles' => ['ROLE_ADMIN']], $token);
        self::assertSame([200, 'Новое имя'], [$this->client->getResponse()->getStatusCode(), $this->body()['firstName'] ?? null]);
    }

    public function testOrdinaryAdministratorCannotPromoteUsersOrCreatePrivilegedAccounts(): void
    {
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $user = $this->user('staff@test.example', []);
        $token = $this->login($admin->getEmail());
        $this->call('PUT', '/api/admin/users/'.$user->getId(), ['email' => $user->getEmail(), 'firstName' => 'Тест', 'roles' => ['ROLE_ADMIN']], $token);
        $promote = $this->client->getResponse()->getStatusCode();
        $this->call('POST', '/api/admin/users', ['email' => 'new-admin@test.example', 'firstName' => 'Тест', 'roles' => ['ROLE_SUPER_ADMIN']], $token);
        self::assertSame([403, 403], [$promote, $this->client->getResponse()->getStatusCode()]);
    }

    public function testOrdinaryAdministratorCannotPromoteSelfToSuperAdministrator(): void
    {
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $token = $this->login($admin->getEmail());
        $this->call('PUT', '/api/admin/users/'.$admin->getId(), ['email' => $admin->getEmail(), 'firstName' => 'Тест', 'roles' => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']], $token);
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testSuperAdministratorCanGrantAndRevokeAdministratorRole(): void
    {
        $super = $this->user('super@test.example', ['ROLE_SUPER_ADMIN']);
        $user = $this->user('staff@test.example', ['ROLE_LAWYER']);
        $token = $this->login($super->getEmail());
        $this->call('PUT', '/api/admin/users/'.$user->getId(), ['email' => $user->getEmail(), 'firstName' => 'Тест', 'roles' => ['ROLE_LAWYER', 'ROLE_ADMIN']], $token);
        $promoted = $this->body()['roles'] ?? [];
        $this->call('PUT', '/api/admin/users/'.$user->getId(), ['email' => $user->getEmail(), 'firstName' => 'Тест', 'roles' => ['ROLE_LAWYER']], $token);
        self::assertSame([true, 200, ['ROLE_LAWYER']], [in_array('ROLE_ADMIN', $promoted, true), $this->client->getResponse()->getStatusCode(), $this->body()['roles'] ?? null]);
    }

    public function testSuperAdministratorCanBlockAnotherAdministratorAndRevokeTheirSession(): void
    {
        $super = $this->user('super@test.example', ['ROLE_SUPER_ADMIN']);
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $token = $this->login($super->getEmail());
        $adminToken = $this->login($admin->getEmail());
        $this->call('PUT', '/api/admin/users/'.$admin->getId(), ['email' => $admin->getEmail(), 'firstName' => 'Тест', 'roles' => ['ROLE_ADMIN'], 'blocked' => true], $token);
        $blocked = $this->client->getResponse()->getStatusCode();
        $this->call('GET', '/api/auth/me', null, $adminToken);
        self::assertSame([200, 401], [$blocked, $this->client->getResponse()->getStatusCode()]);
    }

    public function testSuperAdministratorCannotBlockOwnAccount(): void
    {
        $super = $this->user('super@test.example', ['ROLE_SUPER_ADMIN']);
        $this->user('other-super@test.example', ['ROLE_SUPER_ADMIN']);
        $token = $this->login($super->getEmail());
        $this->call('PUT', '/api/admin/users/'.$super->getId(), ['email' => $super->getEmail(), 'firstName' => 'Тест', 'roles' => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'], 'blocked' => true], $token);
        self::assertSame(409, $this->client->getResponse()->getStatusCode());
    }

    public function testSuperAdministratorCannotRemoveSystemRole(): void
    {
        $super = $this->user('super@test.example', ['ROLE_SUPER_ADMIN']);
        $pending = $this->user('pending-super@test.example', ['ROLE_SUPER_ADMIN']);
        $pending->setActive(false);
        $this->em()->flush();
        $token = $this->login($super->getEmail());
        $this->call('PUT', '/api/admin/users/'.$super->getId(), ['email' => $super->getEmail(), 'firstName' => 'Тест', 'roles' => ['ROLE_ADMIN']], $token);
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testSuperAdministratorCannotAssignSystemRoleToAnotherUser(): void
    {
        $super = $this->user('super@test.example', ['ROLE_SUPER_ADMIN']);
        $staff = $this->user('staff@test.example', []);
        $token = $this->login($super->getEmail());
        $this->call('PUT', '/api/admin/users/'.$staff->getId(), ['email' => $staff->getEmail(), 'firstName' => 'Тест', 'roles' => ['ROLE_SUPER_ADMIN']], $token);
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testSuperAdministratorCannotInviteAnotherSuperAdministrator(): void
    {
        $super = $this->user('super@test.example', ['ROLE_SUPER_ADMIN']);
        $token = $this->login($super->getEmail());
        $this->call('POST', '/api/admin/users', ['email' => 'new-super@test.example', 'firstName' => 'Тест', 'roles' => ['ROLE_SUPER_ADMIN']], $token);
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testSuperAdministratorCanInviteAnAdministrator(): void
    {
        $super = $this->user('super@test.example', ['ROLE_SUPER_ADMIN']);
        $token = $this->login($super->getEmail());
        $this->call('POST', '/api/admin/users', ['email' => 'new-admin@test.example', 'firstName' => 'Тест', 'roles' => ['ROLE_ADMIN']], $token);
        self::assertSame([201, ['ROLE_ADMIN'], false], [$this->client->getResponse()->getStatusCode(), $this->body()['roles'] ?? null, $this->body()['active'] ?? null]);
    }

    public function testRoleOrderingAloneDoesNotRevokeTheUsersSession(): void
    {
        $admin = $this->user('admin@test.example', ['ROLE_ADMIN']);
        $user = $this->user('staff@test.example', ['ROLE_TECHNICIAN', 'ROLE_DIRECTOR']);
        $token = $this->login($admin->getEmail());
        $staffToken = $this->login($user->getEmail());
        $this->call('PUT', '/api/admin/users/'.$user->getId(), ['email' => $user->getEmail(), 'firstName' => 'Новое имя', 'roles' => ['ROLE_DIRECTOR', 'ROLE_TECHNICIAN']], $token);
        $this->call('GET', '/api/auth/me', null, $staffToken);
        self::assertSame([200, 'Новое имя'], [$this->client->getResponse()->getStatusCode(), $this->body()['firstName'] ?? null]);
    }

    #[DataProvider('roleCatalogueActors')]
    public function testRoleCatalogueContainsDescriptionsAndAssignmentRules(array $roles, bool $canAssignAdmin, bool $canAssignStaff): void
    {
        $actor = $this->user('roles@test.example', $roles);
        $token = $this->login($actor->getEmail());
        $this->call('GET', '/api/roles', null, $token);
        $items = $this->body()['items'] ?? [];
        $byCode = array_column($items, null, 'code');
        self::assertSame(
            [200, 6, true, false, $canAssignAdmin, $canAssignStaff],
            [$this->client->getResponse()->getStatusCode(), count($items), 6 === count(array_filter($items, static fn (array $role): bool => '' !== ($role['label'] ?? '') && '' !== ($role['description'] ?? ''))), $byCode['ROLE_SUPER_ADMIN']['assignable'] ?? null, $byCode['ROLE_ADMIN']['assignable'] ?? null, $byCode['ROLE_TECHNICIAN']['assignable'] ?? null]
        );
    }

    public static function roleCatalogueActors(): iterable
    {
        yield 'super administrator' => [['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'], true, true];
        yield 'administrator' => [['ROLE_ADMIN'], false, true];
        yield 'staff' => [['ROLE_TECHNICIAN'], false, false];
    }

    public function testRoleCatalogueRequiresAuthentication(): void
    {
        $this->call('GET', '/api/roles');
        self::assertResponseStatusCodeSame(401);
    }

    private function captureInvitations(): void
    {
        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(function (RawMessage $message): void {
            if ($this->failInvitationMail) {
                throw new \Symfony\Component\Mailer\Exception\TransportException('SMTP unavailable');
            } $this->invitationMessages[] = $message;
        });
        $container = static::getContainer();
        $service = new UserInvitationService($this->em(), $container->get(UserPasswordHasherInterface::class), $container->get(\App\Service\TokenService::class), $mailer, 'smtp://localhost', 'https://landlord.example', 'noreply@landlord.example', 86400);
        $container->set(UserInvitationService::class, $service);
    }

    private function invitationToken(): string
    {
        $message = $this->invitationMessages[array_key_last($this->invitationMessages)] ?? null;
        if (!$message instanceof \Symfony\Bridge\Twig\Mime\TemplatedEmail) {
            throw new \LogicException('Invitation mail was not captured.');
        }
        preg_match('/token=([a-f0-9]{64})/', $message->getContext()['invitationUrl'], $match);

        return $match[1];
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function user(string $email, array $roles, bool $blocked = false): User
    {
        $user = new User($email);
        $user->setFirstName('Тест');
        $user->setActive(true);
        $user->setRoles($roles);
        $user->setBlocked($blocked);
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD));
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    private function login(string $email): string
    {
        $this->call('POST', '/api/auth/login', ['email' => $email, 'password' => self::PASSWORD]);
        if (200 !== $this->client->getResponse()->getStatusCode()) {
            throw new \RuntimeException('Fixture login failed: '.$this->client->getResponse()->getContent());
        }

        return $this->body()['accessToken'];
    }

    private function call(string $method, string $path, ?array $body = null, ?string $token = null): void
    {
        if (null !== $body && in_array($method, ['POST', 'PUT'], true) && preg_match('#^/api/admin/users(?:/\d+)?$#', $path)) {
            $body += ['firstName' => 'Тест'];
        }
        $this->client->request($method, $path, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => null === $token ? '' : 'Bearer '.$token], content: null === $body ? null : json_encode($body, JSON_THROW_ON_ERROR));
    }

    private function body(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
