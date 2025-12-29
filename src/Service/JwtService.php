<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Message\UserCreatedMessage;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class JwtService
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {}

    public function createToken(User $user): string
    {
        // LexikJWTAuthenticationBundle автоматически добавит username и roles
        // Добавим свои кастомные поля
        return $this->jwtManager->createFromPayload($user, [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);
    }
}
