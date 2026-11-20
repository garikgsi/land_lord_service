<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\Role;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Справочники')]
final class RoleController extends AbstractController
{
    #[Route('/api/roles', name: 'api_roles_list', methods: ['GET'])]
    #[OA\Get(description: 'Доступен авторизованным пользователям. Названия и описания ролей; assignable учитывает права текущего пользователя. ROLE_SUPER_ADMIN никогда не назначается. Назначение ROLE_ADMIN доступно только суперадминистратору. Описания предметных ролей обозначают их назначение, а не готовую матрицу будущих модулей.', summary: 'Справочник ролей')]
    #[OA\Response(response: 200, description: 'Role catalogue', content: new OA\JsonContent(ref: '#/components/schemas/RoleList'))]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    #[OA\Response(ref: '#/components/responses/ServerError', response: 'default')]
    public function list(): JsonResponse
    {
        $labels = Role::labels();
        $items = [];

        foreach (Role::cases() as $role) {
            $items[] = [
                'code' => $role->value,
                'label' => $labels[$role->value],
                'description' => $role->description(),
                'assignable' => match ($role) {
                    Role::SuperAdmin => false,
                    Role::Admin => $this->isGranted(Role::SuperAdmin->value),
                    default => $this->isGranted(Role::Admin->value),
                },
            ];
        }

        $response = $this->json(['items' => $items]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
