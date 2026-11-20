<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\UserInput;
use App\Dto\UserListQuery;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\UserManagementAccess;
use App\Service\PasswordResetService;
use App\Service\UserAdministration;
use App\Service\UserInvitationService;
use App\Service\UserView;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/admin/users')]
#[OA\Tag(name: 'Пользователи')]
final class AdminUserController extends AbstractController
{
    public const PAGE_SIZE = 20;

    #[Route('', name: 'admin_users_list', methods: ['GET'])]
    #[OA\Get(description: 'ROLE_ADMIN. Поиск по имени, фамилии, email, должности или телефону без учёта регистра; email по возрастанию, 20 записей на страницу.', summary: 'Список пользователей')]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/UserList'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    #[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
    #[OA\Response(ref: '#/components/responses/ServerError', response: 'default')]
    public function list(
        #[MapQueryString]
        UserListQuery $input,
        UserRepository $users,
        UserView $view
    ): JsonResponse
    {
        $query = $users->createQueryBuilder('u');
        if ('' !== trim($input->query)) {
            $query->andWhere('LOWER(u.email) LIKE :search OR LOWER(u.firstName) LIKE :search OR LOWER(u.lastName) LIKE :search OR LOWER(u.position) LIKE :search OR u.phone LIKE :search')
                ->setParameter('search', '%'.mb_strtolower(trim($input->query)).'%');
        }
        if ('blocked' === $input->state) {
            $query->andWhere('u.blocked = true');
        } elseif ('active' === $input->state) {
            $query->andWhere('u.active = true AND u.blocked = false');
        } elseif ('pending' === $input->state) {
            $query->andWhere('u.active = false AND u.blocked = false');
        }
        $total = (int) (clone $query)->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();
        $items = $query->orderBy('u.email', 'ASC')->setFirstResult(($input->page - 1) * self::PAGE_SIZE)->setMaxResults(self::PAGE_SIZE)->getQuery()->getResult();

        return $this->json(['items' => array_map($view->present(...), $items), 'total' => $total, 'page' => $input->page, 'pageSize' => self::PAGE_SIZE]);
    }

    #[Route('', name: 'admin_users_create', methods: ['POST'])]
    #[OA\Post(description: 'Обязательны имя и email; пароль не передаётся. Создаётся active=false. При ошибке почты всё равно 201 и invitationSent=false: запись сохранена. ROLE_ADMIN назначает только суперадминистратор; ROLE_SUPER_ADMIN нельзя назначить никому.', summary: 'Пригласить пользователя')]
    #[OA\Response(response: 201, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/UserCreated'))]
    #[OA\Response(ref: '#/components/responses/BadRequest', response: 400)]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    #[OA\Response(ref: '#/components/responses/Conflict', response: 409)]
    #[OA\Response(ref: '#/components/responses/UnsupportedMediaType', response: 415)]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/ServerError', response: 'default')]
    public function create(
        #[MapRequestPayload(acceptFormat: 'json')]
        UserInput $input,
        UserAdministration $users,
        UserView $view,
        UserInvitationService $invitations,
        #[CurrentUser]
        User $actor
    ): JsonResponse
    {
        $user = $users->create($input, $actor);
        $sent = true;
        try {
            $invitations->send($user);
        } catch (ServiceUnavailableHttpException) {
            $sent = false;
        }

        return $this->json([...$view->present($user), 'invitationSent' => $sent, 'message' => $sent ? 'Пользователь создан. Приглашение отправлено.' : 'Пользователь создан и ожидает активации. Письмо не отправлено: SMTP не настроен или недоступен. Отправьте приглашение повторно после настройки почты.'], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'admin_users_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[OA\Get(description: 'ROLE_ADMIN. Возвращает тот же безопасный профиль, что список. Право чтения не даёт права изменять другого администратора.', summary: 'Пользователь по ID')]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/User'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    #[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
    #[OA\Response(ref: '#/components/responses/ServerError', response: 'default')]
    public function show(
        #[MapEntity(id: 'id')]
        User $user,
        UserView $view
    ): JsonResponse
    {
        return $this->json($view->present($user), headers: ['Cache-Control' => 'no-store']);
    }

    #[Route('/{id}', name: 'admin_users_update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[OA\Put(description: 'Полная замена полей UserInput, не PATCH. Обычный администратор меняет себя и обычных пользователей; суперадминистратор также меняет администраторов. Самоблокировка запрещена. ROLE_SUPER_ADMIN нельзя добавить или снять даже её владельцу; при его редактировании передайте существующую роль без изменения. Email/роли/блокировка отзывают все сеансы и ссылки.', summary: 'Изменить профиль и доступ')]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/User'))]
    #[OA\Response(ref: '#/components/responses/BadRequest', response: 400)]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    #[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
    #[OA\Response(ref: '#/components/responses/Conflict', response: 409)]
    #[OA\Response(ref: '#/components/responses/UnsupportedMediaType', response: 415)]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/ServerError', response: 'default')]
    public function update(
        #[MapEntity(id: 'id')]
        User $user,
        #[MapRequestPayload(acceptFormat: 'json')]
        UserInput $input,
        UserAdministration $users,
        UserView $view,
        #[CurrentUser]
        User $actor
    ): JsonResponse
    {
        return $this->json($view->present($users->update($user, $input, $actor)));
    }

    #[Route('/{id}/password-reset', name: 'admin_users_password_reset', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[OA\Post(description: 'Только активному незаблокированному пользователю, которого оператор вправе редактировать. Пароль администратор не задаёт.', summary: 'Отправить ссылку восстановления')]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/Message'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    #[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
    #[OA\Response(ref: '#/components/responses/Conflict', response: 409)]
    #[OA\Response(ref: '#/components/responses/MailUnavailable', response: 503)]
    #[OA\Response(ref: '#/components/responses/ServerError', response: 'default')]
    public function reset(
        #[MapEntity(id: 'id')]
        User $user,
        PasswordResetService $passwords,
        UserManagementAccess $access,
        #[CurrentUser]
        User $actor
    ): JsonResponse
    {
        $access->assertCanEdit($actor, $user);
        if (!$user->isActive() || $user->isBlocked()) {
            throw new ConflictHttpException('Для этого пользователя восстановление пароля недоступно.');
        }
        $passwords->request($user->getEmail());

        return $this->json(['message' => 'Ссылка восстановления отправлена.']);
    }

    #[Route('/{id}/invitation', name: 'admin_users_invitation', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[OA\Post(description: 'Только ожидающему активации незаблокированному пользователю. Отправка на сохранённый email разрешена администратору, включая приглашение другому администратору, без изменения его профиля. Успех заменяет старую ссылку; сбой доставки сохраняет предыдущую действующую ссылку. Не более 3 запросов на пользователя за 15 минут.', summary: 'Повторно отправить приглашение')]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/Message'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    #[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
    #[OA\Response(ref: '#/components/responses/Conflict', response: 409)]
    #[OA\Response(ref: '#/components/responses/TooManyRequests', response: 429)]
    #[OA\Response(ref: '#/components/responses/MailUnavailable', response: 503)]
    #[OA\Response(ref: '#/components/responses/ServerError', response: 'default')]
    public function invite(
        #[MapEntity(id: 'id')]
        User $user,
        UserInvitationService $invitations,
        #[Autowire(service: 'limiter.user_invitation')]
        RateLimiterFactoryInterface $limiter
    ): JsonResponse
    {
        if (!$limiter->create((string) $user->getId())->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(900, 'Слишком много писем. Повторите позже.');
        }
        $invitations->send($user);

        return $this->json(['message' => 'Приглашение отправлено повторно. Предыдущая ссылка больше не действует.']);
    }
}
