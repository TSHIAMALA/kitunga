<?php

namespace App\Controller;

use App\Entity\CommissionWallet;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ReferralService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

class AuthController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;
    private ReferralService $referralService;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        ReferralService $referralService
    ) {
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
        $this->referralService = $referralService;
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the security firewall.');
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        // 1. Validation Constraints
        $constraints = new Assert\Collection([
            'fullName' => [new Assert\NotBlank(message: 'Full name is required'), new Assert\Length(max: 100)],
            'phone' => [new Assert\NotBlank(message: 'Phone number is required'), new Assert\Length(max: 20)],
            'email' => [new Assert\NotBlank(message: 'Email is required'), new Assert\Email(message: 'Invalid email address'), new Assert\Length(max: 180)],
            'password' => [new Assert\NotBlank(message: 'Password is required'), new Assert\Length(min: 6, minMessage: 'Password must be at least 6 characters long')],
            'address' => [new Assert\Optional(new Assert\Type('string'))],
            'referrerCode' => [new Assert\Optional(new Assert\Type('string'))],
        ]);

        $violations = $validator->validate($data, $constraints);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $property = str_replace(['[', ']'], '', $violation->getPropertyPath());
                $errors[$property] = $violation->getMessage();
            }
            return new JsonResponse(['status' => 'error', 'errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        // 2. Business Checks (uniqueness)
        if ($this->userRepository->findOneBy(['email' => $data['email']])) {
            return new JsonResponse(['status' => 'error', 'errors' => ['email' => 'This email is already registered']], Response::HTTP_BAD_REQUEST);
        }

        if ($this->userRepository->findOneBy(['phone' => $data['phone']])) {
            return new JsonResponse(['status' => 'error', 'errors' => ['phone' => 'This phone number is already registered']], Response::HTTP_BAD_REQUEST);
        }

        // 3. Create User
        $user = new User();
        $user->setFullName($data['fullName']);
        $user->setPhone($data['phone']);
        $user->setEmail($data['email']);
        $user->setAddress($data['address'] ?? null);
        $user->setRoles(['ROLE_USER']);
        $user->setStatus('active');

        // Hash Password
        $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));

        // Generate Referral Code
        $userCode = $this->referralService->generateReferralCode($data['fullName']);
        $user->setReferralCode($userCode);

        // Assign Referrer if provided
        if (!empty($data['referrerCode'])) {
            $referrer = $this->userRepository->findOneByReferralCode($data['referrerCode']);
            if ($referrer) {
                $user->setReferrer($referrer);
            } else {
                return new JsonResponse(['status' => 'error', 'errors' => ['referrerCode' => 'Invalid referral code']], Response::HTTP_BAD_REQUEST);
            }
        }

        $this->entityManager->persist($user);

        // 4. Create Wallet
        $wallet = new CommissionWallet();
        $wallet->setUser($user);
        $wallet->setAvailableBalance('0.00');
        $wallet->setTotalGenerated('0.00');
        $wallet->setTotalPaid('0.00');
        $this->entityManager->persist($wallet);

        $this->entityManager->flush();

        return new JsonResponse([
            'status' => 'success',
            'message' => 'User registered successfully',
            'user' => [
                'id' => $user->getId(),
                'fullName' => $user->getFullName(),
                'email' => $user->getEmail(),
                'phone' => $user->getPhone(),
                'referralCode' => $user->getReferralCode(),
                'referrerCode' => $user->getReferrer() ? $user->getReferrer()->getReferralCode() : null,
                'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM)
            ]
        ], Response::HTTP_CREATED);
    }
}
