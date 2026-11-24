<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ContractAmendmentInput;
use App\Dto\ContractAnnulmentInput;
use App\Dto\ContractIndexationInput;
use App\Dto\ContractInput;
use App\Dto\ContractListQuery;
use App\Entity\Contract;
use App\Service\ContractService;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/contracts')]
#[OA\Tag(name: 'Договоры')]
final class ContractController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    #[OA\Get(description: 'Пагинация по 20 записей. Фильтры status (all, active, upcoming, finished, cancelled), objectId, renterId, spaceId, expiryFrom/expiryTo (границы периода действия: пересечение с арендой хотя бы на один день, включительно; окончание учитывает дату аннулирования, отменённые до начала договоры исключены); query — номер, арендатор, помещение. Сортировка expiryDate, id.', summary: 'Реестр договоров')]
    #[OA\Response(response: 200, description: 'Список', content: new OA\JsonContent(ref: '#/components/schemas/ContractList'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    public function list(
        #[MapQueryString]
        ContractListQuery $input,
        ContractService $contracts
    ): JsonResponse
    {
        return $this->json($contracts->list($input));
    }

    #[Route('/{id}', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[OA\Get(summary: 'Contract show')]
    #[OA\Response(response: 200, description: 'Договор', content: new OA\JsonContent(ref: '#/components/schemas/Contract'))]
    public function show(
        #[MapEntity(id: 'id')]
        Contract $contract,
        ContractService $contracts
    ): JsonResponse
    {
        return $this->json($contracts->present($contract));
    }

    #[Route('/signing-preview', methods: ['POST'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\Post(description: 'Точный расчёт по месяцам до текущего месяца включительно, с начала аренды, не ранее декабря 2026. Не создаёт договор и начисления. confirmation передаётся в последующее создание; изменение условий или даты расчёта требует нового подтверждения.', summary: 'Расчёт перед созданием подписанного договора')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ContractInput'))]
    #[OA\Response(response: 200, description: 'Расчёт и подтверждение', content: new OA\JsonContent(ref: '#/components/schemas/ContractSigningPreview'))]
    public function creationSigningPreview(
        #[MapRequestPayload(acceptFormat: 'json')]
        ContractInput $input,
        ContractService $contracts
    ): JsonResponse
    {
        return $this->json($contracts->signingPreview($input));
    }

    #[Route('/{id}/signing-preview', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\Post(description: 'Расчёт не меняет черновик. При подтверждённом сохранении подписание и начисления за все месяцы аренды до текущего выполняются в одной транзакции. Неполные месяцы — пропорционально дням, округление HALF_UP до копеек.', summary: 'Расчёт перед подписанием черновика')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ContractInput'))]
    #[OA\Response(response: 200, description: 'Расчёт и подтверждение', content: new OA\JsonContent(ref: '#/components/schemas/ContractSigningPreview'))]
    public function signingPreview(
        #[MapEntity(id: 'id')]
        Contract $contract,
        #[MapRequestPayload(acceptFormat: 'json')]
        ContractInput $input,
        ContractService $contracts
    ): JsonResponse
    {
        return $this->json($contracts->signingPreview($input, $contract));
    }

    #[Route('', methods: ['POST'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\Post(description: 'Первый договор атомарно создаёт Space; square обязательна, spaceName генерируется из площади и арендатора. previousContractId создаёт следующий договор на прежнем Space без разрыва, пока он не освобождён. Оба типа договоров занимают площадь. isSigned по умолчанию true; false создаёт редактируемый черновик без истории и начислений.', summary: 'Создать договор вместе с помещением или перезаключить')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ContractInput'))]
    #[OA\Response(response: 201, description: 'Создан', content: new OA\JsonContent(ref: '#/components/schemas/Contract'))]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/Conflict', response: 409)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    public function create(
        #[MapRequestPayload(acceptFormat: 'json')]
        ContractInput $input,
        ContractService $contracts
    ): JsonResponse
    {
        return $this->json($contracts->present($contracts->save($input)), 201);
    }

    #[Route('/{id}', requirements: ['id' => '\\d+'], methods: ['PUT'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\Put(description: 'Полная замена реквизитов. До подписания допустимо изменение объекта и площади с переносом резерва и проверкой остатка. Для общего Space создаётся отдельный Space, сохраняя другие договоры. После подписания объект и площадь неизменны. Черновые изменения не архивируются. Подписание isSigned=true необратимо; попытка снять подпись возвращает 409.', summary: 'Исправить договор')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ContractInput'))]
    #[OA\Response(response: 200, description: 'Сохранён', content: new OA\JsonContent(ref: '#/components/schemas/Contract'))]
    #[OA\Response(ref: '#/components/responses/Conflict', response: 409)]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    public function update(
        #[MapEntity(id: 'id')]
        Contract $contract,
        #[MapRequestPayload(acceptFormat: 'json')]
        ContractInput $input,
        ContractService $contracts
    ): JsonResponse
    {
        return $this->json($contracts->present($contracts->save($input, $contract)));
    }

    #[Route('/{id}/annulment', requirements: ['id' => '\\d+'], methods: ['PUT'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\Put(description: 'Дата включена в аренду; площадь освобождается на следующий день. До начала аренды договор отменяется без занятости площади.', summary: 'Аннулировать указанной датой')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ContractAnnulmentInput'))]
    #[OA\Response(response: 200, description: 'Договор', content: new OA\JsonContent(ref: '#/components/schemas/Contract'))]
    #[OA\Response(ref: '#/components/responses/Conflict', response: 409)]
    public function annul(
        #[MapEntity(id: 'id')]
        Contract $contract,
        #[MapRequestPayload(acceptFormat: 'json')]
        ContractAnnulmentInput $input,
        ContractService $contracts
    ): JsonResponse
    {
        return $this->json($contracts->present($contracts->annul($contract, new \DateTimeImmutable($input->annulmentDate))));
    }

    #[Route('/{id}/amendment', requirements: ['id' => '\\d+'], methods: ['PUT'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\Put(description: 'Изменяются только арендатор, окончание аренды и ежемесячный платёж. Изменения действуют сразу; история сохраняется только для подписанного договора.', summary: 'Изменить условия договора')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ContractAmendmentInput'))]
    #[OA\Response(response: 200, description: 'Договор', content: new OA\JsonContent(ref: '#/components/schemas/Contract'))]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/Conflict', response: 409)]
    public function amend(
        #[MapEntity(id: 'id')]
        Contract $contract,
        #[MapRequestPayload(acceptFormat: 'json')]
        ContractAmendmentInput $input,
        ContractService $contracts
    ): JsonResponse
    {
        return $this->json($contracts->present($contracts->amend($contract, $input)));
    }

    #[Route('/{id}/indexation', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\Post(description: 'Сумма меняется сразу. valid_from — время сохранения; increaseDate — реквизит и начало отсчёта напоминаний. Номер и остальные реквизиты договора не меняются. Деньги округляются HALF_UP до копеек, обратный процент — до 6 знаков. Периодичность создаёт только напоминания, не автоматические повышения.', summary: 'Индексировать договор с сохранением истории')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ContractIndexationInput'))]
    #[OA\Response(response: 200, description: 'Договор', content: new OA\JsonContent(ref: '#/components/schemas/Contract'))]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/Conflict', response: 409)]
    public function index(
        #[MapEntity(id: 'id')]
        Contract $contract,
        #[MapRequestPayload(acceptFormat: 'json')]
        ContractIndexationInput $input,
        ContractService $contracts
    ): JsonResponse
    {
        return $this->json($contracts->present($contracts->index($contract, $input)));
    }

    #[Route('/{id}', requirements: ['id' => '\\d+'], methods: ['DELETE'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\Delete(description: 'Помечает неподписанный договор удалённым; если не осталось других договоров, помещение тоже помечается удалённым. Площадь освобождается. Подписанный договор — 409. Строки и история сохраняются.', summary: 'Пометить черновик удалённым')]
    #[OA\Response(response: 204, description: 'Помечено удалённым')]
    #[OA\Response(ref: '#/components/responses/Conflict', response: 409)]
    public function remove(
        #[MapEntity(id: 'id')]
        Contract $contract,
        ContractService $contracts
    ): \Symfony\Component\HttpFoundation\Response
    {
        $contracts->remove($contract);

        return new \Symfony\Component\HttpFoundation\Response(status: 204);
    }
}
