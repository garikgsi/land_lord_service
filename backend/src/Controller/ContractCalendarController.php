<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ContractCalendarQuery;
use App\Service\ContractService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Календарь')]
final class ContractCalendarController extends AbstractController
{
    #[Route('/api/calendar/contracts', methods: ['GET'])]
    #[OA\Get(description: 'Период start включительно, end исключительно (YYYY-MM-DD), не более 370 дней. События завершения и периодические напоминания. Напоминание не меняет платёж.', summary: 'События договоров и напоминания об индексации')]
    #[OA\Response(response: 200, description: 'События', content: new OA\JsonContent(ref: '#/components/schemas/ContractCalendar'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    public function list(
        #[MapQueryString]
        ContractCalendarQuery $input,
        ContractService $contracts
    ): JsonResponse
    {
        return $this->json(['items' => $contracts->calendar(new \DateTimeImmutable($input->start), new \DateTimeImmutable($input->end))]);
    }
}
