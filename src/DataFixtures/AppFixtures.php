<?php

namespace App\DataFixtures;

use App\Entity\Brand;
use App\Entity\Customer;
use App\Entity\Smartphone;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $hasher;

    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Create a standard user (can view smartphones and view/add/delete their customers)
        $stdUser = new User();
        $stdUser->setEmail("democlient@demo.com");
        $stdUser->setRoles(["ROLE_USER"]);
        $stdUser->setPassword($this->hasher->hashPassword($stdUser, "Secret123"));
        $manager->persist($stdUser);

        // Create an admin (can also add/update/delete smartphones)
        $admin = new User();
        $admin->setEmail("admin@bilemo.com");
        $admin->setRoles(["ROLE_ADMIN"]);
        $admin->setPassword($this->hasher->hashPassword($admin, "Secret123"));
        $manager->persist($admin);

        // Create standard client's users
        for($i = 0; $i < 10; $i++){
            $customer = new Customer();
            $customer->setEmail("user".$i."@demo.com");
            $customer->setFirstName("John-".$i);
            $customer->setLastName("Doe-".$i);
            $customer->setCreationDate(new \DateTimeImmutable());
            $customer->setUser($stdUser);
            $manager->persist($customer);
        }

        $brandsList = ["Apple", "Huawei", "Samsung", "Sony", "Google", "Xiaomi"];
        for ($i = 0; $i < count($brandsList); $i++) {
            $brand = new Brand();
            $brand->setName($brandsList[$i]);
            $manager->persist($brand);

            // Create brand random phones
            for($j = 0; $j < rand(5,15); $j++) {
                $phone = new Smartphone();
                $phone->setName("DemoSmartphone : ".$j);
                $phone->setDescription("Demo description : ".$j);
                $phone->setScreenSize(rand(45,65) / 10);
                $phone->setPrice(rand(3500,8000) / 10);
                $phone->setBrand($brand);
                $manager->persist($phone);
            }
        }

        $manager->flush();
    }
}
