<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Repository\ProductRepository;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OrderController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private ProductRepository $productRepository;

    private const DELIVERY_FEE = '5000.00'; // Fixed delivery fee of 5000 FC

    public function __construct(
        EntityManagerInterface $entityManager,
        ProductRepository $productRepository
    ) {
        $this->entityManager = $entityManager;
        $this->productRepository = $productRepository;
    }

    #[Route('/api/orders', name: 'api_orders_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['status' => 'error', 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['deliveryAddress'])) {
            return new JsonResponse(['status' => 'error', 'errors' => ['deliveryAddress' => 'Delivery address is required']], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['paymentMethod'])) {
            return new JsonResponse(['status' => 'error', 'errors' => ['paymentMethod' => 'Payment method is required']], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['items']) || !is_array($data['items'])) {
            return new JsonResponse(['status' => 'error', 'errors' => ['items' => 'Items are required and must be an array']], Response::HTTP_BAD_REQUEST);
        }

        $order = new Order();
        $order->setCustomer($user);
        $order->setDeliveryAddress($data['deliveryAddress']);
        $order->setPaymentMethod($data['paymentMethod']);
        $order->setDeliveryFee(self::DELIVERY_FEE);
        $order->setStatus('received');

        $subtotal = 0.0;

        foreach ($data['items'] as $itemData) {
            if (empty($itemData['productId']) || empty($itemData['quantity']) || $itemData['quantity'] <= 0) {
                return new JsonResponse(['status' => 'error', 'message' => 'Invalid item details'], Response::HTTP_BAD_REQUEST);
            }

            $product = $this->productRepository->find($itemData['productId']);
            if (!$product) {
                return new JsonResponse(['status' => 'error', 'message' => 'Product not found: ' . $itemData['productId']], Response::HTTP_BAD_REQUEST);
            }

            if (!$product->isAvailable() || $product->getStock() < $itemData['quantity']) {
                return new JsonResponse([
                    'status' => 'error', 
                    'message' => sprintf('Insufficient stock for product "%s" (Available: %d)', $product->getName(), $product->getStock())
                ], Response::HTTP_BAD_REQUEST);
            }

            // Deduct stock
            $product->setStock($product->getStock() - $itemData['quantity']);
            if ($product->getStock() === 0) {
                $product->setIsAvailable(false);
            }

            $orderItem = new OrderItem();
            $orderItem->setProduct($product);
            $orderItem->setQuantity($itemData['quantity']);
            $orderItem->setUnitPrice($product->getPrice());

            $order->addOrderItem($orderItem);

            $itemCost = floatval($product->getPrice()) * $itemData['quantity'];
            $subtotal += $itemCost;
        }

        $order->setSubtotal(number_format($subtotal, 2, '.', ''));
        $totalAmount = $subtotal + floatval(self::DELIVERY_FEE);
        $order->setTotalAmount(number_format($totalAmount, 2, '.', ''));

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Order created successfully',
            'order' => [
                'id' => $order->getId(),
                'status' => $order->getStatus(),
                'subtotal' => $order->getSubtotal(),
                'deliveryFee' => $order->getDeliveryFee(),
                'totalAmount' => $order->getTotalAmount(),
                'deliveryAddress' => $order->getDeliveryAddress(),
                'paymentMethod' => $order->getPaymentMethod(),
                'createdAt' => $order->getCreatedAt()->format(\DateTimeInterface::ATOM)
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/orders', name: 'api_orders_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['status' => 'error', 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $orders = $this->entityManager->getRepository(Order::class)->findBy(['customer' => $user], ['createdAt' => 'DESC']);

        $data = [];
        foreach ($orders as $order) {
            $data[] = [
                'id' => $order->getId(),
                'status' => $order->getStatus(),
                'subtotal' => $order->getSubtotal(),
                'deliveryFee' => $order->getDeliveryFee(),
                'totalAmount' => $order->getTotalAmount(),
                'createdAt' => $order->getCreatedAt()->format(\DateTimeInterface::ATOM)
            ];
        }

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route('/api/orders/{id}', name: 'api_orders_detail', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['status' => 'error', 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $order = $this->entityManager->getRepository(Order::class)->find($id);

        if (!$order) {
            return new JsonResponse(['status' => 'error', 'message' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        if ($order->getCustomer()->getId() !== $user->getId() && !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['status' => 'error', 'message' => 'Access Denied'], Response::HTTP_FORBIDDEN);
        }

        $items = [];
        foreach ($order->getOrderItems() as $item) {
            $items[] = [
                'id' => $item->getId(),
                'productId' => $item->getProduct()->getId(),
                'productName' => $item->getProduct()->getName(),
                'quantity' => $item->getQuantity(),
                'unitPrice' => $item->getUnitPrice(),
            ];
        }

        return new JsonResponse([
            'id' => $order->getId(),
            'status' => $order->getStatus(),
            'subtotal' => $order->getSubtotal(),
            'deliveryFee' => $order->getDeliveryFee(),
            'totalAmount' => $order->getTotalAmount(),
            'deliveryAddress' => $order->getDeliveryAddress(),
            'paymentMethod' => $order->getPaymentMethod(),
            'createdAt' => $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'items' => $items,
        ], Response::HTTP_OK);
    }

    #[Route('/api/orders/{id}/confirm', name: 'api_orders_confirm', methods: ['POST'])]
    public function confirm(int $id, \App\Service\CommissionService $commissionService): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['status' => 'error', 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $order = $this->entityManager->getRepository(Order::class)->find($id);

        if (!$order) {
            return new JsonResponse(['status' => 'error', 'message' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        if ($order->getCustomer()->getId() !== $user->getId()) {
            return new JsonResponse(['status' => 'error', 'message' => 'Access Denied'], Response::HTTP_FORBIDDEN);
        }

        if ($order->getStatus() === 'confirmed') {
            return new JsonResponse(['status' => 'error', 'message' => 'Order is already confirmed'], Response::HTTP_BAD_REQUEST);
        }

        // We accept confirmation if the order is in shipping or delivered state
        if (!in_array($order->getStatus(), ['shipping', 'delivered'])) {
            return new JsonResponse([
                'status' => 'error', 
                'message' => 'Order must be shipping or delivered to be confirmed. Current status: ' . $order->getStatus()
            ], Response::HTTP_BAD_REQUEST);
        }

        $commissionService->confirmOrder($order);

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Order confirmed and commissions generated successfully',
            'order' => [
                'id' => $order->getId(),
                'status' => $order->getStatus()
            ]
        ], Response::HTTP_OK);
    }
}
