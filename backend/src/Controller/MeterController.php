<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\MeterInput;
use App\Dto\MeterListQuery;
use App\Entity\Meter;
use App\Service\MeterService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/meters')]
#[OA\Tag(name: 'Приборы учёта')]
final class MeterController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    #[OA\Get(description: 'Фильтры objectId (включая счетчики помещений), spaceId и utilityId объединяются с поиском query и состоянием установки state. Результат разбит на страницы по 20 записей.', summary: 'Meter: список')]
    #[OA\Response(response: 200, description: 'Список записей', content: new OA\JsonContent(ref: '#/components/schemas/MeterList'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    public function list(
        #[MapQueryString]
        MeterListQuery $input,
        MeterService $records
    ): JsonResponse
    {
        return $this->json($records->list($input));
    }

    #[Route('/{id}', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[OA\Get(summary: 'Meter: карточка')]
    #[OA\Response(response: 200, description: 'Запись', content: new OA\JsonContent(ref: '#/components/schemas/Meter'))]
    public function show(
        #[MapEntity(id: 'id')]
        Meter $record,
        MeterService $records
    ): JsonResponse
    {
        return $this->json($records->present($record));
    }

    #[Route('', methods: ['POST'])]
    #[IsGranted('METER_INSTALL')]
    #[OA\Post(description: 'Администратор и технарь могут создать и установить прибор. Собственный readingDay задаёт только администратор; технарь наследует график объекта. objectUnityProviderId — необязательная связь поставщика объекта при установке общего счётчика. Не ID глобального справочника. Для Space отсутствует.', summary: 'Meter: создать')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: MeterInput::class)))]
    #[OA\Response(response: 201, description: 'Создано', content: new OA\JsonContent(ref: '#/components/schemas/Meter'))]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    public function create(
        #[MapRequestPayload(acceptFormat: 'json')]
        MeterInput $input,
        MeterService $records
    ): JsonResponse
    {
        if (null !== $input->readingDay) {
            $this->denyAccessUnlessGranted('METER_EDIT');
        }

        return $this->json($records->present($records->save($input)), 201);
    }

    #[Route('/{id}', requirements: ['id' => '\\d+'], methods: ['PUT'])]
    #[IsGranted('METER_EDIT')]
    #[OA\Put(description: 'Только администратор.', summary: 'Meter: изменить')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: MeterInput::class)))]
    #[OA\Response(response: 200, description: 'Сохранено', content: new OA\JsonContent(ref: '#/components/schemas/Meter'))]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    public function update(
        #[MapEntity(id: 'id')]
        Meter $record,
        #[MapRequestPayload(acceptFormat: 'json')]
        MeterInput $input,
        MeterService $records
    ): JsonResponse
    {
        return $this->json($records->present($records->save($input, $record)));
    }

    #[Route('/{id}', requirements: ['id' => '\\d+'], methods: ['DELETE'])]
    #[IsGranted('METER_EDIT')]
    #[OA\Delete(description: 'Ставит пометку удаления без физического удаления строки. Запись исчезает из рабочих списков; связи и история сохраняются.', summary: 'Пометить запись удалённой')]
    #[OA\Response(response: 204, description: 'Поставлена пометка удаления')]
    #[OA\Response(ref: '#/components/responses/Conflict', response: 409)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    public function remove(
        #[MapEntity(id: 'id')]
        Meter $record,
        MeterService $records
    ): \Symfony\Component\HttpFoundation\Response
    {
        $records->remove($record);

        return new \Symfony\Component\HttpFoundation\Response(status: 204);
    }
}
