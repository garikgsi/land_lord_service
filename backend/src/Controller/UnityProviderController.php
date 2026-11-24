<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\RegistryListQuery;
use App\Dto\UnityProviderInput;
use App\Entity\UnityProvider;
use App\Service\UnityProviderService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/unity-providers')]
#[OA\Tag(name: 'Приборы учёта')]
final class UnityProviderController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'UnityProvider: список')]
    #[OA\Response(response: 200, description: 'Список записей', content: new OA\JsonContent(ref: '#/components/schemas/UnityProviderList'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    public function list(
        #[MapQueryString]
        RegistryListQuery $input,
        UnityProviderService $records
    ): JsonResponse
    {
        return $this->json($records->list($input));
    }

    #[Route('/{id}', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[OA\Get(summary: 'UnityProvider: карточка')]
    #[OA\Response(response: 200, description: 'Запись', content: new OA\JsonContent(ref: '#/components/schemas/UnityProvider'))]
    public function show(
        #[MapEntity(id: 'id')]
        UnityProvider $record,
        UnityProviderService $records
    ): JsonResponse
    {
        return $this->json($records->present($record));
    }

    #[Route('', methods: ['POST'])]
    #[IsGranted('METER_EDIT')]
    #[OA\Post(description: 'Только администратор; остальные роли просматривают.', summary: 'UnityProvider: создать')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: UnityProviderInput::class)))]
    #[OA\Response(response: 201, description: 'Создано', content: new OA\JsonContent(ref: '#/components/schemas/UnityProvider'))]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    public function create(
        #[MapRequestPayload(acceptFormat: 'json')]
        UnityProviderInput $input,
        UnityProviderService $records
    ): JsonResponse
    {
        return $this->json($records->present($records->save($input)), 201);
    }

    #[Route('/{id}', requirements: ['id' => '\\d+'], methods: ['PUT'])]
    #[IsGranted('METER_EDIT')]
    #[OA\Put(description: 'Только администратор; остальные роли просматривают.', summary: 'UnityProvider: изменить')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: UnityProviderInput::class)))]
    #[OA\Response(response: 200, description: 'Сохранено', content: new OA\JsonContent(ref: '#/components/schemas/UnityProvider'))]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    public function update(
        #[MapEntity(id: 'id')]
        UnityProvider $record,
        #[MapRequestPayload(acceptFormat: 'json')]
        UnityProviderInput $input,
        UnityProviderService $records
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
        UnityProvider $record,
        UnityProviderService $records
    ): \Symfony\Component\HttpFoundation\Response
    {
        $records->remove($record);

        return new \Symfony\Component\HttpFoundation\Response(status: 204);
    }
}
