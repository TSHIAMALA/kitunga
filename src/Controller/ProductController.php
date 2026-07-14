<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProductController extends AbstractController
{
    private ProductRepository $productRepository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    #[Route('/api/products', name: 'api_products_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $category = $request->query->get('category');
        $search = $request->query->get('search');

        $qb = $this->productRepository->createQueryBuilder('p')
            ->where('p.isAvailable = :available')
            ->setParameter('available', true);

        if (!empty($category)) {
            $qb->join('p.category', 'c')
               ->andWhere('c.slug = :category OR c.name = :category')
               ->setParameter('category', $category);
        }

        if (!empty($search)) {
            $qb->andWhere('p.name LIKE :search OR p.description LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        $products = $qb->getQuery()->getResult();

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
                'categorySlug' => $product->getCategory() ? $product->getCategory()->getSlug() : null,
                'isAvailable' => $product->isAvailable(),
                'stock' => $product->getStock(),
            ];
        }

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route('/api/products/{id}', name: 'api_products_detail', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            return new JsonResponse(['status' => 'error', 'message' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'imageUrl' => $product->getImageUrl(),
            'category' => $product->getCategory() ? $product->getCategory()->getName() : null,
            'categoryId' => $product->getCategory() ? $product->getCategory()->getId() : null,
            'categorySlug' => $product->getCategory() ? $product->getCategory()->getSlug() : null,
            'isAvailable' => $product->isAvailable(),
            'stock' => $product->getStock(),
        ], Response::HTTP_OK);
    }
}
