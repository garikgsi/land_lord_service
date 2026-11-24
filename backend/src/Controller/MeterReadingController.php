<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\MeterReadingInput;
use App\Dto\MeterReadingPreviousQuery;
use App\Dto\RegistryListQuery;
use App\Entity\Meter;
use App\Entity\MeterReading;
use App\Service\MeterReadingService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/meters/{meterId}/readings', requirements: ['meterId' => '\\d+'])]
#[OA\Tag(name: 'Приборы учёта')]
final class MeterReadingController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Показания и потребление за фактический интервал')]
    #[OA\Response(response: 200, description: 'Список', content: new OA\JsonContent(ref: '#/components/schemas/MeterReadingList'))]
    public function list(
        #[MapEntity(id: 'meterId')]
        Meter $meter,
        #[MapQueryString]
        RegistryListQuery $input,
        MeterReadingService $records
    ): JsonResponse
    {
        return $this->json($records->list($meter, $input));
    }

    #[Route('/previous', methods: ['GET'])]
    #[OA\Get(description: 'Возвращает предыдущее показание и дату последнего снятия за выбранный календарный месяц, включая записи после выбранного дня. Учитывается дата снятия, не дата внесения.', summary: 'Предыдущие показания для выбранной даты снятия')]
    #[OA\Response(response: 200, description: 'Предыдущее показание или начальное значение', content: new OA\JsonContent(ref: '#/components/schemas/MeterReadingPrevious'))]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    public function previous(
        #[MapEntity(id: 'meterId')]
        Meter $meter,
        #[MapQueryString(validationFailedStatusCode: 422)]
        MeterReadingPreviousQuery $input,
        MeterReadingService $records
    ): JsonResponse
    {
        return $this->json($records->previous($meter, new \DateTimeImmutable($input->date)));
    }

    #[Route('', methods: ['POST'])]
    #[IsGranted('METER_READING_EDIT')]
    #[OA\Post(description: 'Администратор и технарь.', summary: 'Внести целое показание; задним числом разрешено')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: MeterReadingInput::class)))]
    #[OA\Response(response: 201, description: 'Сохранено', content: new OA\JsonContent(ref: '#/components/schemas/MeterReading'))]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    public function create(
        #[MapEntity(id: 'meterId')]
        Meter $meter,
        #[MapRequestPayload(acceptFormat: 'json')]
        MeterReadingInput $input,
        MeterReadingService $records
    ): JsonResponse
    {
        return $this->json($records->present($records->save($meter, $input)), 201);
    }

    #[Route('/{id}', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[OA\Get(summary: 'Отдельное показание')]
    #[OA\Response(response: 200, description: 'Показание', content: new OA\JsonContent(ref: '#/components/schemas/MeterReading'))]
    public function show(
        #[MapEntity(id: 'meterId')]
        Meter $meter,
        #[MapEntity(id: 'id')]
        MeterReading $record,
        MeterReadingService $records
    ): JsonResponse
    {
        if ($record->getMeter()->getId() !== $meter->getId()) {
            throw $this->createNotFoundException();
        }

        return $this->json($records->present($record));
    }

    #[Route('/{id}', requirements: ['id' => '\\d+'], methods: ['PUT'])]
    #[IsGranted('METER_READING_EDIT')]
    #[OA\Put(description: 'Администратор и технарь. Одна актуальная запись на момент снятия; проверяются оба соседних показания. История исправлений не хранится.', summary: 'Исправить показание')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: MeterReadingInput::class)))]
    #[OA\Response(response: 200, description: 'Сохранено', content: new OA\JsonContent(ref: '#/components/schemas/MeterReading'))]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    public function update(
        #[MapEntity(id: 'meterId')]
        Meter $meter,
        #[MapEntity(id: 'id')]
        MeterReading $record,
        #[MapRequestPayload(acceptFormat: 'json')]
        MeterReadingInput $input,
        MeterReadingService $records
    ): JsonResponse
    {
        return $this->json($records->present($records->save($meter, $input, $record)));
    }
}
