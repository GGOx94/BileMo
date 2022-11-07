<?php

namespace App\EventSubscriber;

use App\Exception\ApiValidationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExceptionSubscriber implements EventSubscriberInterface
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if($exception instanceof HttpException)
        {
            $data = ['status' => $exception->getStatusCode()];

            $data["message"] = match ($exception->getStatusCode()) {
                403 => "Access denied.",
                404 => "Resource not found.",
                405 => "Method not allowed on the targeted resource.",
                default => $exception->getMessage(),
            };

            if($exception instanceof ApiValidationException) {
                $data['errors'] = $exception->getFormattedErrors();
            }

            $event->setResponse(new JsonResponse($data));
            return;
        }

        $data = ['status' => 500, 'message' => "Internal server error."];
        $event->setResponse(new JsonResponse($data));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }
}
