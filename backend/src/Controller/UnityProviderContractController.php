<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\UnityProviderContractInput;
use App\Dto\UnityProviderContractListQuery;
use App\Entity\RentalObject;
use App\Entity\UnityProviderContract;
use App\Service\UnityProviderContractService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Договоры коммунальных поставщиков')]
final class UnityProviderContractController extends AbstractController
{
    #[Route('/api/unity-provider-contracts', methods: ['GET'])]
    #[OA\Get(description: 'Фильтры unityProviderId, unityId, status (all/active/suspended/finished), withMeters объединяются до пагинации. withMeters учитывает только установленные общие приборы этого объекта и поставщика; при unityId прибор должен относиться к выбранной коммуникации. Набор коммуникаций без ограничений охватывает все типы.', summary: 'Договоры коммунальных поставщиков, с сохранением прежних')]
    #[OA\Response(response: 200, description: 'Договоры', content: new OA\JsonContent(ref: '#/components/schemas/UnityProviderContractList'))]
    public function list(
        #[MapQueryString(validationFailedStatusCode: 422)]
        UnityProviderContractListQuery $input,
        UnityProviderContractService $contracts
    ): JsonResponse
    {
        return $this->json($contracts->list($input));
    }

    #[Route('/api/objects/{objectId}/unity-provider-contracts', requirements: ['objectId' => '\d+'], methods: ['GET'])]
    #[OA\Get(description: 'Те же объединяемые фильтры unityProviderId, unityId, status, withMeters в пределах указанного объекта.', summary: 'Договоры коммунальных поставщиков объекта')]
    #[OA\Response(response: 200, description: 'Договоры', content: new OA\JsonContent(ref: '#/components/schemas/UnityProviderContractList'))]
    public function forObject(
        #[MapEntity(id: 'objectId')]
        RentalObject $object,
        #[MapQueryString(validationFailedStatusCode: 422)]
        UnityProviderContractListQuery $input,
        UnityProviderContractService $contracts
    ): JsonResponse
    {
        return $this->json($contracts->list($input, $object->getId()));
    }

    #[Route('/api/unity-provider-contracts/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(summary: 'Карточка договора коммунального поставщика')]
    #[OA\Response(response: 200, description: 'Договор', content: new OA\JsonContent(ref: '#/components/schemas/UnityProviderContract'))]
    public function show(
        #[MapEntity(id: 'id')]
        UnityProviderContract $contract,
        UnityProviderContractService $contracts
    ): JsonResponse
    {
        return $this->json($contracts->present($contract));
    }

    #[Route('/api/unity-provider-contracts', methods: ['POST'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\Post(description: 'Один поставщик, одна коммуникация в unityIds, необязательный meterId. Свободный прибор устанавливается на объекте атомарно с сохранением договора. Привязку/снятие прибора выполняют администратор и технарь с правом изменения договора.', summary: 'Создать договор коммунального поставщика')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: UnityProviderContractInput::class)))]
    #[OA\Response(response: 201, description: 'Договор', content: new OA\JsonContent(ref: '#/components/schemas/UnityProviderContract'))]
    public function create(
        #[MapRequestPayload(acceptFormat: 'json')]
        UnityProviderContractInput $input,
        UnityProviderContractService $contracts
    ): JsonResponse
    {
        if (null !== $input->meterId) {
            $this->denyAccessUnlessGranted('METER_INSTALL');
        }

        return $this->json($contracts->present($contracts->save($input)), 201);
    }

    #[Route('/api/unity-provider-contracts/{id}', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\Put(summary: 'Изменить договор, включая признак приостановки')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: UnityProviderContractInput::class)))]
    #[OA\Response(response: 200, description: 'Договор', content: new OA\JsonContent(ref: '#/components/schemas/UnityProviderContract'))]
    public function update(
        #[MapEntity(id: 'id')]
        UnityProviderContract $contract,
        #[MapRequestPayload(acceptFormat: 'json')]
        UnityProviderContractInput $input,
        UnityProviderContractService $contracts
    ): JsonResponse
    {
        if ($input->meterId !== $contract->getMeter()?->getId() || (null !== $input->meterId && ($input->objectId !== $contract->getObject()->getId() || $input->unityProviderId !== $contract->getUnityProvider()->getId() || $input->unityIds !== [$contract->getMeter()?->getUtility()->getId()]))) {
            $this->denyAccessUnlessGranted('METER_INSTALL');
        }

        return $this->json($contracts->present($contracts->save($input, $contract)));
    }

    #[Route('/api/unity-provider-contracts/{id}', requirements: ['id' => '\\d+'], methods: ['DELETE'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\Delete(description: 'Ставит пометку удаления без физического удаления строки. Запись исчезает из рабочих списков; связи и история сохраняются.', summary: 'Пометить запись удалённой')]
    #[OA\Response(response: 204, description: 'Поставлена пометка удаления')]
    #[OA\Response(ref: '#/components/responses/Conflict', response: 409)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    public function remove(
        #[MapEntity(id: 'id')]
        UnityProviderContract $record,
        UnityProviderContractService $records
    ): \Symfony\Component\HttpFoundation\Response
    {
        $records->remove($record);

        return new \Symfony\Component\HttpFoundation\Response(status: 204);
    }
}
