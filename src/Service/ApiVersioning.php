<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ApiVersioning
{
    private string $defaultVersion;
    private string $currentVersion;

    public function __construct(private readonly RequestStack $requestStack, ParameterBagInterface $parameterBag)
    {
        $this->defaultVersion = $parameterBag->get('default_api_version');

        $version = $this->defaultVersion;

        $headers = $this->requestStack->getCurrentRequest()->headers;
        $accepts = explode(';', $headers->get('accept'));

        foreach ($accepts as $accept) {
            if (str_contains($accept, 'version')) {
                $version = explode('=', $accept)[1];
                break;
            }
        }

        $this->currentVersion = $version;
    }

    public function isDefaultVersion(): bool
    {
        return $this->defaultVersion === $this->currentVersion;
    }

    public function getDefaultVersion(): string
    {
        return $this->defaultVersion;
    }

    public function getCurrentVersion(): string
    {
        return $this->currentVersion;
    }

    public function testExpr(): bool
    {
        return true;
    }
}
