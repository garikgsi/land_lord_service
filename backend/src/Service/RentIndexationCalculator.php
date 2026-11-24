<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\InputException;

final readonly class RentIndexationCalculator
{
    public function amount(string $current, string $percent): string
    {
        if (bccomp($percent, '-100', $this->scale($percent)) < 0) {
            throw new InputException('increasePercent', 'Процент не может уменьшать платёж ниже нуля.');
        }
        $scale = $this->scale($current) + $this->scale($percent) + 2;
        $amount = bcdiv(bcmul($current, bcadd('100', $percent, $scale), $scale), '100', $scale);

        return bcround($amount, 2);
    }

    public function percent(string $current, string $amount): string
    {
        if (0 === bccomp($current, '0', $this->scale($current))) {
            if (0 === bccomp($amount, '0', $this->scale($amount))) {
                return '0';
            }
            throw new InputException('newMonthlyPayment', 'При текущем платеже 0 процент не определяется. Сначала измените платёж действием «Изменение».');
        }
        $scale = max($this->scale($current), $this->scale($amount));
        $percent = bcround(bcdiv(bcmul(bcsub($amount, $current, $scale), '100', $scale), $current, 8), 6);

        return rtrim(rtrim($percent, '0'), '.');
    }

    public function same(string $left, string $right): bool
    {
        return 0 === bccomp($left, $right, max($this->scale($left), $this->scale($right)));
    }

    private function scale(string $value): int
    {
        $dot = strpos($value, '.');

        return false === $dot ? 0 : strlen($value) - $dot - 1;
    }
}
