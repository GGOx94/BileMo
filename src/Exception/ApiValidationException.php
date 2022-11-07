<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class ApiValidationException extends BadRequestHttpException
{
    private ConstraintViolationListInterface $errList;

    public function __construct(ConstraintViolationListInterface $list)
    {
        parent::__construct(sprintf('Validation failed with %d error(s).', \count($list)));
        $this->errList = $list;
    }

    public function getFormattedErrors() : array
    {
        $results = [];
        foreach ($this->errList as $err) {
            $results[$err->getPropertyPath()] = $err->getMessage();
        }

        return $results;
    }
}