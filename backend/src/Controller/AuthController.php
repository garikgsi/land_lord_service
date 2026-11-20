<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ActivationInput;
use App\Dto\ForgotPasswordInput;
use App\Dto\InvitationTokenInput;
use App\Dto\ResetPasswordInput;
use App\Entity\User;
use App\Repository\AccessTokenRepository;
use App\Service\PasswordResetService;
use App\Service\UserInvitationService;
use App\Service\UserView;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\AccessToken\HeaderAccessTokenExtractor;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/auth')]
#[OA\Tag(name: 'Авторизация и активация')]
final class AuthController extends AbstractController
{
    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    #[OA\Post(description: 'Активная незаблокированная запись. Ответ успешного входа формирует firewall Symfony; метод контроллера содержит только ответ отказа. Лимит: 5 неудачных попыток за 15 минут.', summary: 'Вход в систему', security: [])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LoginRequest'))]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/Session'))]
    #[OA\Response(ref: '#/components/responses/BadRequest', response: 400)]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    #[OA\Response(ref: '#/components/responses/TooManyRequests', response: 429)]
    #[OA\Response(ref: '#/components/responses/ServerError', response: 'default')]
    public function login(): JsonResponse
    {
        return $this->json(['message' => 'Требуются почта и пароль.'], Response::HTTP_UNAUTHORIZED);
    }

    #[Route('/me', name: 'api_auth_me', methods: ['GET'])]
    #[OA\Get(description: 'Без пароля и хеша. Результат не кешируется.', summary: 'Текущий пользователь')]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/User'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    #[OA\Response(ref: '#/components/responses/ServerError', response: 'default')]
    public function me(
        #[CurrentUser]
        User $user,
        UserView $view
    ): JsonResponse
    {
        return $this->json($view->present($user), headers: ['Cache-Control' => 'no-store']);
    }

    #[Route('/logout', name: 'api_auth_logout', methods: ['POST'])]
    #[OA\Post(description: 'Отзывает только переданный сеансовый токен; после 204 удалить токен на клиенте.', summary: 'Выход')]
    #[OA\Response(response: 204, description: 'No content')]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    #[OA\Response(ref: '#/components/responses/ServerError', response: 'default')]
    public function logout(
        Request $request,
        AccessTokenRepository $tokens,
        EntityManagerInterface $em
    ): Response
    {
        $plain = (new HeaderAccessTokenExtractor())->extractAccessToken($request);
        if (null !== $plain) {
            $token = $tokens->findOneBy(['tokenHash' => hash('sha256', $plain)]);
            if (null !== $token) {
                $em->remove($token);
                $em->flush();
            }
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/forgot-password', name: 'api_auth_forgot', methods: ['POST'])]
    #[OA\Post(description: 'Только активная незаблокированная запись. При работающей почте ответ одинаков для неизвестного и существующего email. По умолчанию ссылка действует 1 час. Ограничения по IP и email.', summary: 'Запрос восстановления пароля', security: [])]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/Message'))]
    #[OA\Response(ref: '#/components/responses/BadRequest', response: 400)]
    #[OA\Response(ref: '#/components/responses/UnsupportedMediaType', response: 415)]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/TooManyRequests', response: 429)]
    #[OA\Response(ref: '#/components/responses/MailUnavailable', response: 503)]
    #[OA\Response(ref: '#/components/responses/ServerError', response: 'default')]
    public function forgot(
        #[MapRequestPayload(acceptFormat: 'json')]
        ForgotPasswordInput $input,
        Request $request,
        PasswordResetService $passwords,
        #[Autowire(service: 'limiter.password_reset')]
        RateLimiterFactoryInterface $limiter
    ): JsonResponse
    {
        foreach (['ip:'.$request->getClientIp(), 'email:'.hash('sha256', mb_strtolower(trim($input->email)))] as $key) {
            $limit = $limiter->create($key)->consume();
            if (!$limit->isAccepted()) {
                throw new TooManyRequestsHttpException(max(1, $limit->getRetryAfter()->getTimestamp() - time()), 'Слишком много запросов. Повторите позже.');
            }
        }
        $passwords->request($input->email);

        return $this->json(['message' => 'Если учётная запись активна, на указанную почту отправлена ссылка восстановления.']);
    }

    #[Route('/reset-password', name: 'api_auth_reset', methods: ['POST'])]
    #[OA\Post(description: 'Одноразовый reset-токен. Успех отзывает все сеансы и ссылки пользователя; затем необходим вход. Не активирует ожидающую запись.', summary: 'Новый пароль по ссылке', security: [])]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/Message'))]
    #[OA\Response(ref: '#/components/responses/BadRequest', response: 400)]
    #[OA\Response(ref: '#/components/responses/UnsupportedMediaType', response: 415)]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/ServerError', response: 'default')]
    public function reset(
        #[MapRequestPayload(acceptFormat: 'json')]
        ResetPasswordInput $input,
        PasswordResetService $passwords
    ): JsonResponse
    {
        $passwords->complete($input->token, $input->password);

        return $this->json(['message' => 'Пароль изменён. Войдите с новым паролем.']);
    }

    #[Route('/invitation', name: 'api_auth_invitation', methods: ['POST'])]
    #[OA\Post(description: 'Одноразовый токен в JSON. Возвращает ограниченный профиль ожидающего пользователя; по умолчанию приглашение действует 24 часа.', summary: 'Проверка приглашения', security: [])]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/InvitationProfile'))]
    #[OA\Response(ref: '#/components/responses/BadRequest', response: 400)]
    #[OA\Response(ref: '#/components/responses/UnsupportedMediaType', response: 415)]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/ServerError', response: 'default')]
    public function invitation(
        #[MapRequestPayload(acceptFormat: 'json')]
        InvitationTokenInput $input,
        UserInvitationService $invitations
    ): JsonResponse
    {
        return $this->json($invitations->profile($input->token), headers: ['Cache-Control' => 'no-store']);
    }

    #[Route('/activate', name: 'api_auth_activate', methods: ['POST'])]
    #[OA\Post(description: 'Имя и пароль обязательны. Email и роли не меняются. Активирует запись и погашает ссылки; сеансовый Bearer не выдаётся — требуется отдельный вход.', summary: 'Активация пользователя', security: [])]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/Message'))]
    #[OA\Response(ref: '#/components/responses/BadRequest', response: 400)]
    #[OA\Response(ref: '#/components/responses/UnsupportedMediaType', response: 415)]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/ServerError', response: 'default')]
    public function activate(
        #[MapRequestPayload(acceptFormat: 'json')]
        ActivationInput $input,
        UserInvitationService $invitations
    ): JsonResponse
    {
        $invitations->complete($input);

        return $this->json(['message' => 'Учётная запись активирована. Войдите с новым паролем.']);
    }
}
