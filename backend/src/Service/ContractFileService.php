<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contract;
use App\Entity\ContractFile;
use App\Exception\InputException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ContractFileService
{
    private const MAX_FILE_BYTES = 20 * 1024 * 1024;

    public function __construct(
        private EntityManagerInterface $em,
        private Filesystem $files,
        #[Autowire('%kernel.project_dir%/var/contract-files')]
        private string $directory,
    ) {
    }

    public function present(ContractFile $file): array
    {
        return ['id' => $file->getId(), 'name' => $file->getOriginalName(), 'sizeBytes' => $file->getSizeBytes(), 'createdAt' => $file->getCreatedAt()->format(DATE_ATOM)];
    }

    public function list(Contract $contract): array
    {
        return ['items' => array_map($this->present(...), $this->em->getRepository(ContractFile::class)->findBy(['contract' => $contract], ['id' => 'DESC']))];
    }

    public function store(Contract $contract, ?UploadedFile $upload): ContractFile
    {
        if (null === $upload || !$upload->isValid()) {
            throw new InputException('file', 'Выберите файл для загрузки размером до 20 МБ.');
        }
        if ($upload->getSize() > self::MAX_FILE_BYTES) {
            throw new InputException('file', 'Размер файла не должен превышать 20 МБ.');
        }
        $storageName = bin2hex(random_bytes(24));
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $upload->getClientOriginalName());
        $file = new ContractFile($contract, '' === $name ? 'document' : $name, $storageName, 'application/octet-stream', $upload->getSize());
        $this->files->mkdir($this->directory, 0700);
        $upload->move($this->directory, $storageName);
        try {
            $this->em->persist($file);
            $this->em->flush();
        } catch (\Throwable $exception) {
            $this->files->remove($this->directory.'/'.$storageName);
            throw $exception;
        }

        return $file;
    }

    public function path(ContractFile $file): string
    {
        $path = $this->directory.'/'.$file->getStorageName();
        if (!is_file($path)) {
            throw new NotFoundHttpException('Файл не найден.');
        }

        return $path;
    }

    public function delete(ContractFile $file): void
    {
        $path = $this->directory.'/'.$file->getStorageName();
        $this->em->remove($file);
        $this->em->flush();
        $this->files->remove($path);
    }
}
