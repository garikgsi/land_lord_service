<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\UnityProviderContract;
use App\Notification\NotificationChannel;
use App\Notification\NotificationKind;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class UnityProviderContractNotificationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotificationTypeService $types,
        private NotificationService $notifications,
        #[Autowire('%env(csv:NOTIFICATION_CHANNELS)%')]
        private array $channels,
    ) {
    }

    public function generate(?\DateTimeImmutable $today = null): int
    {
        $today = ($today ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        $type = $this->types->configuration(NotificationKind::SupplierContractExpiry);
        if (null === $type || !$type->isActive()) {
            return 0;
        }
        $recipients = $this->types->recipients($type);
        if ([] === $recipients) {
            return 0;
        }
        $records = $this->em->getRepository(UnityProviderContract::class)->createQueryBuilder('c')
            ->andWhere('c.expiryDate >= :today AND c.expiryDate <= :end')
            ->setParameter('today', $today)->setParameter('end', $today->modify('+'.$type->getDays().' days'))->getQuery()->getResult();
        $channels = array_map(static fn (string $channel): NotificationChannel => NotificationChannel::from(trim($channel)), $this->channels);
        $created = 0;
        foreach ($records as $record) {
            $key = NotificationKind::SupplierContractExpiry->code().':'.$record->getId().':'.$record->getExpiryDate()->format('Y-m-d');
            foreach ($recipients as $recipient) {
                if (false !== $this->em->getConnection()->fetchOne('SELECT id FROM notifications WHERE user_id = ? AND deduplication_key = ?', [$recipient->getId(), $key])) {
                    continue;
                }
                $title = 'Продление договора: '.$record->getUnityProvider()->getName();
                $this->notifications->notify(
                    $recipient,
                    NotificationKind::SupplierContractExpiry->code(),
                    $title,
                    $title.'. Объект: '.$record->getObject()->getName().'. Срок: '.$record->getExpiryDate()->format('d.m.Y').'.',
                    $record->getExpiryDate(),
                    '/unity-provider-contracts/'.$record->getId(),
                    $channels,
                    $key
                );
                ++$created;
            }
        }

        return $created;
    }
}
