<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\MeterInstallationInput;
use App\Entity\Meter;
use App\Service\MeterInstallationService;
use App\Service\MeterService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Приборы учёта')]
final class MeterInstallationController extends AbstractController
{
    #[Route('/api/meters/{id}/installation', requirements: ['id' => '\\d+'], methods: ['PUT'])]
    #[IsGranted('METER_INSTALL')]
    #[OA\Put(description: 'Администратор и технарь. Одно место: objectId или spaceId. Оба null снимают прибор, сохраняя показания. requireUninstalled запрещает привязку занятого прибора под блокировкой; utilityId проверяет коммуникацию. Просроченная поверка не запрещает установку. objectUnityProviderId — необязательный ID связи из списка поставщиков объекта; должен совпадать объект и тип коммуникации. Для Space не задаётся.', summary: 'Установить, перенести или снять прибор')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: MeterInstallationInput::class)))]
    #[OA\Response(response: 200, description: 'Сохранено', content: new OA\JsonContent(ref: '#/components/schemas/Meter'))]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    public function update(
        #[MapEntity(id: 'id')]
        Meter $meter,
        #[MapRequestPayload(acceptFormat: 'json')]
        MeterInstallationInput $input,
        MeterInstallationService $installations,
        MeterService $meters
    ): JsonResponse
    {
        $installations->save($meter, $input);

        return $this->json($meters->present($meter));
    }
}
