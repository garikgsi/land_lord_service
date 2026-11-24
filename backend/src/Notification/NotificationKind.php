<?php

declare(strict_types=1);

namespace App\Notification;

enum NotificationKind: int
{
    case ContractExpiry = 1;
    case ContractIndexation = 2;
    case MeterReading = 3;
    case SupplierContractExpiry = 4;
    case UnsignedContract = 5;

    public function code(): string
    {
        return match ($this) {
            self::ContractExpiry => 'contract.expiry',
            self::ContractIndexation => 'contract.indexation',
            self::MeterReading => 'meter.reading',
            self::SupplierContractExpiry => 'supplier_contract.expiry',
            self::UnsignedContract => 'contract.unsigned',
        };
    }
}
