<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\MeterVerificationInput;
use App\Entity\Meter;
use App\Service\MeterVerificationService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/meters/{meterId}/verifications', requirements: ['meterId' => '\\d+'])]
#[OA\Tag(name: 'Приборы учёта')]
final class MeterVerificationController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'История поверок')]
    #[OA\Response(response: 200, description: 'Список', content: new OA\JsonContent(ref: '#/components/schemas/MeterVerificationList'))]
    public function list(
        #[MapEntity(id: 'meterId')]
        Meter $meter,
        MeterVerificationService $records
    ): JsonResponse
    {
        return $this->json($records->list($meter));
    }

    #[Route('', methods: ['POST'])]
    #[IsGranted('METER_EDIT')]
    #[OA\Post(description: 'Только администратор.', summary: 'Добавить поверку; последняя дата вычисляется по истории')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: MeterVerificationInput::class)))]
    #[OA\Response(response: 201, description: 'Сохранено', content: new OA\JsonContent(ref: '#/components/schemas/MeterVerification'))]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    public function create(
        #[MapEntity(id: 'meterId')]
        Meter $meter,
        #[MapRequestPayload(acceptFormat: 'json')]
        MeterVerificationInput $input,
        MeterVerificationService $records
    ): JsonResponse
    {
        return $this->json($records->present($records->add($meter, new \DateTimeImmutable($input->verificationDate))), 201);
    }
}
