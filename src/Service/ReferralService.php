<?php

namespace App\Service;

use App\Repository\UserRepository;

class ReferralService
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Generates a unique referral code based on user's name: KNB-XX1234
     */
    public function generateReferralCode(string $fullName): string
    {
        // 1. Clean up and extract initials
        $name = trim(preg_replace('/\s+/', ' ', $fullName));
        $parts = explode(' ', $name);
        
        $initials = '';
        if (count($parts) >= 2) {
            $initials .= mb_substr($parts[0], 0, 1);
            $initials .= mb_substr($parts[count($parts) - 1], 0, 1);
        } elseif (count($parts) === 1 && !empty($parts[0])) {
            $initials .= mb_substr($parts[0], 0, min(2, mb_strlen($parts[0])));
        } else {
            $initials = 'XX';
        }
        
        $initials = mb_strtoupper($initials);
        // Clean to keep only A-Z
        $initials = preg_replace('/[^A-Z]/', 'X', $initials);
        if (strlen($initials) < 2) {
            $initials = str_pad($initials, 2, 'X');
        }

        // 2. Loop to guarantee uniqueness
        do {
            $digits = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $code = 'KNB-' . $initials . $digits;
            $exists = $this->userRepository->findOneByReferralCode($code) !== null;
        } while ($exists);

        return $code;
    }
}
