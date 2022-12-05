<?php

namespace App\Entity;

use App\Repository\SmartphoneRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Hateoas\Configuration\Annotation as Hateoas;
use JMS\Serializer\Annotation\Groups;

/**
 * @Hateoas\Relation("self",
 *      href = @Hateoas\Route("api_phone_get", parameters = { "id" = "expr(object.getId())" }),
 *      exclusion = @Hateoas\Exclusion(groups="getPhones"))
 * @Hateoas\Relation("delete",
 *      href = @Hateoas\Route("api_phone_delete", parameters = { "id" = "expr(object.getId())" }),
 *      exclusion = @Hateoas\Exclusion(groups="getPhones", excludeIf = "expr(not is_granted('ROLE_ADMIN'))"))
 * @Hateoas\Relation("update",
 *      href = @Hateoas\Route("api_phone_update", parameters = { "id" = "expr(object.getId())" }),
 *      exclusion = @Hateoas\Exclusion(groups="getPhones", excludeIf = "expr(not is_granted('ROLE_ADMIN'))"))
 */
#[ORM\Entity(repositoryClass: SmartphoneRepository::class)]
class Smartphone
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    #[Groups(['getPhones'])]
    private int $id;

    #[ORM\Column(length: 80)]
    #[Groups(['getPhones', 'modifyPhones'])]
    private string $model;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['getPhones', 'modifyPhones'])]
    private string $description;

    #[ORM\Column(type: Types::DECIMAL, scale: 1)]
    #[Groups(['getPhones', 'modifyPhones'])]
    private float $screenSize;

    #[ORM\Column(type: Types::DECIMAL, scale: 2)]
    #[Groups(['getPhones', 'modifyPhones'])]
    private float $price;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setModel(string $model): void
    {
        $this->model = $model;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getScreenSize(): string
    {
        return $this->screenSize;
    }

    public function setScreenSize(string $screenSize): void
    {
        $this->screenSize = $screenSize;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }
}
