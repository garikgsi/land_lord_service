<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::CONTROLLER_ARGUMENTS)]
final class DeletedRecordListener
{
    public function __invoke(ControllerArgumentsEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) { return; }
        foreach ($event->getArguments() as $argument) {
            if (is_object($argument) && method_exists($argument, 'isDeleted') && $argument->isDeleted()) { throw new NotFoundHttpException('Запись удалена.'); }
        }
    }
}
