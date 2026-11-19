<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApplicationController extends AbstractController
{
    #[Route('/', name: 'application', methods: ['GET'])]
    #[Route('/index.html', name: 'application_index', methods: ['GET'])]
    public function __invoke(
        Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/public/ui/index.html')] string $indexPath,
    ): Response {
        return new Response($filesystem->readFile($indexPath));
    }
}
