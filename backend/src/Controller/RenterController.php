<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\RegistryListQuery;
use App\Dto\RenterInput;
use App\Entity\Renter;
use App\Service\RenterService;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/renters')]
#[OA\Tag(name: 'Арендаторы')]
final class RenterController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'Арендаторы: поиск по имени и ИНН, страницы по 20 записей')]
    #[OA\Response(response: 200, description: 'Список', content: new OA\JsonContent(ref: '#/components/schemas/RenterList'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    public function list(
        #[MapQueryString]
        RegistryListQuery $input,
        RenterService $renters
    ): JsonResponse
    {
        return $this->json($renters->list($input));
    }

    #[Route('/{id}', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[OA\Get(summary: 'Renter show')]
    #[OA\Response(response: 200, description: 'Арендатор', content: new OA\JsonContent(ref: '#/components/schemas/Renter'))]
    public function show(
        #[MapEntity(id: 'id')]
        Renter $renter,
        RenterService $renters
    ): JsonResponse
    {
        return $this->json($renters->present($renter));
    }

    #[Route('', methods: ['POST'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\Post(summary: 'Создать арендатора; юрист или администратор')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RenterInput'))]
    #[OA\Response(response: 201, description: 'Создан', content: new OA\JsonContent(ref: '#/components/schemas/Renter'))]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    public function create(
        #[MapRequestPayload(acceptFormat: 'json')]
        RenterInput $input,
        RenterService $renters
    ): JsonResponse
    {
        return $this->json($renters->present($renters->save($input)), 201);
    }

    #[Route('/{id}', requirements: ['id' => '\\d+'], methods: ['PUT'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RenterInput'))]
    #[OA\Put(summary: 'Renter update')]
    #[OA\Response(response: 200, description: 'Сохранён', content: new OA\JsonContent(ref: '#/components/schemas/Renter'))]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    public function update(
        #[MapEntity(id: 'id')]
        Renter $renter,
        #[MapRequestPayload(acceptFormat: 'json')]
        RenterInput $input,
        RenterService $renters
    ): JsonResponse
    {
        return $this->json($renters->present($renters->save($input, $renter)));
    }

    #[Route('/{id}', requirements: ['id' => '\\d+'], methods: ['DELETE'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\Delete(description: 'Ставит пометку удаления без физического удаления строки. Запись исчезает из рабочих списков; связи и история сохраняются.', summary: 'Пометить запись удалённой')]
    #[OA\Response(response: 204, description: 'Поставлена пометка удаления')]
    #[OA\Response(ref: '#/components/responses/Conflict', response: 409)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    public function remove(
        #[MapEntity(id: 'id')]
        Renter $record,
        RenterService $records
    ): \Symfony\Component\HttpFoundation\Response
    {
        $records->remove($record);

        return new \Symfony\Component\HttpFoundation\Response(status: 204);
    }
}
