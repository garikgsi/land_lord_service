<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\LandLordInput;
use App\Dto\RegistryListQuery;
use App\Entity\LandLord;
use App\Service\LandLordService;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/land-lords')]
#[OA\Tag(name: 'Арендодатели')]
final class LandLordController extends AbstractController
{
    #[Route('', name: 'land_lords_list', methods: ['GET'])]
    #[OA\Get(description: 'Просмотр для авторизованных пользователей. Поиск query, page с 1; 20 записей на страницу; сортировка по имени и ID.', summary: 'Список: Арендодатели')]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/LandLordList'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    #[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
    public function list(
        #[MapQueryString]
        RegistryListQuery $input,
        LandLordService $landLords
    ): JsonResponse
    {
        return $this->json($landLords->list($input));
    }

    #[Route('/{id}', name: 'land_lords_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[OA\Get(summary: 'Карточка: Арендодатели')]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/LandLord'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    #[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
    public function show(
        #[MapEntity(id: 'id')]
        LandLord $record,
        LandLordService $landLords
    ): JsonResponse
    {
        return $this->json($landLords->present($record));
    }

    #[Route('', name: 'land_lords_create', methods: ['POST'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\Post(description: 'Создание разрешено администратору и юристу.', summary: 'Создать: Арендодатели')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LandLordInput'))]
    #[OA\Response(response: 201, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/LandLord'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    #[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
    #[OA\Response(ref: '#/components/responses/BadRequest', response: 400)]
    #[OA\Response(ref: '#/components/responses/Conflict', response: 409)]
    #[OA\Response(ref: '#/components/responses/UnsupportedMediaType', response: 415)]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    public function create(
        #[MapRequestPayload(acceptFormat: 'json')]
        LandLordInput $input,
        LandLordService $landLords
    ): JsonResponse
    {
        return $this->json($landLords->present($landLords->save($input)), 201);
    }

    #[Route('/{id}', name: 'land_lords_update', requirements: ['id' => '\\d+'], methods: ['PUT'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\Put(description: 'Полная замена полей. Администратор и юрист.', summary: 'Изменить: Арендодатели')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LandLordInput'))]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/LandLord'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    #[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
    #[OA\Response(ref: '#/components/responses/BadRequest', response: 400)]
    #[OA\Response(ref: '#/components/responses/Conflict', response: 409)]
    #[OA\Response(ref: '#/components/responses/UnsupportedMediaType', response: 415)]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    public function update(
        #[MapEntity(id: 'id')]
        LandLord $record,
        #[MapRequestPayload(acceptFormat: 'json')]
        LandLordInput $input,
        LandLordService $landLords
    ): JsonResponse
    {
        return $this->json($landLords->present($landLords->save($input, $record)));
    }
}
