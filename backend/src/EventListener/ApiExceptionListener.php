<?php

declare(strict_types=1);

namespace App\EventListener;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -20)]
final class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }
        $exception = $event->getThrowable();
        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : Response::HTTP_INTERNAL_SERVER_ERROR;
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];
        $message = $status >= 500 ? 'Не удалось выполнить операцию. Повторите позже.' : $exception->getMessage();
        if ($exception instanceof UniqueConstraintViolationException) {
            $status = Response::HTTP_CONFLICT;
            $message = 'Пользователь с такой почтой уже существует.';
        }
        if (Response::HTTP_SERVICE_UNAVAILABLE === $status) {
            $message = 'Отправка почты пока не настроена или временно недоступна. Обратитесь к администратору.';
        }
        $errors = [];
        $previous = $exception->getPrevious();
        if ($previous instanceof ValidationFailedException) {
            foreach ($previous->getViolations() as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }
            $message = 'Проверьте заполнение полей.';
        }
        $event->setResponse(new JsonResponse(['message' => $message, 'errors' => $errors], $status, $headers));
    }
}
