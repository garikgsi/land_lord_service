<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\SpaceAvailabilityQuery;
use App\Dto\SpaceInput;
use App\Entity\RentalObject;
use App\Entity\Space;
use App\Service\SpaceService;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Помещения')]
final class SpaceController extends AbstractController
{
    #[Route('/api/objects/{id}/spaces', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[OA\Get(description: 'Все помещения объекта, включая недействующие. Порядок: active DESC, id DESC; действующие первыми. active учитывает занятость и резерв по оформленным будущим договорам. Площадь Space учитывается один раз.', summary: 'История помещений объекта и остаток площади')]
    #[OA\Response(response: 200, description: 'Все помещения объекта, включая исторические', content: new OA\JsonContent(ref: '#/components/schemas/SpaceList'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    public function list(
        #[MapEntity(id: 'id')]
        RentalObject $object,
        SpaceService $spaces
    ): JsonResponse
    {
        return $this->json($spaces->forObject($object));
    }

    #[Route('/api/objects/{id}/availability', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[OA\Get(description: 'Текущий остаток площади без кэширования. contractId исключает резерв только указанного договора: если тот же Space резервируют другие договоры, его площадь остаётся занятой. Используется для проверки изменений черновика; окончательная проверка выполняется при сохранении под блокировкой объекта.', summary: 'Доступная площадь для создания или изменения договора')]
    #[OA\Response(response: 200, description: 'Остаток площади и серверная дата', content: new OA\JsonContent(ref: '#/components/schemas/SpaceAvailability'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    public function availability(
        #[MapEntity(id: 'id')]
        RentalObject $object,
        #[MapQueryString]
        SpaceAvailabilityQuery $input,
        SpaceService $spaces
    ): JsonResponse
    {
        return $this->json($spaces->area($object, $input->contractId));
    }

    #[Route('/api/spaces/{id}', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[OA\Get(summary: 'Space show')]
    #[OA\Response(response: 200, description: 'Помещение', content: new OA\JsonContent(ref: '#/components/schemas/Space'))]
    public function show(
        #[MapEntity(id: 'id')]
        Space $space,
        SpaceService $spaces
    ): JsonResponse
    {
        return $this->json($spaces->present($space));
    }

    #[Route('/api/spaces/{id}', requirements: ['id' => '\\d+'], methods: ['PUT'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\Put(description: 'Меняется только название. Площадь и объект неизменны; создание Space доступно только с первым договором.', summary: 'Изменить название помещения')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SpaceInput'))]
    #[OA\Response(response: 200, description: 'Сохранено', content: new OA\JsonContent(ref: '#/components/schemas/Space'))]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    public function update(
        #[MapEntity(id: 'id')]
        Space $space,
        #[MapRequestPayload(acceptFormat: 'json')]
        SpaceInput $input,
        SpaceService $spaces
    ): JsonResponse
    {
        return $this->json($spaces->present($spaces->rename($space, $input)));
    }
}
