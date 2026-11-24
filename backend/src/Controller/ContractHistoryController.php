<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\RegistryListQuery;
use App\Entity\Contract;
use App\Service\ContractHistoryService;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'История договоров')]
final class ContractHistoryController extends AbstractController
{
    #[Route('/api/contracts/{id}/history', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[OA\Get(description: 'Для неподписанного договора — пустой список. После подписания: архив полных прежних подписанных состояний плюс текущее состояние. Черновики не архивируются. Первая запись — создание; начальная запись не дублируется в архивной таблице. Границы [validFrom, validTo); по 20 записей, новые первыми.', summary: 'История договора')]
    #[OA\Response(response: 200, description: 'История', content: new OA\JsonContent(ref: '#/components/schemas/ContractHistoryList'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    public function list(
        #[MapEntity(id: 'id')]
        Contract $contract,
        #[MapQueryString]
        RegistryListQuery $input,
        ContractHistoryService $changes
    ): JsonResponse
    {
        return $this->json($changes->list($contract, $input));
    }
}
