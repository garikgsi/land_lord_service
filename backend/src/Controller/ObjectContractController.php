<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\RegistryListQuery;
use App\Entity\RentalObject;
use App\Service\ContractService;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Договоры')]
final class ObjectContractController extends AbstractController
{
    #[Route('/api/objects/{id}/contracts', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[OA\Get(description: 'Все договоры арендаторов объекта, включая завершённые, отменённые и неподписанные. Каждый договор показан один раз. Сортировка до пагинации: ближайшая невыполненная дата вмешательства по возрастанию, включая просроченные. Окончание с последующим договором и выполненная индексация исключены из событий. Договоры без оставшихся событий идут в конце. При одинаковой дате ID по убыванию. Общая логика с реестром вмешательства. Пагинация по 20, параметр page. Удалённые записи исключены. Просмотр всем авторизованным ролям.', summary: 'Арендаторы объекта по необходимости вмешательства')]
    #[OA\Response(response: 200, description: 'Договоры арендаторов объекта', content: new OA\JsonContent(ref: '#/components/schemas/ContractList'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    #[OA\Response(response: 404, description: 'Объект не найден')]
    public function list(
        #[MapEntity(id: 'id')]
        RentalObject $object,
        #[MapQueryString]
        RegistryListQuery $input,
        ContractService $contracts
    ): JsonResponse
    {
        return $this->json($contracts->forObject($object, $input));
    }
}
