<?php

declare(strict_types=1);

namespace App\Controller;

use Nelmio\ApiDocBundle\Controller\DocumentationController;
use Nelmio\ApiDocBundle\Controller\SwaggerUiController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiDocumentationController
{
    #[Route('/server/api', name: 'api_documentation', methods: ['GET'])]
    public function index(
        Request $request,
        #[Autowire(service: 'nelmio_api_doc.controller.swagger_ui')]
        SwaggerUiController $documentation
    ): Response
    {
        return $documentation($request);
    }

    #[Route('/server/api/openapi.json', name: 'api_documentation_json', methods: ['GET'])]
    public function json(
        Request $request,
        #[Autowire(service: 'nelmio_api_doc.controller.swagger')]
        DocumentationController $documentation
    ): JsonResponse
    {
        return $documentation($request);
    }
}
