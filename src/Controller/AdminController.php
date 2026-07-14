<?php

namespace App\Controller;

use App\Entity\Commission;
use App\Entity\CommissionPayment;
use App\Entity\CommissionWallet;
use App\Entity\Notification;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/admin')]
class AdminController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/payments', name: 'api_admin_payments_create', methods: ['POST'])]
    public function recordPayment(Request $request): JsonResponse
    {
        $admin = $this->getUser();
        if (!$admin instanceof User) {
            return new JsonResponse(['status' => 'error', 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['beneficiaryId']) || empty($data['amount']) || empty($data['paymentMethod'])) {
            return new JsonResponse(['status' => 'error', 'message' => 'beneficiaryId, amount and paymentMethod are required'], Response::HTTP_BAD_REQUEST);
        }

        $beneficiary = $this->entityManager->getRepository(User::class)->find($data['beneficiaryId']);
        if (!$beneficiary) {
            return new JsonResponse(['status' => 'error', 'message' => 'Beneficiary user not found'], Response::HTTP_NOT_FOUND);
        }

        $wallet = $this->entityManager->getRepository(CommissionWallet::class)->findOneBy(['user' => $beneficiary]);
        if (!$wallet) {
            return new JsonResponse(['status' => 'error', 'message' => 'Wallet not found for this beneficiary'], Response::HTTP_NOT_FOUND);
        }

        $amountToPay = number_format(floatval($data['amount']), 2, '.', '');

        // Check balance
        if (bccomp($wallet->getAvailableBalance(), $amountToPay, 2) < 0) {
            return new JsonResponse([
                'status' => 'error', 
                'message' => sprintf('Insufficient balance. Available: %s FC, Requested: %s FC', $wallet->getAvailableBalance(), $amountToPay)
            ], Response::HTTP_BAD_REQUEST);
        }

        // Deduct balance and update paid amount
        $wallet->setAvailableBalance(bcsub($wallet->getAvailableBalance(), $amountToPay, 2));
        $wallet->setTotalPaid(bcadd($wallet->getTotalPaid(), $amountToPay, 2));

        // Create Payment log
        $payment = new CommissionPayment();
        $payment->setBeneficiary($beneficiary);
        $payment->setAmount($amountToPay);
        $payment->setPaymentMethod($data['paymentMethod']);
        $payment->setTransactionReference($data['transactionReference'] ?? null);
        $payment->setRecordedBy($admin);

        $this->entityManager->persist($payment);

        // Notify client
        $notification = new Notification();
        $notification->setUser($beneficiary);
        $notification->setTitle('Paiement de commission effectué');
        $notification->setMessage(sprintf('Votre commission de %s FC a été payée via %s.', number_format(floatval($amountToPay), 0, ',', ' '), $data['paymentMethod']));
        $notification->setType('payment');
        $this->entityManager->persist($notification);

        $this->entityManager->flush();

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Payment recorded successfully',
            'payment' => [
                'id' => $payment->getId(),
                'beneficiary' => $beneficiary->getFullName(),
                'amount' => $payment->getAmount(),
                'paymentMethod' => $payment->getPaymentMethod(),
                'paidAt' => $payment->getPaidAt()->format(\DateTimeInterface::ATOM)
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/dashboard', name: 'api_admin_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $userRepo = $this->entityManager->getRepository(User::class);
        $orderRepo = $this->entityManager->getRepository(Order::class);
        $productRepo = $this->entityManager->getRepository(Product::class);
        $commissionRepo = $this->entityManager->getRepository(Commission::class);
        $walletRepo = $this->entityManager->getRepository(CommissionWallet::class);

        $totalClients = $userRepo->count([]);
        $totalOrders = $orderRepo->count([]);
        $totalProducts = $productRepo->count([]);

        $salesResult = $orderRepo->createQueryBuilder('o')
            ->select('SUM(o.totalAmount) as total')
            ->getQuery()->getSingleScalarResult();
        $totalSales = $salesResult ?? '0.00';

        $commResult = $commissionRepo->createQueryBuilder('c')
            ->select('SUM(c.amount) as total')
            ->where('c.status = :status')
            ->setParameter('status', 'validated')
            ->getQuery()->getSingleScalarResult();
        $totalCommissionsGenerated = $commResult ?? '0.00';

        $paidResult = $walletRepo->createQueryBuilder('w')
            ->select('SUM(w.totalPaid) as total')
            ->getQuery()->getSingleScalarResult();
        $totalCommissionsPaid = $paidResult ?? '0.00';

        return new JsonResponse([
            'totalClients' => $totalClients,
            'totalOrders' => $totalOrders,
            'totalProducts' => $totalProducts,
            'totalSales' => $totalSales,
            'totalCommissionsGenerated' => $totalCommissionsGenerated,
            'totalCommissionsPaid' => $totalCommissionsPaid,
        ], Response::HTTP_OK);
    }

    #[Route('/orders', name: 'api_admin_orders_list', methods: ['GET'])]
    public function listOrders(): JsonResponse
    {
        $orders = $this->entityManager->getRepository(Order::class)->findBy([], ['createdAt' => 'DESC']);
        $data = [];
        foreach ($orders as $order) {
            $data[] = [
                'id' => $order->getId(),
                'customerName' => $order->getCustomer()->getFullName(),
                'customerId' => $order->getCustomer()->getId(),
                'subtotal' => $order->getSubtotal(),
                'deliveryFee' => $order->getDeliveryFee(),
                'totalAmount' => $order->getTotalAmount(),
                'deliveryAddress' => $order->getDeliveryAddress(),
                'paymentMethod' => $order->getPaymentMethod(),
                'status' => $order->getStatus(),
                'createdAt' => $order->getCreatedAt()->format(\DateTimeInterface::ATOM)
            ];
        }
        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route('/orders/{id}/status', name: 'api_admin_order_status_update', methods: ['PUT'])]
    public function updateOrderStatus(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $newStatus = $data['status'] ?? '';

        $validStatuses = ['received', 'preparing', 'shipping', 'delivered', 'confirmed'];
        if (!in_array($newStatus, $validStatuses)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid status'], Response::HTTP_BAD_REQUEST);
        }

        $order = $this->entityManager->getRepository(Order::class)->find($id);
        if (!$order) {
            return new JsonResponse(['status' => 'error', 'message' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        $order->setStatus($newStatus);

        if (!empty($data['delivererId'])) {
            $deliverer = $this->entityManager->getRepository(User::class)->find($data['delivererId']);
            if ($deliverer) {
                $order->setDeliverer($deliverer);
            }
        }

        $notification = new Notification();
        $notification->setUser($order->getCustomer());
        $notification->setType('order');

        switch ($newStatus) {
            case 'preparing':
                $notification->setTitle('Commande en préparation');
                $notification->setMessage('Votre commande est en cours de préparation par KITUNGA NA BISO.');
                break;
            case 'shipping':
                $notification->setTitle('Commande en cours de livraison');
                $notification->setMessage('Votre commande est en route ! Un livreur arrive.');
                break;
            case 'delivered':
                $notification->setTitle('Commande livrée ?');
                $notification->setMessage('Votre commande a été marquée comme livrée. Confirmez-vous la réception ?');
                $notification->setType('delivery');
                break;
            case 'confirmed':
                $notification->setTitle('Commande confirmée');
                $notification->setMessage('Merci d\'avoir confirmé la réception de votre commande.');
                break;
        }

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Order status updated to ' . $newStatus,
            'order' => [
                'id' => $order->getId(),
                'status' => $order->getStatus()
            ]
        ], Response::HTTP_OK);
    }

    #[Route('/clients', name: 'api_admin_clients_list', methods: ['GET'])]
    public function listClients(): JsonResponse
    {
        $clients = $this->entityManager->getRepository(User::class)->findBy([], ['createdAt' => 'DESC']);
        $data = [];
        foreach ($clients as $client) {
            $data[] = [
                'id' => $client->getId(),
                'fullName' => $client->getFullName(),
                'email' => $client->getEmail(),
                'phone' => $client->getPhone(),
                'referralCode' => $client->getReferralCode(),
                'referrerCode' => $client->getReferrer() ? $client->getReferrer()->getReferralCode() : null,
                'status' => $client->getStatus(),
                'createdAt' => $client->getCreatedAt()->format(\DateTimeInterface::ATOM)
            ];
        }
        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route('/clients/{id}/network', name: 'api_admin_client_network', methods: ['GET'])]
    public function clientNetwork(int $id): JsonResponse
    {
        $client = $this->entityManager->getRepository(User::class)->find($id);
        if (!$client) {
            return new JsonResponse(['status' => 'error', 'message' => 'Client not found'], Response::HTTP_NOT_FOUND);
        }

        $directs = $this->entityManager->getRepository(User::class)->findBy(['referrer' => $client]);
        $directData = [];
        
        foreach ($directs as $direct) {
            $indirects = $this->entityManager->getRepository(User::class)->findBy(['referrer' => $direct]);
            $indirectData = [];
            foreach ($indirects as $indirect) {
                $indirectData[] = [
                    'id' => $indirect->getId(),
                    'fullName' => $indirect->getFullName(),
                    'email' => $indirect->getEmail(),
                    'phone' => $indirect->getPhone(),
                    'createdAt' => $indirect->getCreatedAt()->format(\DateTimeInterface::ATOM)
                ];
            }

            $directData[] = [
                'id' => $direct->getId(),
                'fullName' => $direct->getFullName(),
                'email' => $direct->getEmail(),
                'phone' => $direct->getPhone(),
                'createdAt' => $direct->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'subNetwork' => $indirectData
            ];
        }

        return new JsonResponse([
            'client' => [
                'id' => $client->getId(),
                'fullName' => $client->getFullName(),
                'referralCode' => $client->getReferralCode()
            ],
            'level1Referrals' => $directData
        ], Response::HTTP_OK);
    }

    #[Route('/clients/{id}/status', name: 'api_admin_client_status', methods: ['PUT'])]
    public function updateClientStatus(int $id, Request $request): JsonResponse
    {
        $client = $this->entityManager->getRepository(User::class)->find($id);
        if (!$client) {
            return new JsonResponse(['status' => 'error', 'message' => 'Client not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $status = $data['status'] ?? '';

        if (!in_array($status, ['active', 'blocked'])) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid status'], Response::HTTP_BAD_REQUEST);
        }

        $client->setStatus($status);
        $this->entityManager->flush();

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Client status updated to ' . $status
        ], Response::HTTP_OK);
    }

    #[Route('/clients', name: 'api_admin_clients_create', methods: ['POST'])]
    public function createClient(Request $request, \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $passwordHasher, \App\Service\ReferralService $referralService): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        
        if (empty($data['fullName']) || empty($data['phone']) || empty($data['email']) || empty($data['password'])) {
            return new JsonResponse(['status' => 'error', 'message' => 'Required fields are missing'], Response::HTTP_BAD_REQUEST);
        }

        $userRepo = $this->entityManager->getRepository(User::class);
        if ($userRepo->findOneBy(['email' => $data['email']]) || $userRepo->findOneBy(['phone' => $data['phone']])) {
            return new JsonResponse(['status' => 'error', 'message' => 'Email or phone already exists'], Response::HTTP_BAD_REQUEST);
        }

        $user = new User();
        $user->setFullName($data['fullName']);
        $user->setPhone($data['phone']);
        $user->setEmail($data['email']);
        $user->setAddress($data['address'] ?? null);
        $user->setRoles($data['roles'] ?? ['ROLE_USER']);
        $user->setStatus($data['status'] ?? 'active');
        $user->setPassword($passwordHasher->hashPassword($user, $data['password']));
        $user->setReferralCode($referralService->generateReferralCode($data['fullName']));

        $this->entityManager->persist($user);
        
        $wallet = new \App\Entity\CommissionWallet();
        $wallet->setUser($user);
        $wallet->setAvailableBalance('0.00');
        $wallet->setTotalGenerated('0.00');
        $wallet->setTotalPaid('0.00');
        $this->entityManager->persist($wallet);

        $this->entityManager->flush();

        return new JsonResponse(['status' => 'success', 'message' => 'User created successfully', 'id' => $user->getId()], Response::HTTP_CREATED);
    }

    #[Route('/clients/{id}', name: 'api_admin_clients_update', methods: ['PUT'])]
    public function updateClient(int $id, Request $request, \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $user = $this->entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            return new JsonResponse(['status' => 'error', 'message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['fullName'])) $user->setFullName($data['fullName']);
        if (isset($data['phone'])) $user->setPhone($data['phone']);
        if (isset($data['email'])) $user->setEmail($data['email']);
        if (isset($data['address'])) $user->setAddress($data['address']);
        if (isset($data['roles'])) $user->setRoles($data['roles']);
        if (isset($data['status'])) $user->setStatus($data['status']);
        
        if (!empty($data['password'])) {
            $user->setPassword($passwordHasher->hashPassword($user, $data['password']));
        }

        $this->entityManager->flush();

        return new JsonResponse(['status' => 'success', 'message' => 'User updated successfully'], Response::HTTP_OK);
    }

    #[Route('/clients/{id}', name: 'api_admin_clients_delete', methods: ['DELETE'])]
    public function deleteClient(int $id): JsonResponse
    {
        $user = $this->entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            return new JsonResponse(['status' => 'error', 'message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $wallet = $this->entityManager->getRepository(\App\Entity\CommissionWallet::class)->findOneBy(['user' => $user]);
        if ($wallet) {
            $this->entityManager->remove($wallet);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return new JsonResponse(['status' => 'success', 'message' => 'User deleted successfully'], Response::HTTP_OK);
    }

    #[Route('/products', name: 'api_admin_products_list', methods: ['GET'])]
    public function listProducts(): JsonResponse
    {
        $products = $this->entityManager->getRepository(Product::class)->findAll();
        $data = [];
        foreach ($products as $product) {
            $data[] = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'description' => $product->getDescription(),
                'price' => $product->getPrice(),
                'imageUrl' => $product->getImageUrl(),
                'category' => $product->getCategory() ? $product->getCategory()->getName() : null,
                'categoryId' => $product->getCategory() ? $product->getCategory()->getId() : null,
                'isAvailable' => $product->isAvailable(),
                'stock' => $product->getStock(),
            ];
        }
        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route('/products', name: 'api_admin_products_create', methods: ['POST'])]
    public function createProduct(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        
        if (empty($data['name']) || empty($data['price']) || empty($data['category'])) {
            return new JsonResponse(['status' => 'error', 'message' => 'Name, price, and category are required'], Response::HTTP_BAD_REQUEST);
        }

        $categoryInput = $data['category'];
        $category = null;
        if (is_numeric($categoryInput)) {
            $category = $this->entityManager->getRepository(\App\Entity\ProductCategory::class)->find($categoryInput);
        } else {
            $category = $this->entityManager->getRepository(\App\Entity\ProductCategory::class)->findOneBy(['slug' => $categoryInput])
                     ?? $this->entityManager->getRepository(\App\Entity\ProductCategory::class)->findOneBy(['name' => $categoryInput]);
        }

        if (!$category) {
            return new JsonResponse(['status' => 'error', 'message' => 'Category not found'], Response::HTTP_BAD_REQUEST);
        }

        $product = new Product();
        $product->setName($data['name']);
        $product->setDescription($data['description'] ?? null);
        $product->setPrice(number_format(floatval($data['price']), 2, '.', ''));
        $product->setCategory($category);
        $product->setImageUrl($data['imageUrl'] ?? null);
        $product->setStock($data['stock'] ?? 0);
        $product->setIsAvailable($data['isAvailable'] ?? true);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Product created successfully',
            'product' => [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'price' => $product->getPrice()
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/products/{id}', name: 'api_admin_products_update', methods: ['PUT'])]
    public function updateProduct(int $id, Request $request): JsonResponse
    {
        $product = $this->entityManager->getRepository(Product::class)->find($id);
        if (!$product) {
            return new JsonResponse(['status' => 'error', 'message' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['name'])) $product->setName($data['name']);
        if (isset($data['description'])) $product->setDescription($data['description']);
        if (isset($data['price'])) $product->setPrice(number_format(floatval($data['price']), 2, '.', ''));
        if (isset($data['category'])) {
            $categoryInput = $data['category'];
            $category = null;
            if (is_numeric($categoryInput)) {
                $category = $this->entityManager->getRepository(\App\Entity\ProductCategory::class)->find($categoryInput);
            } else {
                $category = $this->entityManager->getRepository(\App\Entity\ProductCategory::class)->findOneBy(['slug' => $categoryInput])
                         ?? $this->entityManager->getRepository(\App\Entity\ProductCategory::class)->findOneBy(['name' => $categoryInput]);
            }
            if (!$category) {
                return new JsonResponse(['status' => 'error', 'message' => 'Category not found'], Response::HTTP_BAD_REQUEST);
            }
            $product->setCategory($category);
        }
        if (isset($data['imageUrl'])) $product->setImageUrl($data['imageUrl']);
        if (isset($data['stock'])) $product->setStock($data['stock']);
        if (isset($data['isAvailable'])) $product->setIsAvailable($data['isAvailable']);

        $this->entityManager->flush();

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Product updated successfully'
        ], Response::HTTP_OK);
    }

    #[Route('/products/{id}', name: 'api_admin_products_delete', methods: ['DELETE'])]
    public function deleteProduct(int $id): JsonResponse
    {
        $product = $this->entityManager->getRepository(Product::class)->find($id);
        if (!$product) {
            return new JsonResponse(['status' => 'error', 'message' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($product);
        $this->entityManager->flush();

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Product deleted successfully'
        ], Response::HTTP_OK);
    }

    #[Route('/reports', name: 'api_admin_reports', methods: ['GET'])]
    public function getReports(): JsonResponse
    {
        $orderRepo = $this->entityManager->getRepository(Order::class);
        
        $dailySales = $orderRepo->createQueryBuilder('o')
            ->select('SUBSTRING(o.createdAt, 1, 10) as dateVal, SUM(o.totalAmount) as total, COUNT(o.id) as count')
            ->groupBy('dateVal')
            ->orderBy('dateVal', 'DESC')
            ->setMaxResults(30)
            ->getQuery()->getResult();

        $commRepo = $this->entityManager->getRepository(Commission::class);
        $commSummary = $commRepo->createQueryBuilder('c')
            ->select('c.status, SUM(c.amount) as total, COUNT(c.id) as count')
            ->groupBy('c.status')
            ->getQuery()->getResult();

        $topSponsors = $this->entityManager->getRepository(User::class)->createQueryBuilder('u')
            ->select('u.id, u.fullName, u.referralCode, COUNT(f.id) as directCount')
            ->join(User::class, 'f', 'WITH', 'f.referrer = u')
            ->groupBy('u.id')
            ->orderBy('directCount', 'DESC')
            ->setMaxResults(10)
            ->getQuery()->getResult();

        return new JsonResponse([
            'sales' => $dailySales,
            'commissions' => $commSummary,
            'topSponsors' => $topSponsors
        ], Response::HTTP_OK);
    }
}
