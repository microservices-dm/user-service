<?php

namespace App\MessageHandler;

use App\Message\UserUpdatedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class UserUpdatedMessageHandler
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function __invoke(UserUpdatedMessage $message): void
    {
        // Логика обработки события создания пользователя
        // Например, отправка email, создание профиля в других сервисах и т.д.

        $this->logger->info('User updated event processed', [
            'user_id' => $message->getUserId(),
            'email' => $message->getEmail()
        ]);
    }
}
