<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class UserAvatarStorage
{
    public function __construct(
        private EntityManagerInterface $em,
        private Filesystem $files,
        #[Autowire('%kernel.project_dir%/var/avatars')]
        private string $directory,
    ) {
    }

    public function path(User $user): string
    {
        if (null === $user->getAvatar()) {
            throw new NotFoundHttpException();
        }

        return $this->directory.'/'.$user->getAvatar();
    }

    public function store(User $user, UploadedFile $avatar): void
    {
        $extension = match ($avatar->getMimeType()) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', default => throw new \LogicException('Неподдерживаемый формат изображения.'),
        };
        $filename = bin2hex(random_bytes(24)).'.'.$extension;
        $this->files->mkdir($this->directory, 0700);
        $avatar->move($this->directory, $filename);
        $previous = $user->getAvatar();
        try {
            $user->setAvatar($filename);
            $this->em->flush();
        } catch (\Throwable $exception) {
            $this->files->remove($this->directory.'/'.$filename);
            throw $exception;
        }
        if (null !== $previous) {
            $this->files->remove($this->directory.'/'.$previous);
        }
    }

    public function delete(User $user): void
    {
        $previous = $user->getAvatar();
        $user->setAvatar(null);
        $this->em->flush();
        if (null !== $previous) {
            $this->files->remove($this->directory.'/'.$previous);
        }
    }
}
