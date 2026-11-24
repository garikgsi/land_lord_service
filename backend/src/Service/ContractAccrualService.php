<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contract;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final readonly class ContractAccrualService
{
    private const ACCOUNTING_START = '2026-12-01';

    public function __construct(private EntityManagerInterface $em, private ClockInterface $clock)
    {
    }

    public function today(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Moscow'))->setTime(0, 0);
    }

    /** Initial signing only: advance periods through the current month at the draft rate. */
    public function plan(\DateTimeImmutable $start, \DateTimeImmutable $end, string $monthlyPayment): array
    {
        $today = $this->today();
        $start = new \DateTimeImmutable($start->format('Y-m-d'), $today->getTimezone());
        $end = new \DateTimeImmutable($end->format('Y-m-d'), $today->getTimezone());
        $start = max($start, new \DateTimeImmutable(self::ACCOUNTING_START, $start->getTimezone()));
        $lastMonth = $today->modify('first day of this month');
        $periods = [];
        $total = '0.00';

        for ($month = $start->modify('first day of this month'); $month <= $lastMonth && $month <= $end; $month = $month->modify('+1 month')) {
            $periodStart = max($start, $month);
            $periodEnd = min($end, $month->modify('last day of this month'));
            if ($periodEnd < $periodStart) {
                continue;
            }
            $days = (int) $periodStart->diff($periodEnd)->days + 1;
            $fraction = str_contains($monthlyPayment, '.') ? strlen(explode('.', $monthlyPayment, 2)[1]) : 0;
            $numerator = bcmul(str_replace('.', '', $monthlyPayment), (string) ($days * 100), 0);
            $denominator = bcmul('1'.str_repeat('0', $fraction), $month->format('t'), 0);
            $cents = bcdiv(bcadd(bcmul($numerator, '2', 0), $denominator, 0), bcmul($denominator, '2', 0), 0);
            $amount = bcdiv($cents, '100', 2);
            $periods[] = [
                'periodMonth' => $month->format('Y-m-d'),
                'periodStart' => $periodStart->format('Y-m-d'),
                'periodEnd' => $periodEnd->format('Y-m-d'),
                'monthlyPayment' => $monthlyPayment,
                'amount' => $amount,
            ];
            $total = bcadd($total, $amount, 2);
        }

        return ['asOf' => $today->format('Y-m-d'), 'monthCount' => count($periods), 'totalAmount' => $total, 'periods' => $periods];
    }

    /** Called inside the contract signing transaction, after the signed record is flushed. */
    public function postSigning(Contract $contract, array $periods): void
    {
        if (!$contract->isSigned()) {
            throw new \LogicException('An unsigned contract cannot receive accruals.');
        }
        foreach ($periods as $period) {
            $this->em->getConnection()->executeStatement(
                'INSERT INTO contract_accruals (contract_id, period_month, period_start, period_end, monthly_payment, amount) VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT (contract_id, period_month) DO NOTHING',
                [$contract->getId(), $period['periodMonth'], $period['periodStart'], $period['periodEnd'], $period['monthlyPayment'], $period['amount']],
            );
        }
    }
}
