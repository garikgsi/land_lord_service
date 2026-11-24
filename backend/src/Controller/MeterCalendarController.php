<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ContractCalendarQuery;
use App\Service\MeterCalendarService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Календарь')]
final class MeterCalendarController extends AbstractController
{
    #[Route('/api/calendar/meters', methods: ['GET'])]
    #[OA\Get(description: 'Текущие установки, общий день объекта или индивидуальный. В коротком месяце последний день. Снятые приборы исключены.', summary: 'Сроки передачи показаний и поверок')]
    #[OA\Response(response: 200, description: 'События', content: new OA\JsonContent(ref: '#/components/schemas/ContractCalendar'))]
    public function list(
        #[MapQueryString]
        ContractCalendarQuery $input,
        MeterCalendarService $calendar
    ): JsonResponse
    {
        return $this->json(['items' => $calendar->list(new \DateTimeImmutable($input->start), new \DateTimeImmutable($input->end))]);
    }
}
