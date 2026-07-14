<?php

namespace App\Service;

use App\Entity\Commission;
use App\Entity\CommissionWallet;
use App\Entity\Notification;
use App\Entity\Order;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class CommissionService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Confirms delivery of an order and calculates L1 and L2 commissions.
     */
    public function confirmOrder(Order $order): void
    {
        if ($order->getStatus() === 'confirmed') {
            return;
        }

        $order->setStatus('confirmed');
        $customer = $order->getCustomer();
        $subtotal = $order->getSubtotal();

        // 1. Level 1 Commission (5%)
        $referrerL1 = $customer->getReferrer();
        if ($referrerL1) {
            $amountL1 = bcmul($subtotal, '0.05', 2);
            if (bccomp($amountL1, '0.00', 2) > 0) {
                // Create Commission record
                $commissionL1 = new Commission();
                $commissionL1->setOrder($order);
                $commissionL1->setBeneficiary($referrerL1);
                $commissionL1->setBuyer($customer);
                $commissionL1->setLevel(1);
                $commissionL1->setPercentage('5.00');
                $commissionL1->setAmount($amountL1);
                $commissionL1->setStatus('validated');
                $this->entityManager->persist($commissionL1);

                // Update Wallet
                $walletL1 = $this->getOrCreateWallet($referrerL1);
                $walletL1->setAvailableBalance(bcadd($walletL1->getAvailableBalance(), $amountL1, 2));
                $walletL1->setTotalGenerated(bcadd($walletL1->getTotalGenerated(), $amountL1, 2));

                // Create Notification
                $notifL1 = new Notification();
                $notifL1->setUser($referrerL1);
                $notifL1->setTitle('Commission gagnée !');
                $notifL1->setMessage(sprintf('Vous avez gagné une commission de %s FC suite à l\'achat de %s.', number_format(floatval($amountL1), 0, ',', ' '), $customer->getFullName()));
                $notifL1->setType('commission');
                $this->entityManager->persist($notifL1);
            }

            // 2. Level 2 Commission (2%)
            $referrerL2 = $referrerL1->getReferrer();
            if ($referrerL2) {
                $amountL2 = bcmul($subtotal, '0.02', 2);
                if (bccomp($amountL2, '0.00', 2) > 0) {
                    // Create Commission record
                    $commissionL2 = new Commission();
                    $commissionL2->setOrder($order);
                    $commissionL2->setBeneficiary($referrerL2);
                    $commissionL2->setBuyer($customer);
                    $commissionL2->setLevel(2);
                    $commissionL2->setPercentage('2.00');
                    $commissionL2->setAmount($amountL2);
                    $commissionL2->setStatus('validated');
                    $this->entityManager->persist($commissionL2);

                    // Update Wallet
                    $walletL2 = $this->getOrCreateWallet($referrerL2);
                    $walletL2->setAvailableBalance(bcadd($walletL2->getAvailableBalance(), $amountL2, 2));
                    $walletL2->setTotalGenerated(bcadd($walletL2->getTotalGenerated(), $amountL2, 2));

                    // Create Notification
                    $notifL2 = new Notification();
                    $notifL2->setUser($referrerL2);
                    $notifL2->setTitle('Commission indirecte gagnée !');
                    $notifL2->setMessage(sprintf('Vous avez gagné une commission indirecte de %s FC suite à l\'achat de %s.', number_format(floatval($amountL2), 0, ',', ' '), $customer->getFullName()));
                    $notifL2->setType('commission');
                    $this->entityManager->persist($notifL2);
                }
            }
        }

        // 3. Customer Notification
        $notifCustomer = new Notification();
        $notifCustomer->setUser($customer);
        $notifCustomer->setTitle('Livraison confirmée');
        $notifCustomer->setMessage('Merci d\'avoir confirmé la réception de votre commande. Bon appétit !');
        $notifCustomer->setType('confirmation');
        $this->entityManager->persist($notifCustomer);

        $this->entityManager->flush();
    }

    private function getOrCreateWallet(User $user): CommissionWallet
    {
        $walletRepo = $this->entityManager->getRepository(CommissionWallet::class);
        $wallet = $walletRepo->findOneBy(['user' => $user]);

        if (!$wallet) {
            $wallet = new CommissionWallet();
            $wallet->setUser($user);
            $wallet->setAvailableBalance('0.00');
            $wallet->setTotalGenerated('0.00');
            $wallet->setTotalPaid('0.00');
            $this->entityManager->persist($wallet);
            $this->entityManager->flush();
        }

        return $wallet;
    }
}
