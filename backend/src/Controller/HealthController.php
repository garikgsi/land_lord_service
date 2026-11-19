<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\DatabaseReadiness;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    #[Route('/api/healthz', name: 'health', methods: ['GET'])]
    public function __invoke(DatabaseReadiness $databaseReadiness): JsonResponse
    {
        if (!$databaseReadiness->isAvailable()) {
            return new JsonResponse(['status' => 'unavailable'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
