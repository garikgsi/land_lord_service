<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\RegistryListQuery;
use App\Dto\RentalObjectInput;
use App\Dto\RentalObjectMeterSettingsInput;
use App\Entity\RentalObject;
use App\Service\RentalObjectService;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/objects')]
#[OA\Tag(name: 'Объекты')]
final class RentalObjectController extends AbstractController
{
    #[Route('', name: 'objects_list', methods: ['GET'])]
    #[OA\Get(description: 'Просмотр для авторизованных пользователей. Поиск query, page с 1; 20 записей на страницу; сортировка по имени и ID.', summary: 'Список: Объекты')]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/RentalObjectList'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    #[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
    public function list(
        #[MapQueryString]
        RegistryListQuery $input,
        RentalObjectService $rentalObjects
    ): JsonResponse
    {
        return $this->json($rentalObjects->list($input));
    }

    #[Route('/{id}', name: 'objects_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[OA\Get(summary: 'Карточка: Объекты')]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/RentalObject'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    #[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
    public function show(
        #[MapEntity(id: 'id')]
        RentalObject $record,
        RentalObjectService $rentalObjects
    ): JsonResponse
    {
        return $this->json($rentalObjects->present($record));
    }

    #[Route('', name: 'objects_create', methods: ['POST'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\Post(description: 'Создание разрешено администратору и юристу. readingDay при отсутствии задаётся сервером: 25. Площадь передаётся десятичной строкой, например 123.45.', summary: 'Создать: Объекты')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RentalObjectInput'))]
    #[OA\Response(response: 201, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/RentalObject'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    #[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
    #[OA\Response(ref: '#/components/responses/BadRequest', response: 400)]
    #[OA\Response(ref: '#/components/responses/Conflict', response: 409)]
    #[OA\Response(ref: '#/components/responses/UnsupportedMediaType', response: 415)]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    public function create(
        #[MapRequestPayload(acceptFormat: 'json')]
        RentalObjectInput $input,
        RentalObjectService $rentalObjects
    ): JsonResponse
    {
        return $this->json($rentalObjects->present($rentalObjects->save($input)), 201);
    }

    #[Route('/{id}', name: 'objects_update', requirements: ['id' => '\\d+'], methods: ['PUT'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\Put(description: 'Полная замена полей. Администратор и юрист. Смена арендодателя при незавершённых договорах возвращает 409.', summary: 'Изменить: Объекты')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RentalObjectInput'))]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/RentalObject'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    #[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
    #[OA\Response(ref: '#/components/responses/BadRequest', response: 400)]
    #[OA\Response(ref: '#/components/responses/Conflict', response: 409)]
    #[OA\Response(ref: '#/components/responses/UnsupportedMediaType', response: 415)]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    public function update(
        #[MapEntity(id: 'id')]
        RentalObject $record,
        #[MapRequestPayload(acceptFormat: 'json')]
        RentalObjectInput $input,
        RentalObjectService $rentalObjects
    ): JsonResponse
    {
        return $this->json($rentalObjects->present($rentalObjects->save($input, $record)));
    }

    #[Route('/{id}/meter-settings', name: 'objects_meter_settings_update', requirements: ['id' => '\\d+'], methods: ['PUT'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\Put(description: 'Администратор и юрист. Изменяет только общий день передачи показаний, не затрагивая основные поля объекта.', summary: 'Настройки приборов учёта объекта')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RentalObjectMeterSettingsInput'))]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/RentalObject'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    #[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
    #[OA\Response(ref: '#/components/responses/BadRequest', response: 400)]
    #[OA\Response(ref: '#/components/responses/UnsupportedMediaType', response: 415)]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    public function updateMeterSettings(
        #[MapEntity(id: 'id')]
        RentalObject $record,
        #[MapRequestPayload(acceptFormat: 'json')]
        RentalObjectMeterSettingsInput $input,
        RentalObjectService $rentalObjects
    ): JsonResponse
    {
        return $this->json($rentalObjects->present($rentalObjects->updateMeterSettings($record, $input)));
    }

    #[Route('/{id}', requirements: ['id' => '\\d+'], methods: ['DELETE'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\Delete(description: 'Ставит пометку удаления без физического удаления строки. Запись исчезает из рабочих списков; связи и история сохраняются.', summary: 'Пометить запись удалённой')]
    #[OA\Response(response: 204, description: 'Поставлена пометка удаления')]
    #[OA\Response(ref: '#/components/responses/Conflict', response: 409)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    public function remove(
        #[MapEntity(id: 'id')]
        RentalObject $record,
        RentalObjectService $records
    ): \Symfony\Component\HttpFoundation\Response
    {
        $records->remove($record);

        return new \Symfony\Component\HttpFoundation\Response(status: 204);
    }
}
