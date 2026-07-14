<?php

namespace App\Controller;

use App\Entity\Commission;
use App\Entity\CommissionWallet;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WalletController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/api/wallet', name: 'api_wallet_show', methods: ['GET'])]
    public function show(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['status' => 'error', 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $wallet = $this->entityManager->getRepository(CommissionWallet::class)->findOneBy(['user' => $user]);

        if (!$wallet) {
            $wallet = new CommissionWallet();
            $wallet->setUser($user);
            $this->entityManager->persist($wallet);
            $this->entityManager->flush();
        }

        $directReferralsCount = $this->entityManager->getRepository(User::class)->count(['referrer' => $user]);
        
        $indirectReferralsCount = 0;
        $directReferrals = $this->entityManager->getRepository(User::class)->findBy(['referrer' => $user]);
        foreach ($directReferrals as $direct) {
            $indirectReferralsCount += $this->entityManager->getRepository(User::class)->count(['referrer' => $direct]);
        }

        return new JsonResponse([
            'availableBalance' => $wallet->getAvailableBalance(),
            'totalGenerated' => $wallet->getTotalGenerated(),
            'totalPaid' => $wallet->getTotalPaid(),
            'referrals' => [
                'directCount' => $directReferralsCount,
                'indirectCount' => $indirectReferralsCount,
                'code' => $user->getReferralCode()
            ]
        ], Response::HTTP_OK);
    }

    #[Route('/api/commissions', name: 'api_commissions_history', methods: ['GET'])]
    public function history(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['status' => 'error', 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $commissions = $this->entityManager->getRepository(Commission::class)->findBy(
            ['beneficiary' => $user],
            ['createdAt' => 'DESC']
        );

        $data = [];
        foreach ($commissions as $commission) {
            $data[] = [
                'id' => $commission->getId(),
                'date' => $commission->getCreatedAt()->format('d/m/Y'),
                'buyerName' => $commission->getBuyer()->getFullName(),
                'amount' => $commission->getAmount(),
                'level' => $commission->getLevel(),
                'percentage' => $commission->getPercentage(),
                'status' => $commission->getStatus(),
                'orderId' => $commission->getOrder()->getId(),
            ];
        }

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route('/api/notifications', name: 'api_notifications_list', methods: ['GET'])]
    public function listNotifications(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['status' => 'error', 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $notifications = $this->entityManager->getRepository(\App\Entity\Notification::class)->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        $data = [];
        foreach ($notifications as $n) {
            $data[] = [
                'id' => $n->getId(),
                'title' => $n->getTitle(),
                'message' => $n->getMessage(),
                'isRead' => $n->isRead(),
                'type' => $n->getType(),
                'createdAt' => $n->getCreatedAt()->format(\DateTimeInterface::ATOM)
            ];
        }

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route('/api/notifications/{id}/read', name: 'api_notifications_read', methods: ['PUT'])]
    public function readNotification(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['status' => 'error', 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $n = $this->entityManager->getRepository(\App\Entity\Notification::class)->find($id);
        if (!$n || $n->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['status' => 'error', 'message' => 'Notification not found'], Response::HTTP_NOT_FOUND);
        }

        $n->setIsRead(true);
        $this->entityManager->flush();

        return new JsonResponse(['status' => 'success', 'message' => 'Notification marked as read'], Response::HTTP_OK);
    }
}
