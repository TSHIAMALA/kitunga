<?php

namespace App\Controller;

use App\Entity\ProductCategory;
use App\Repository\ProductCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class CategoryController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/api/categories', name: 'api_categories_list', methods: ['GET'])]
    public function list(ProductCategoryRepository $categoryRepository): JsonResponse
    {
        $categories = $categoryRepository->findAll();
        $data = [];
        foreach ($categories as $category) {
            $data[] = [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
            ];
        }
        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route('/api/admin/categories', name: 'api_admin_categories_create', methods: ['POST'])]
    public function create(Request $request, SluggerInterface $slugger, ProductCategoryRepository $categoryRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['name'])) {
            return new JsonResponse(['status' => 'error', 'message' => 'Name is required'], Response::HTTP_BAD_REQUEST);
        }

        $name = $data['name'];
        $slug = $data['slug'] ?? strtolower($slugger->slug($name));

        if ($categoryRepository->findOneBy(['name' => $name]) || $categoryRepository->findOneBy(['slug' => $slug])) {
            return new JsonResponse(['status' => 'error', 'message' => 'Category already exists'], Response::HTTP_BAD_REQUEST);
        }

        $category = new ProductCategory();
        $category->setName($name);
        $category->setSlug($slug);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Category created successfully',
            'category' => [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/admin/categories/{id}', name: 'api_admin_categories_update', methods: ['PUT'])]
    public function update(int $id, Request $request, SluggerInterface $slugger, ProductCategoryRepository $categoryRepository): JsonResponse
    {
        $category = $categoryRepository->find($id);
        if (!$category) {
            return new JsonResponse(['status' => 'error', 'message' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['name'])) {
            $name = $data['name'];
            $slug = $data['slug'] ?? strtolower($slugger->slug($name));

            $existingCategoryByName = $categoryRepository->findOneBy(['name' => $name]);
            if ($existingCategoryByName && $existingCategoryByName->getId() !== $id) {
                return new JsonResponse(['status' => 'error', 'message' => 'Category name already exists'], Response::HTTP_BAD_REQUEST);
            }

            $existingCategoryBySlug = $categoryRepository->findOneBy(['slug' => $slug]);
            if ($existingCategoryBySlug && $existingCategoryBySlug->getId() !== $id) {
                return new JsonResponse(['status' => 'error', 'message' => 'Category slug already exists'], Response::HTTP_BAD_REQUEST);
            }

            $category->setName($name);
            $category->setSlug($slug);
            $this->entityManager->flush();
        }

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Category updated successfully',
            'category' => [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
            ]
        ], Response::HTTP_OK);
    }

    #[Route('/api/admin/categories/{id}', name: 'api_admin_categories_delete', methods: ['DELETE'])]
    public function delete(int $id, ProductCategoryRepository $categoryRepository): JsonResponse
    {
        $category = $categoryRepository->find($id);
        if (!$category) {
            return new JsonResponse(['status' => 'error', 'message' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Category deleted successfully'
        ], Response::HTTP_OK);
    }
}
