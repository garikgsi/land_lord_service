<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\NotificationTypeInput;
use App\Entity\NotificationType;
use App\Entity\User;
use App\Exception\InputException;
use App\Notification\NotificationKind;
use Doctrine\ORM\EntityManagerInterface;

final readonly class NotificationTypeService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /** Read directly to observe settings changed since the preceding generation. */
    public function configuration(NotificationKind $kind): ?NotificationType
    {
        $row = $this->em->getConnection()->fetchAssociative('SELECT * FROM notification_types WHERE id = ?', [$kind->value]);

        return false === $row ? null : new NotificationType((int) $row['id'], $row['name'], (int) $row['days'], json_decode($row['roles'], true, flags: JSON_THROW_ON_ERROR), (bool) $row['is_active']);
    }

    public function recipients(NotificationType $type): array
    {
        if (!$type->isActive() || [] === $type->getRoles()) {
            return [];
        }

        return array_values(array_filter(
            $this->em->getRepository(User::class)->findBy(['active' => true, 'blocked' => false]),
            static fn (User $user): bool => [] !== array_intersect($type->getRoles(), $user->getRoles()),
        ));
    }

    public function notificationDate(NotificationType $type, \DateTimeImmutable $due): \DateTimeImmutable
    {
        return $due->setTime(0, 0)->modify('-'.$type->getDays().' days');
    }

    public function present(NotificationType $type): array
    {
        return ['id' => $type->getId(), 'name' => $type->getName(), 'days' => $type->getDays(), 'roles' => $type->getRoles(), 'isActive' => $type->isActive(), 'timing' => NotificationKind::UnsignedContract->value === $type->getId() ? 'repeat' : 'lead'];
    }

    public function list(): array
    {
        $items = [];
        foreach (NotificationKind::cases() as $kind) {
            $type = $this->configuration($kind);
            if (null !== $type) {
                $items[] = $this->present($type);
            }
        }

        return ['items' => $items];
    }

    public function update(NotificationType $type, NotificationTypeInput $input): NotificationType
    {
        if (NotificationKind::UnsignedContract->value === $type->getId() && $input->days < 1) {
            throw new InputException('days', 'Интервал повторения должен быть не меньше одного дня.');
        }
        $type->configure($input->name, $input->days, $input->roles, $input->isActive);
        $this->em->flush();

        return $type;
    }
}
