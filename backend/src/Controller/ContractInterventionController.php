<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ContractInterventionListQuery;
use App\Service\ContractInterventionService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Договоры')]
final class ContractInterventionController extends AbstractController
{
    #[Route('/api/contracts/interventions', methods: ['GET'])]
    #[OA\Get(description: 'Неисполненные события только подписанных договоров аренды (индексация и окончание без действительного последующего договора) и сроки продления коммунальных договоров. action: all/indexation/expiry, contractType: all/rental/utility. Фильтры и query по номеру, объекту, помещению, арендатору или поставщику объединяются до count/пагинации. Даты по возрастанию: просроченные, затем ближайшие предстоящие; при одинаковой дате ID по убыванию. Одно событие на строку, по 20 строк на страницу. Правила арендной индексации и сортировки во вкладке объекта сохранены.', summary: 'Договоры: требуют внимания')]
    #[OA\Response(response: 200, description: 'События договоров', content: new OA\JsonContent(ref: '#/components/schemas/ContractInterventionList'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    public function list(
        #[MapQueryString(validationFailedStatusCode: 422)]
        ContractInterventionListQuery $input,
        ContractInterventionService $interventions
    ): JsonResponse
    {
        return $this->json($interventions->list($input));
    }
}
