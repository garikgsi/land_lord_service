<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Security\UserManagementAccess;
use App\Service\UserAvatarStorage;
use App\Service\UserInvitationService;
use App\Service\UserView;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Tag(name: 'Фото пользователей')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserAvatarStorage $avatars
    )
    {
    }

    #[Route('/api/users/{id}/avatar', name: 'user_avatar', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(description: 'Себе или пользователю с административными правами. Бинарный ответ, приватный кеш без хранения.', summary: 'Фото пользователя')]
    #[OA\Response(response: 200, description: 'Success', content: [new OA\MediaType(mediaType: 'image/jpeg', schema: new OA\Schema(type: 'string', format: 'binary')), new OA\MediaType(mediaType: 'image/png', schema: new OA\Schema(type: 'string', format: 'binary')), new OA\MediaType(mediaType: 'image/webp', schema: new OA\Schema(type: 'string', format: 'binary'))])]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    #[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
    #[OA\Response(ref: '#/components/responses/ServerError', response: 'default')]
    public function avatar(
        #[MapEntity(id: 'id')]
        User $user,
        #[CurrentUser]
        User $current
    ): BinaryFileResponse
    {
        if (!$current->isAdmin() && $current->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        return $this->file($this->avatars->path($user))->setPrivate()->setMaxAge(0);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/api/admin/users/{id}/avatar', name: 'admin_users_avatar', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[OA\Post(description: 'ROLE_ADMIN и право изменения этого пользователя. multipart/form-data, поле avatar. Сохранение фото независимо от формы профиля.', summary: 'Сразу сохранить фото пользователя')]
    #[OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(ref: '#/components/schemas/AvatarUpload')))]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/User'))]
    #[OA\Response(ref: '#/components/responses/BadRequest', response: 400)]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    #[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/ServerError', response: 'default')]
    public function uploadAvatar(
        #[MapEntity(id: 'id')]
        User $user,
        #[MapUploadedFile(constraints: new Assert\Image(maxSize: '2M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'], maxWidth: 4096, maxHeight: 4096), name: 'avatar')]
        UploadedFile $avatar,
        UserManagementAccess $access,
        #[CurrentUser]
        User $actor,
        UserView $view
    ): JsonResponse
    {
        $access->assertCanEdit($actor, $user);
        $this->avatars->store($user, $avatar);

        return $this->json($view->present($user));
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/api/admin/users/{id}/avatar', name: 'admin_users_avatar_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(description: 'ROLE_ADMIN и право изменения этого пользователя. Возвращает 204, фото удаляется сразу.', summary: 'Удалить фото пользователя')]
    #[OA\Response(response: 204, description: 'No content')]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    #[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
    #[OA\Response(ref: '#/components/responses/ServerError', response: 'default')]
    public function deleteAvatar(
        #[MapEntity(id: 'id')]
        User $user,
        UserManagementAccess $access,
        #[CurrentUser]
        User $actor
    ): Response
    {
        $access->assertCanEdit($actor, $user);
        $this->avatars->delete($user);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/auth/invitation/avatar', name: 'invitation_avatar_read', methods: ['GET'])]
    #[OA\Get(description: 'До активации, токен в X-Invitation-Token. Аватар не найден — 404. Bearer не используется.', summary: 'Фото по приглашению', security: [['InvitationToken' => []]])]
    #[OA\Response(response: 200, description: 'Success', content: [new OA\MediaType(mediaType: 'image/jpeg', schema: new OA\Schema(type: 'string', format: 'binary')), new OA\MediaType(mediaType: 'image/png', schema: new OA\Schema(type: 'string', format: 'binary')), new OA\MediaType(mediaType: 'image/webp', schema: new OA\Schema(type: 'string', format: 'binary'))])]
    #[OA\Response(ref: '#/components/responses/BadRequest', response: 400)]
    #[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
    #[OA\Response(ref: '#/components/responses/ServerError', response: 'default')]
    public function invitationAvatar(
        Request $request,
        UserInvitationService $invitations
    ): BinaryFileResponse
    {
        return $this->file($this->avatars->path($invitations->userFromToken($request->headers->get('X-Invitation-Token', ''))))->setPrivate()->setMaxAge(0);
    }

    #[Route('/api/auth/invitation/avatar', name: 'invitation_avatar_upload', methods: ['POST'])]
    #[OA\Post(description: 'X-Invitation-Token и multipart/form-data с полем avatar. Загрузка фото не активирует пользователя.', summary: 'Сразу сохранить фото до активации', security: [['InvitationToken' => []]])]
    #[OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(ref: '#/components/schemas/AvatarUpload')))]
    #[OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(ref: '#/components/schemas/Message'))]
    #[OA\Response(ref: '#/components/responses/BadRequest', response: 400)]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/ServerError', response: 'default')]
    public function uploadInvitationAvatar(
        Request $request,
        #[MapUploadedFile(constraints: new Assert\Image(maxSize: '2M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'], maxWidth: 4096, maxHeight: 4096), name: 'avatar')]
        UploadedFile $avatar,
        UserInvitationService $invitations
    ): JsonResponse
    {
        $invitations->withPendingUser($request->headers->get('X-Invitation-Token', ''), fn (User $user) => $this->avatars->store($user, $avatar));

        return $this->json(['message' => 'Фото сохранено.']);
    }

    #[Route('/api/auth/invitation/avatar', name: 'invitation_avatar_delete', methods: ['DELETE'])]
    #[OA\Delete(description: 'X-Invitation-Token. Удаление фото не активирует пользователя.', summary: 'Удалить фото до активации', security: [['InvitationToken' => []]])]
    #[OA\Response(response: 204, description: 'No content')]
    #[OA\Response(ref: '#/components/responses/BadRequest', response: 400)]
    #[OA\Response(ref: '#/components/responses/ServerError', response: 'default')]
    public function deleteInvitationAvatar(
        Request $request,
        UserInvitationService $invitations
    ): Response
    {
        $invitations->withPendingUser($request->headers->get('X-Invitation-Token', ''), fn (User $user) => $this->avatars->delete($user));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
