<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\NamedRecordInput;
use App\Dto\RegistryListQuery;
use App\Entity\ContractType;
use App\Service\ContractTypeService;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/contract-types')]
#[OA\Tag(name: 'Справочники договоров')]
final class ContractTypeController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    #[OA\Get(description: 'Полный список по id ASC; query фильтрует по наименованию. Доступен всем вошедшим пользователям.', summary: 'Типы договоров')]
    #[OA\Response(response: 200, description: 'Справочник', content: new OA\JsonContent(ref: '#/components/schemas/NamedList'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    public function list(
        #[MapQueryString]
        RegistryListQuery $input,
        ContractTypeService $records
    ): JsonResponse
    {
        return $this->json($records->list($input));
    }

    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(summary: 'ContractType: отдельная запись')]
    #[OA\Response(response: 200, description: 'Запись', content: new OA\JsonContent(ref: '#/components/schemas/NamedRecord'))]
    public function show(
        #[MapEntity(id: 'id')]
        ContractType $record,
        ContractTypeService $records
    ): JsonResponse
    {
        return $this->json($records->present($record));
    }

    #[Route('', methods: ['POST'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\Post(summary: 'ContractType: создать; юрист или администратор')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/NamedRecordInput'))]
    #[OA\Response(response: 201, description: 'Создана', content: new OA\JsonContent(ref: '#/components/schemas/NamedRecord'))]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    public function create(
        #[MapRequestPayload(acceptFormat: 'json')]
        NamedRecordInput $input,
        ContractTypeService $records
    ): JsonResponse
    {
        return $this->json($records->present($records->save($input)), 201);
    }

    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\Put(description: 'Меняется наименование; ID и существующие связи сохраняются. Прежнее состояние архивируется.', summary: 'ContractType: изменить; юрист или администратор')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/NamedRecordInput'))]
    #[OA\Response(response: 200, description: 'Сохранена', content: new OA\JsonContent(ref: '#/components/schemas/NamedRecord'))]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    public function update(
        #[MapEntity(id: 'id')]
        ContractType $record,
        #[MapRequestPayload(acceptFormat: 'json')]
        NamedRecordInput $input,
        ContractTypeService $records
    ): JsonResponse
    {
        return $this->json($records->present($records->save($input, $record)));
    }

    #[Route('/{id}', requirements: ['id' => '\\d+'], methods: ['DELETE'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\Delete(description: 'Ставит пометку удаления без физического удаления строки. Запись исчезает из рабочих списков; связи и история сохраняются.', summary: 'Пометить запись удалённой')]
    #[OA\Response(response: 204, description: 'Поставлена пометка удаления')]
    #[OA\Response(ref: '#/components/responses/Conflict', response: 409)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    public function remove(
        #[MapEntity(id: 'id')]
        ContractType $record,
        ContractTypeService $records
    ): \Symfony\Component\HttpFoundation\Response
    {
        $records->remove($record);

        return new \Symfony\Component\HttpFoundation\Response(status: 204);
    }
}
