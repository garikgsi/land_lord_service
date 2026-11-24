<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Contract;
use App\Entity\ContractFile;
use App\Exception\InputException;
use App\Service\ContractFileService;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Договоры: файлы')]
final class ContractFileController extends AbstractController
{
    #[Route('/api/contracts/{id}/files', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[OA\Get(summary: 'ContractFile list')]
    #[OA\Response(response: 200, description: 'Вложения', content: new OA\JsonContent(ref: '#/components/schemas/ContractFileList'))]
    public function list(
        #[MapEntity(id: 'id')]
        Contract $contract,
        ContractFileService $files
    ): JsonResponse
    {
        return $this->json($files->list($contract));
    }

    #[Route('/api/contracts/{id}/files', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\Post(summary: 'Прикрепить любой файл размером до 20 МБ')]
    #[OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(ref: '#/components/schemas/ContractFileUpload')))]
    #[OA\Response(response: 201, description: 'Файл сохранён', content: new OA\JsonContent(ref: '#/components/schemas/ContractFile'))]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    public function upload(
        #[MapEntity(id: 'id')]
        Contract $contract,
        Request $request,
        ContractFileService $files
    ): JsonResponse
    {
        $upload = $request->files->get('file');
        if (!$upload instanceof UploadedFile) {
            throw new InputException('file', 'Выберите один файл для загрузки.');
        }

        return $this->json($files->present($files->store($contract, $upload)), 201);
    }

    #[Route('/api/contract-files/{id}', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[OA\Get(summary: 'Скачать вложение с Bearer-авторизацией')]
    #[OA\Response(response: 200, description: 'Содержимое файла, Content-Disposition: attachment')]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 401)]
    public function download(
        #[MapEntity(id: 'id')]
        ContractFile $file,
        ContractFileService $files
    ): BinaryFileResponse
    {
        $response = new BinaryFileResponse($files->path($file));
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file->getOriginalName());
        $response->setPrivate();

        return $response;
    }

    #[Route('/api/contract-files/{id}', requirements: ['id' => '\\d+'], methods: ['DELETE'])]
    #[IsGranted('RENTAL_REGISTRY_EDIT')]
    #[OA\Delete(summary: 'Удалить вложение; юрист или администратор')]
    #[OA\Response(response: 204, description: 'Удалено')]
    #[OA\Response(ref: '#/components/responses/Forbidden', response: 403)]
    public function delete(
        #[MapEntity(id: 'id')]
        ContractFile $file,
        ContractFileService $files
    ): JsonResponse
    {
        $files->delete($file);

        return $this->json(null, 204);
    }
}
