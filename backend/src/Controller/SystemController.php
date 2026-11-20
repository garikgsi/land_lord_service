<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\DatabaseReadiness;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SystemController extends AbstractController
{
    #[Route('/server/status', name: 'server_status', methods: ['GET'])]
    public function __invoke(
        DatabaseReadiness $databaseReadiness,
        Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/public/ui/.vite/manifest.json')]
        string $manifestPath
    ): Response
    {
        $available = $databaseReadiness->isAvailable();

        return $this->render('system/status.html.twig', [
            'available' => $available,
            'stylesheets' => json_decode($filesystem->readFile($manifestPath), true, 512, JSON_THROW_ON_ERROR)['index.html']['css'] ?? [],
        ], new Response(status: $available ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE));
    }
}
