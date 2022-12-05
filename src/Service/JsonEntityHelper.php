<?php

namespace App\Service;

use App\Exception\ApiValidationException;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\Since;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class JsonEntityHelper
{
    private DeserializationContext $context;

    public function __construct(
        private readonly ValidatorInterface         $validator,
        private readonly SerializerInterface        $serializer,
        private readonly ApiVersioning              $versioning
    ) {}

    public function serialize(mixed $data, array $groups) : string
    {
        $context = SerializationContext::create()->setGroups($groups)->setVersion($this->versioning->getCurrentVersion());
        return $this->serializer->serialize($data, 'json', $context);
    }

    public function updateEntity(string $json, object $baseEntity, array $groups): object
    {
        $updatedEntity = $this->deserializeAndValidate($json, $baseEntity::class, $groups);
        $this->updateProperties($baseEntity, $updatedEntity, $this->context);

        return $baseEntity;
    }

    public function deserializeAndValidate(string $json, string $entityClass, array $groups) : object
    {
        $this->context = DeserializationContext::create()->setGroups($groups)->setVersion($this->versioning->getCurrentVersion());
        $entity = $this->serializer->deserialize($json, $entityClass, 'json', $this->context);
        $errors = $this->validator->validate($entity);
        if($errors->count() > 0) {
            throw new ApiValidationException($errors);
        }

        return $entity;
    }

    private function updateProperties(object $baseEntity, object $updatedEntity, DeserializationContext $context) : void
    {
        $rflxClass = new \ReflectionClass($updatedEntity);
        $contextGroups = $context->getAttribute("groups");
        $contextVersion = $context->getAttribute("version");

        foreach($rflxClass->getProperties() as $prop)
        {
            // Strict Groups policy : Check for intersection between Context and Property Groups attributes
            $propGroups = $prop->getAttributes(Groups::class);
            if(empty($propGroups) || empty(array_intersect($propGroups[0]->getArguments()[0], $contextGroups))) {
                continue;
            }

            // If the property has a Since("version") attribute, only allow update if the current request version is at least equal
            $propSince = $prop->getAttributes(Since::class);
            if(!empty($propSince) && version_compare($contextVersion, $propSince[0]->getArguments()[0], "<")) {
                continue;
            }

            // Our checks have passed, call the setter of the property if it exists
            $setter = 'set'.ucwords($prop->getName());
            if(method_exists($baseEntity, $setter)) {
                $baseEntity->$setter($prop->getValue($updatedEntity));
            }
        }
    }
}
