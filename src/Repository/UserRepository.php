<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 * @implements PasswordUpgraderInterface<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Сохранить пользователя
     */
    public function save(User $user, bool $flush = true): void
    {
        $this->getEntityManager()->persist($user);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Удалить пользователя
     */
    public function remove(User $user, bool $flush = true): void
    {
        $this->getEntityManager()->remove($user);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Обновить пароль пользователя (для Symfony Security)
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Найти пользователя по email (для авторизации)
     */
    public function findOneByEmail(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('LOWER(u.email) = LOWER(:email)')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Найти активных пользователей
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Найти пользователей по роли
     */
    public function findByRole(string $role): array
    {
        return $this->createQueryBuilder('u')
            ->where('JSON_CONTAINS(u.roles, :role) = 1')
            ->setParameter('role', json_encode($role))
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Поиск пользователей с пагинацией
     */
    public function findWithPagination(
        int $page = 1,
        int $limit = 20,
        ?string $search = null,
        ?string $role = null,
        ?bool $isActive = null
    ): array {
        $qb = $this->createQueryBuilder('u');

        // Поиск по email или имени
        if ($search) {
            $qb->andWhere('LOWER(u.email) LIKE LOWER(:search) OR LOWER(u.name) LIKE LOWER(:search)')
                ->setParameter('search', '%' . $search . '%');
        }

        // Фильтр по роли
        if ($role) {
            $qb->andWhere('JSON_CONTAINS(u.roles, :role) = 1')
                ->setParameter('role', json_encode($role));
        }

        // Фильтр по активности
        if ($isActive !== null) {
            $qb->andWhere('u.isActive = :active')
                ->setParameter('active', $isActive);
        }

        // Подсчёт общего количества
        $total = (clone $qb)
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Пагинация
        $offset = ($page - 1) * $limit;
        $users = $qb
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->orderBy('u.id', 'DESC')
            ->getQuery()
            ->getResult();

        return [
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }

    /**
     * Получить статистику пользователей
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('u');

        $total = $qb->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $active = (clone $qb)
            ->where('u.isActive = true')
            ->getQuery()
            ->getSingleScalarResult();

        $inactive = $total - $active;

        // Пользователи по ролям
        $roleStats = $this->createQueryBuilder('u')
            ->select('u.roles')
            ->getQuery()
            ->getResult();

        $rolesCount = [];
        foreach ($roleStats as $item) {
            foreach ($item['roles'] as $role) {
                $rolesCount[$role] = ($rolesCount[$role] ?? 0) + 1;
            }
        }

        // Регистрации за последние 30 дней
        $recentUsers = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt >= :date')
            ->setParameter('date', new \DateTimeImmutable('-30 days'))
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'roles' => $rolesCount,
            'recent_30_days' => $recentUsers
        ];
    }

    /**
     * Найти пользователей, зарегистрированных после определённой даты
     */
    public function findRegisteredAfter(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.createdAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Найти неактивных пользователей (не логинились долго)
     * Требует поле lastLoginAt в Entity
     */
    public function findInactiveUsers(int $days = 30): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.lastLoginAt < :date OR u.lastLoginAt IS NULL')
            ->andWhere('u.isActive = true')
            ->setParameter('date', new \DateTimeImmutable("-{$days} days"))
            ->orderBy('u.lastLoginAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Проверить существование email (для валидации)
     */
    public function emailExists(string $email, ?int $excludeUserId = null): bool
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('LOWER(u.email) = LOWER(:email)')
            ->setParameter('email', $email);

        if ($excludeUserId) {
            $qb->andWhere('u.id != :userId')
                ->setParameter('userId', $excludeUserId);
        }

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Массовое обновление (например, для миграций)
     */
    public function bulkUpdate(array $users): void
    {
        $em = $this->getEntityManager();

        foreach ($users as $user) {
            $em->persist($user);
        }

        $em->flush();
    }

    /**
     * Найти дубликаты email (для очистки данных)
     */
    public function findDuplicateEmails(): array
    {
        return $this->createQueryBuilder('u')
            ->select('u.email, COUNT(u.id) as cnt')
            ->groupBy('u.email')
            ->having('COUNT(u.id) > 1')
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить топ пользователей по активности
     * Требует связь с другими таблицами (например, orders)
     */
    public function getTopActiveUsers(int $limit = 10): array
    {
        // Пример, если есть связь с заказами
        return $this->createQueryBuilder('u')
            ->select('u, COUNT(o.id) as orderCount')
            ->leftJoin('u.orders', 'o')
            ->groupBy('u.id')
            ->orderBy('orderCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Soft delete - помечает пользователя как удалённого
     * Требует поле deletedAt в Entity
     */
    public function softDelete(User $user): void
    {
        $user->setDeletedAt(new \DateTimeImmutable());
        $user->setIsActive(false);
        $this->getEntityManager()->flush();
    }

    /**
     * Восстановить soft-deleted пользователя
     */
    public function restore(User $user): void
    {
        $user->setDeletedAt(null);
        $user->setIsActive(true);
        $this->getEntityManager()->flush();
    }

    /**
     * Найти пользователей для экспорта (с оптимизацией памяти)
     */
    public function findForExport(): \Generator
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.id', 'ASC');

        $query = $qb->getQuery();

        // Используем итератор для экономии памяти при больших выборках
        foreach ($query->toIterable() as $user) {
            yield $user;

            // Очищаем EntityManager для освобождения памяти
            $this->getEntityManager()->detach($user);
        }
    }

    /**
     * Пользовательский запрос с использованием DQL
     */
    public function findByCustomQuery(string $dql, array $parameters = []): array
    {
        $query = $this->getEntityManager()->createQuery($dql);

        foreach ($parameters as $key => $value) {
            $query->setParameter($key, $value);
        }

        return $query->getResult();
    }

    /**
     * Получить пользователей с их последними действиями
     * (Пример сложного запроса с подзапросом)
     */
    public function findUsersWithLastActivity(): array
    {
        return $this->createQueryBuilder('u')
            ->select('u, la')
            ->leftJoin('u.activities', 'la', 'WITH', 'la.id = (
                SELECT MAX(la2.id)
                FROM App\Entity\Activity la2
                WHERE la2.user = u
            )')
            ->getQuery()
            ->getResult();
    }
}
