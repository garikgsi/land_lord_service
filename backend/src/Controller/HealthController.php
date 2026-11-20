<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\DatabaseReadiness;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Состояние сервиса')]
final class HealthController extends AbstractController
{
    #[Route('/api/healthz', name: 'health', methods: ['GET'])]
    #[OA\Get(description: 'Проверяет соединение с PostgreSQL. 503 возвращает status=unavailable, без реквизитов соединения.', summary: 'Проверка сервера и БД', security: [])]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/Health'))]
    #[OA\Response(response: 503, description: 'Database unavailable', content: new OA\JsonContent(ref: '#/components/schemas/Health'))]
    #[OA\Response(ref: '#/components/responses/ServerError', response: 'default')]
    public function __invoke(
        DatabaseReadiness $databaseReadiness
    ): JsonResponse
    {
        if (!$databaseReadiness->isAvailable()) {
            return new JsonResponse(['status' => 'unavailable'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
