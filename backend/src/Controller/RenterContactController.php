<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\RegistryListQuery;
use App\Dto\RenterContactInput;
use App\Entity\Renter;
use App\Entity\RenterContact;
use App\Service\RenterContactService;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/renters/{renterId}/contacts', requirements: ['renterId' => '\d+'])]
#[OA\Tag(name: 'Контакты арендаторов')]
final class RenterContactController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    #[OA\Get(description: 'Контакты только выбранного арендатора. query — имя, фамилия, отчество, email или телефон; страницы по 20 записей.', summary: 'Контакты арендатора')]
    #[OA\Response(response: 200, description: 'Контакты', content: new OA\JsonContent(ref: '#/components/schemas/RenterContactList'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    public function list(
        #[MapEntity(id: 'renterId')]
        Renter $renter,
        #[MapQueryString]
        RegistryListQuery $input,
        RenterContactService $contacts
    ): JsonResponse
    {
        return $this->json($contacts->list($renter, $input));
    }

    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(summary: 'Контакт выбранного арендатора')]
    #[OA\Response(response: 200, description: 'Контакт', content: new OA\JsonContent(ref: '#/components/schemas/RenterContact'))]
    #[OA\Response(response: 404, description: 'Контакт не найден у этого арендатора')]
    public function show(
        #[MapEntity(id: 'renterId')]
        Renter $renter,
        #[MapEntity(id: 'id')]
        RenterContact $contact,
        RenterContactService $contacts
    ): JsonResponse
    {
        $contacts->requireRenter($contact, $renter);

        return $this->json($contacts->present($contact));
    }

    #[Route('', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Post(description: 'Обязательно только имя. Остальные поля необязательны; телефон +7, email проверяется. Любой авторизованный пользователь.', summary: 'Добавить контакт')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RenterContactInput'))]
    #[OA\Response(response: 201, description: 'Контакт', content: new OA\JsonContent(ref: '#/components/schemas/RenterContact'))]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    public function create(
        #[MapEntity(id: 'renterId')]
        Renter $renter,
        #[MapRequestPayload(acceptFormat: 'json')]
        RenterContactInput $input,
        RenterContactService $contacts
    ): JsonResponse
    {
        return $this->json($contacts->present($contacts->save($renter, $input)), 201);
    }

    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Put(description: 'Полная замена полей контакта; renterId неизменен. Прежняя версия сохраняется. Любой авторизованный пользователь.', summary: 'Изменить контакт')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RenterContactInput'))]
    #[OA\Response(response: 200, description: 'Контакт', content: new OA\JsonContent(ref: '#/components/schemas/RenterContact'))]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    #[OA\Response(response: 404, description: 'Контакт не найден у этого арендатора')]
    public function update(
        #[MapEntity(id: 'renterId')]
        Renter $renter,
        #[MapEntity(id: 'id')]
        RenterContact $contact,
        #[MapRequestPayload(acceptFormat: 'json')]
        RenterContactInput $input,
        RenterContactService $contacts
    ): JsonResponse
    {
        return $this->json($contacts->present($contacts->save($renter, $input, $contact)));
    }
}
