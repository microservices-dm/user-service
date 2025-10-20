<?php
// services/user-service/src/Controller/AuthController.php

namespace App\Controller;

use App\Service\AuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    #[Route('/register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        //dd(111);
        $data = json_decode($request->getContent(), true);
        //dd($data);
        try {
            $user = $this->authService->register(
                $data['email'],
                $data['password'],
                $data['name'] ?? null
            );

            return $this->json([
                'message' => 'User registered successfully',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail()
                ]
            ], 201);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $result = $this->authService->login(
                $data['email'],
                $data['password']
            );

            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 401);
        }
    }

    #[Route('/refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $result = $this->authService->refreshToken($data['refresh_token']);

            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 401);
        }
    }

    #[Route('/logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $token = $request->headers->get('Authorization');
        $token = str_replace('Bearer ', '', $token);

        $this->authService->logout($token);

        return $this->json(['message' => 'Logged out successfully']);
    }
}
