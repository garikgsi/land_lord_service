<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\NotificationTypeInput;
use App\Entity\NotificationType;
use App\Service\NotificationTypeService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notification-types')]
#[IsGranted('NOTIFICATION_SETTINGS_EDIT')]
#[OA\Tag(name: 'Уведомления')]
final class NotificationTypeController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Настройки типов системных уведомлений')]
    #[OA\Response(response: 200, description: 'Настройки', content: new OA\JsonContent(ref: '#/components/schemas/NotificationTypeList'))]
    public function list(
        NotificationTypeService $types
    ): JsonResponse
    {
        return $this->json($types->list());
    }

    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(summary: 'Настройки типа уведомления')]
    #[OA\Response(response: 200, description: 'Настройки', content: new OA\JsonContent(ref: '#/components/schemas/NotificationType'))]
    public function show(
        #[MapEntity(id: 'id')]
        NotificationType $type,
        NotificationTypeService $types
    ): JsonResponse
    {
        return $this->json($types->present($type));
    }

    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[OA\Put(description: 'Настройки считываются из БД при следующей генерации. Для timing=lead days — число дней до события (0 — день события); для timing=repeat — интервал повторения, минимум 1 день. Пустые roles исключают всех получателей. Уже созданные уведомления не удаляются.', summary: 'Изменить настройки типа уведомления')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: NotificationTypeInput::class)))]
    #[OA\Response(response: 200, description: 'Настройки сохранены', content: new OA\JsonContent(ref: '#/components/schemas/NotificationType'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    public function update(
        #[MapEntity(id: 'id')]
        NotificationType $type,
        #[MapRequestPayload(acceptFormat: 'json')]
        NotificationTypeInput $input,
        NotificationTypeService $types
    ): JsonResponse
    {
        return $this->json($types->present($types->update($type, $input)));
    }
}
