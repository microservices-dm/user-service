<?php

namespace App\Service;

use App\Entity\User;
use App\Message\UserCreatedMessage;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Cache\CacheInterface;

class AuthService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly MessageBusInterface $messageBus,
        private readonly CacheInterface $jwtBlacklist
    ) {}

    public function register(string $email, string $password, ?string $name): User
    {
        if ($this->userRepository->findOneBy(['email' => $email])) {
            throw new \RuntimeException('User already exists');
        }

        $user = new User();
        $user->setEmail($email);
        $user->setName($name);
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $this->em->persist($user);
        $this->em->flush();

        // Отправляем событие в RabbitMQ
        $this->messageBus->dispatch(new UserCreatedMessage(
            $user->getId(),
            $user->getEmail()
        ));

        return $user;
    }

    public function login(string $email, string $password): array
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $password)) {
            throw new \RuntimeException('Invalid credentials');
        }

        $token = $this->jwtManager->create($user);
        $refreshToken = $this->generateRefreshToken($user);

        return [
            'token' => $token,
            'refresh_token' => $refreshToken,
            'expires_in' => 3600
        ];
    }

    public function logout(string $token): void
    {
        // Добавляем токен в blacklist
        $this->jwtBlacklist->get(
            'jwt_blacklist_' . md5($token),
            function () {
                return true;
            },
            3600 // TTL токена
        );
    }

    public function refreshToken(string $refreshToken): array
    {
        // Логика обновления токена
        // Валидация refresh token, создание нового access token

        throw new \RuntimeException('Not implemented');
    }

    private function generateRefreshToken(User $user): string
    {
        // Генерация refresh token
        return bin2hex(random_bytes(32));
    }
}
