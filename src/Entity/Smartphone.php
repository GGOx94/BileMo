<?php

namespace App\Entity;

use App\Repository\SmartphoneRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;

use Hateoas\Configuration\Annotation as Hateoas;

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
    #[Groups(["getPhones"])]
    private int $id;

    #[ORM\Column(length: 80)]
    #[Groups(["getPhones"])]
    private string $name;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(["getPhones"])]
    private string $description;

    #[ORM\ManyToOne(inversedBy: 'smartphones'), ORM\JoinColumn(name: 'brandId', onDelete:"CASCADE")]
    #[Groups(["getPhones"])]
    private Brand $brand;

    #[ORM\Column(type: Types::DECIMAL, scale: 1)]
    #[Groups(["getPhones"])]
    private float $screenSize;

    #[ORM\Column(type: Types::DECIMAL, scale: 2)]
    #[Groups(["getPhones"])]
    private float $price;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getBrand(): Brand
    {
        return $this->brand;
    }

    public function setBrand(Brand $brand): void
    {
        $this->brand = $brand;
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
