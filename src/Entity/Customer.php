<?php

namespace App\Entity;

use App\Repository\CustomerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Hateoas\Configuration\Annotation as Hateoas;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\Since;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @Hateoas\Relation("self",
 *      href = @Hateoas\Route("api_customer_get", parameters = { "id" = "expr(object.getId())" }),
 *      exclusion = @Hateoas\Exclusion(groups = { "getCustomers" }))
 * @Hateoas\Relation("delete",
 *      href = @Hateoas\Route("api_customer_delete", parameters = { "id" = "expr(object.getId())" }),
 *      exclusion = @Hateoas\Exclusion(groups = { "getCustomers" }))
 * @Hateoas\Relation("update",
 *      href = @Hateoas\Route("api_customer_update", parameters = { "id" = "expr(object.getId())" }),
 *      exclusion = @Hateoas\Exclusion(groups = { "getCustomers" }))
 */
#[ORM\Table(uniqueConstraints: [new ORM\UniqueConstraint(columns: ['userId', 'email'])])]
#[ORM\Entity(repositoryClass: CustomerRepository::class)]
class Customer
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    #[Groups(['getCustomers'])]
    private int $id;

    #[ORM\Column(type: Types::STRING, length: 125)]
    #[Groups(['getCustomers', 'modifyCustomers'])]
    #[Assert\NotBlank, Assert\Email, Assert\Length(max: 125)]
    private string $email;

    #[ORM\Column(type: Types::STRING, length: 80)]
    #[Groups(['getCustomers', 'modifyCustomers'])]
    #[Assert\NotBlank, Assert\Length(min: 2, max: 80)]
    private string $firstName;

    #[ORM\Column(type: Types::STRING, length: 80)]
    #[Groups(['getCustomers', 'modifyCustomers'])]
    #[Assert\NotBlank, Assert\Length(min: 2, max: 80)]
    private string $lastName;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Groups(['getCustomers', 'modifyCustomers'])]
    #[Assert\Length(min: 10, max: 20)]
    #[Since('2.0')]
    private string $phoneNumber;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Groups(['getCustomers'])]
    private \DateTimeInterface $creationDate;

    #[ORM\ManyToOne(inversedBy: 'customers'), ORM\JoinColumn(name: 'userId')]
    private User $user;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getCreationDate(): \DateTimeInterface
    {
        return $this->creationDate;
    }

    public function setCreationDate(\DateTimeInterface $creationDate): void
    {
        $this->creationDate = $creationDate;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): void
    {
        $this->phoneNumber = $phoneNumber;
    }
}
