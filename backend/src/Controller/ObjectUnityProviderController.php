<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ObjectUnityProviderInput;
use App\Dto\ObjectUnityProviderListQuery;
use App\Entity\ObjectUnityProvider;
use App\Entity\RentalObject;
use App\Service\ObjectUnityProviderService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/objects/{objectId}/unity-providers', requirements: ['objectId' => '\\d+'])]
#[OA\Tag(name: 'Приборы учёта')]
final class ObjectUnityProviderController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    #[OA\Get(description: 'Связи объект–коммуникация–поставщик. query ищет по наименованиям, unityId фильтрует коммуникацию. name пригоден для автокомплита.', summary: 'Поставщики коммунальных услуг объекта')]
    #[OA\Response(response: 200, description: 'Связи с поставщиками', content: new OA\JsonContent(ref: '#/components/schemas/ObjectUnityProviderList'))]
    public function list(
        #[MapEntity(id: 'objectId')]
        RentalObject $object,
        #[MapQueryString]
        ObjectUnityProviderListQuery $input,
        ObjectUnityProviderService $records
    ): JsonResponse
    {
        return $this->json($records->list($object, $input));
    }

    #[Route('', methods: ['POST'])]
    #[IsGranted('METER_EDIT')]
    #[OA\Post(description: 'Только администратор; остальные роли просматривают.', summary: 'Подключить поставщика к коммуникации объекта; точный повтор не создаётся')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: ObjectUnityProviderInput::class)))]
    #[OA\Response(response: 201, description: 'Связь', content: new OA\JsonContent(ref: '#/components/schemas/ObjectUnityProvider'))]
    public function create(
        #[MapEntity(id: 'objectId')]
        RentalObject $object,
        #[MapRequestPayload(acceptFormat: 'json')]
        ObjectUnityProviderInput $input,
        ObjectUnityProviderService $records
    ): JsonResponse
    {
        return $this->json($records->present($records->add($object, $input)), 201);
    }

    #[Route('/{id}', requirements: ['id' => '\\d+'], methods: ['DELETE'])]
    #[IsGranted('METER_EDIT')]
    #[OA\Delete(description: 'Только администратор; остальные роли просматривают.', summary: 'Удалить связь с поставщиком; сам справочник сохраняется')]
    #[OA\Response(response: 204, description: 'Связь удалена')]
    #[OA\Response(ref: '#/components/responses/Conflict', response: 409)]
    public function remove(
        #[MapEntity(id: 'objectId')]
        RentalObject $object,
        #[MapEntity(id: 'id')]
        ObjectUnityProvider $record,
        ObjectUnityProviderService $records
    ): JsonResponse
    {
        $records->remove($object, $record);

        return new JsonResponse(null, 204);
    }
}
