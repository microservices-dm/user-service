<?php

namespace App\MessageHandler;

use App\Message\UserCreatedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class UserCreatedMessageHandler
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    public function __invoke(UserCreatedMessage $message): void
    {
        // Логика обработки события создания пользователя
        // Например, отправка email, создание профиля в других сервисах и т.д.

        $this->logger->info('User created event processed', [
            'user_id' => $message->getUserId(),
            'email' => $message->getEmail()
        ]);
    }
}
