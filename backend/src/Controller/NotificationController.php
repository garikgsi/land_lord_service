<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ContractCalendarQuery;
use App\Dto\NotificationListQuery;
use App\Entity\User;
use App\Service\NotificationService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/notifications')]
#[OA\Tag(name: 'Уведомления')]
final class NotificationController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    #[OA\Get(description: 'Личные системные сообщения; query ищет по заголовку/тексту, status=all|unread. Новые первыми, по 20 записей. Создание через публичный API отсутствует.', summary: 'Мои уведомления')]
    #[OA\Response(response: 200, description: 'Список', content: new OA\JsonContent(ref: '#/components/schemas/NotificationList'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    public function list(
        #[CurrentUser]
        User $user,
        #[MapQueryString(validationFailedStatusCode: 422)]
        NotificationListQuery $input,
        NotificationService $notifications
    ): JsonResponse
    {
        return $this->json($notifications->list($user, $input));
    }

    #[Route('/unread-count', methods: ['GET'])]
    #[OA\Get(summary: 'Количество моих непрочитанных сообщений')]
    #[OA\Response(response: 200, description: 'Количество', content: new OA\JsonContent(ref: '#/components/schemas/NotificationCount'))]
    public function count(
        #[CurrentUser]
        User $user,
        NotificationService $notifications
    ): JsonResponse
    {
        return $this->json(['unreadCount' => $notifications->unreadCount($user)]);
    }

    #[Route('/calendar', methods: ['GET'])]
    #[OA\Get(description: 'Только мои уведомления с dueDate. start включительно, end исключительно, YYYY-MM-DD, не более 370 дней. Прочитанные сообщения остаются в календаре: синие — новые, зелёные — прочитанные/исполненные.', summary: 'Мои уведомления в календаре')]
    #[OA\Response(response: 200, description: 'События', content: new OA\JsonContent(ref: '#/components/schemas/ContractCalendar'))]
    public function calendar(
        #[CurrentUser]
        User $user,
        #[MapQueryString(validationFailedStatusCode: 422)]
        ContractCalendarQuery $input,
        NotificationService $notifications
    ): JsonResponse
    {
        return $this->json(['items' => $notifications->calendar($user, new \DateTimeImmutable($input->start), new \DateTimeImmutable($input->end))]);
    }

    #[Route('/read-all', methods: ['PUT'])]
    #[OA\Put(description: 'Идемпотентно отмечает все мои сообщения прочитанными. Не меняет чужие сообщения и не удаляет календарные события.', summary: 'Прочитать все мои уведомления')]
    #[OA\Response(response: 200, description: 'Количество', content: new OA\JsonContent(ref: '#/components/schemas/NotificationCount'))]
    public function readAll(
        #[CurrentUser]
        User $user,
        NotificationService $notifications
    ): JsonResponse
    {
        $notifications->markAllRead($user);

        return $this->json(['unreadCount' => $notifications->unreadCount($user)]);
    }

    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(description: 'Чтение записи само по себе не меняет readAt; интерфейс вызывает PUT /read после открытия.', summary: 'Моё уведомление')]
    #[OA\Response(response: 200, description: 'Сообщение', content: new OA\JsonContent(ref: '#/components/schemas/Notification'))]
    #[OA\Response(response: 404, description: 'Уведомления нет или оно принадлежит другому пользователю')]
    public function show(
        int $id,
        #[CurrentUser]
        User $user,
        NotificationService $notifications
    ): JsonResponse
    {
        return $this->json($notifications->present($notifications->owned($id, $user)));
    }

    #[Route('/{id}/read', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[OA\Put(description: 'Идемпотентно; исходное время прочтения сохраняется при повторе. Для чужого ID — 404.', summary: 'Отметить моё уведомление прочитанным')]
    #[OA\Response(response: 200, description: 'Сообщение', content: new OA\JsonContent(ref: '#/components/schemas/Notification'))]
    #[OA\Response(response: 404, description: 'Уведомление не найдено')]
    public function read(
        int $id,
        #[CurrentUser]
        User $user,
        NotificationService $notifications
    ): JsonResponse
    {
        return $this->json($notifications->present($notifications->markRead($id, $user)));
    }
}
