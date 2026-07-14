<?php

namespace App\DataFixtures;

use App\Entity\CommissionWallet;
use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // 0. Create Product Categories
        $categoriesData = [
            'Poissons' => 'poissons',
            'Féculents' => 'feculents',
            'Huiles' => 'huiles',
            'Sucres' => 'sucres'
        ];

        $categories = [];
        foreach ($categoriesData as $name => $slug) {
            $category = new ProductCategory();
            $category->setName($name);
            $category->setSlug($slug);
            $manager->persist($category);
            $categories[$name] = $category;
        }

        // 1. Create Products
        $productsData = [
            [
                'name' => 'Poisson Salé',
                'description' => 'Poisson salé de première qualité séché naturellement.',
                'price' => '15000.00',
                'category' => 'Poissons',
                'imageUrl' => 'poisson_sale.jpg',
                'stock' => 100
            ],
            [
                'name' => 'Poisson fumé',
                'description' => 'Poisson fumé artisanalement au bois local.',
                'price' => '18000.00',
                'category' => 'Poissons',
                'imageUrl' => 'poisson_fume.jpg',
                'stock' => 80
            ],
            [
                'name' => 'Riz de Bumba',
                'description' => 'Riz local produit à Bumba, riche en nutriments.',
                'price' => '25000.00',
                'category' => 'Féculents',
                'imageUrl' => 'riz_bumba.jpg',
                'stock' => 200
            ],
            [
                'name' => 'Patates douces d\'Idiofa',
                'description' => 'Patates douces sucrées et savoureuses en provenance d\'Idiofa.',
                'price' => '12000.00',
                'category' => 'Féculents',
                'imageUrl' => 'patate_idiofa.jpg',
                'stock' => 150
            ],
            [
                'name' => 'Huile de palme',
                'description' => 'Huile de palme pure et naturelle pour toutes vos cuissons.',
                'price' => '9500.00',
                'category' => 'Huiles',
                'imageUrl' => 'huile_palme.jpg',
                'stock' => 120
            ],
            [
                'name' => 'Semoule de maïs',
                'description' => 'Semoule de maïs fine de qualité supérieure pour le fufu.',
                'price' => '14000.00',
                'category' => 'Féculents',
                'imageUrl' => 'semoule_mais.jpg',
                'stock' => 250
            ],
            [
                'name' => 'Miel naturel',
                'description' => 'Miel pur récolté dans les forêts équatoriales.',
                'price' => '20000.00',
                'category' => 'Sucres',
                'imageUrl' => 'miel_naturel.jpg',
                'stock' => 60
            ],
        ];

        foreach ($productsData as $data) {
            $product = new Product();
            $product->setName($data['name']);
            $product->setDescription($data['description']);
            $product->setPrice($data['price']);
            $product->setCategory($categories[$data['category']]);
            $product->setImageUrl($data['imageUrl']);
            $product->setStock($data['stock']);
            $product->setIsAvailable(true);

            $manager->persist($product);
        }

        // 2. Create Admin User
        $admin = new User();
        $admin->setEmail('admin@kitunga.com');
        $admin->setFullName('Admin Kitunga');
        $admin->setPhone('+243810000000');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setReferralCode('KNB-AD0001');
        $admin->setStatus('active');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'adminpassword'));

        $manager->persist($admin);

        // Wallet for Admin
        $adminWallet = new CommissionWallet();
        $adminWallet->setUser($admin);
        $adminWallet->setAvailableBalance('0.00');
        $adminWallet->setTotalGenerated('0.00');
        $adminWallet->setTotalPaid('0.00');

        $manager->persist($adminWallet);

        // 3. Create a Test Client User
        $client = new User();
        $client->setEmail('client@kitunga.com');
        $client->setFullName('Jean Paul');
        $client->setPhone('+243810000001');
        $client->setRoles(['ROLE_USER']);
        $client->setReferralCode('KNB-JP5678');
        $client->setStatus('active');
        $client->setPassword($this->passwordHasher->hashPassword($client, 'clientpassword'));

        $manager->persist($client);

        // Wallet for Client
        $clientWallet = new CommissionWallet();
        $clientWallet->setUser($client);
        $clientWallet->setAvailableBalance('0.00');
        $clientWallet->setTotalGenerated('0.00');
        $clientWallet->setTotalPaid('0.00');

        $manager->persist($clientWallet);

        $manager->flush();
    }
}
