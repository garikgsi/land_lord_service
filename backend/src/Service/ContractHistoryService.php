<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\RegistryListQuery;
use App\Entity\Contract;
use App\Entity\LandLord;
use App\Entity\Renter;

final readonly class ContractHistoryService
{
    public function __construct(private EntityHistoryService $history)
    {
    }

    public function list(Contract $contract, RegistryListQuery $input): array
    {
        if (!$contract->isSigned()) {
            return ['items' => [], 'total' => 0, 'page' => $input->page, 'pageSize' => 20];
        }
        $versions = $this->history->versions($contract);
        $entries = [];
        $previous = [];
        foreach ($versions as $index => $row) {
            $moment = null === $row['valid_to'] ? new \DateTimeImmutable() : (new \DateTimeImmutable($row['valid_to'], new \DateTimeZone('UTC')))->modify('-1 microsecond');
            $renter = $this->history->stateAt(Renter::class, (int) $row['renter_id'], $moment);
            $landLord = $this->history->stateAt(LandLord::class, (int) $row['land_lord_id'], $moment);
            $data = [
                'contractNum' => $row['contract_num'], 'contractDate' => $row['contract_date'],
                'expiryDate' => $row['expiry_date'], 'monthlyPayment' => $row['monthly_payment'],
                'securityPayment' => $row['security_payment'], 'annulmentDate' => $row['annulment_date'],
                'increaseDate' => $row['increase_date'], 'increasePercent' => $row['increase_percent'],
                'increaseIntervalMonths' => $row['increase_interval_months'],
                'lastIndexationDate' => $row['last_indexation_date'],
                'renter' => ['id' => (int) $row['renter_id'], 'name' => $renter['name'] ?? '—'],
                'landLord' => ['id' => (int) $row['land_lord_id'], 'name' => $landLord['name'] ?? '—'],
                'contractTypeId' => (int) $row['contract_type_id'],
            ];
            $type = 0 === $index ? 'created' : (
                $data['annulmentDate'] !== $previous['annulmentDate'] ? 'termination' : (
                    $data['lastIndexationDate'] !== $previous['lastIndexationDate'] || $data['increaseDate'] !== $previous['increaseDate'] || $data['increasePercent'] !== $previous['increasePercent'] || $data['increaseIntervalMonths'] !== $previous['increaseIntervalMonths']
                        ? 'indexation' : 'amendment'
                )
            );
            $entries[] = [
                'id' => $index + 1, 'type' => $type,
                'validFrom' => null === $row['valid_from'] ? null : (new \DateTimeImmutable($row['valid_from'], new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z'),
                'validTo' => null === $row['valid_to'] ? null : (new \DateTimeImmutable($row['valid_to'], new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z'),
                'current' => null === $row['valid_to'],
                'previousData' => (object) $previous, 'nextData' => (object) $data,
            ];
            $previous = $data;
        }

        return ['items' => array_slice(array_reverse($entries), ($input->page - 1) * 20, 20), 'total' => count($entries), 'page' => $input->page, 'pageSize' => 20];
    }
}
