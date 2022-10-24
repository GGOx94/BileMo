<?php

namespace App\DataFixtures;

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
        // Create our users (can fetch smartphones and view/add/delete their customers)
        $clients = [];
        for($i = 0; $i < rand(5, 10); $i++)
        {
            $clt = new User();
            $clt->setName("demoClient$i");
            $clt->setEmail("client$i@demo.com")->setPassword($this->hasher->hashPassword($clt, "Secret123"));
            $clients[] = $clt;
            $manager->persist($clt);
        }

        // Create an admin (can also add/update/delete smartphones)
        $admin = new User();
        $admin->setName("Administrator");
        $admin->setEmail("admin@bilemo.com");
        $admin->setRoles(["ROLE_ADMIN"]);
        $admin->setPassword($this->hasher->hashPassword($admin, "Secret123"));
        $manager->persist($admin);

        // Create clients customers
        foreach ($clients as $clt)
        {
            for ($i = 0; $i < rand(5,25); $i++)
            {
                $customer = new Customer();
                $customer->setEmail("user$i@demo.com");
                $customer->setFirstName("John-$i-".$clt->getName());
                $customer->setLastName("Doe-$i-".$clt->getName());
                $customer->setCreationDate(new \DateTimeImmutable());
                $customer->setUser($clt);
                $manager->persist($customer);
            }
        }

        // Create random phones
        for($j = 0; $j < rand(30,60); $j++)
        {
            $phone = new Smartphone();
            $phone->setModel("DemoModel : ".$j);
            $phone->setDescription("DemoDesc : ".$j);
            $phone->setScreenSize(rand(45,65) / 10);
            $phone->setPrice(rand(3500,8000) / 10);
            $manager->persist($phone);
        }

        $manager->flush();
    }
}
