<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\RegistryListQuery;
use App\Service\ContractService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Аналитика')]
final class LegalDashboardController extends AbstractController
{
    #[Route('/api/dashboard/legal/contracts', methods: ['GET'])]
    #[IsGranted('LEGAL_DASHBOARD_VIEW')]
    #[OA\Get(description: 'Все действующие договоры, 20 на страницу. Сортировка по объекту, арендатору и ID. Доступ юристу, директору и администратору.', summary: 'Аренда на стартовом экране')]
    #[OA\Response(response: 200, description: 'Договоры', content: new OA\JsonContent(ref: '#/components/schemas/ContractList'))]
    public function contracts(
        #[MapQueryString]
        RegistryListQuery $input,
        ContractService $contracts
    ): JsonResponse
    {
        return $this->json($contracts->legalList($input));
    }
}
