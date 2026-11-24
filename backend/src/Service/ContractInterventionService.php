<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ContractInterventionListQuery;
use App\Notification\NotificationKind;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ContractInterventionService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function list(ContractInterventionListQuery $input, ?\DateTimeImmutable $today = null): array
    {
        $today ??= new \DateTimeImmutable('today');
        $source = $this->source().<<<'SQL'
            , intervention_records AS (
                SELECT 'rental' AS contract_type, CASE WHEN is_signed THEN 1 ELSE 0 END AS is_signed, contract_id, contract_num, intervention_date, reason,
                       object_id, object_name, space_id, space_name, renter_id, renter_name,
                       NULL::integer AS provider_id, NULL::text AS provider_name
                FROM events
                UNION ALL
                SELECT 'utility', NULL::integer, c.id, COALESCE(c.contract_num, '—'), c.expiry_date, 'expiry',
                       o.id, o.name, NULL::integer, NULL::text, NULL::integer, NULL::text, p.id, p.name
                FROM unity_provider_contracts c
                JOIN objects o ON o.id = c.object_id
                JOIN unity_providers p ON p.id = c.unity_provider_id
                WHERE NOT c.is_deleted AND NOT o.is_deleted AND NOT p.is_deleted
                  AND (LOWER(COALESCE(c.contract_num, '')) LIKE :query
                       OR LOWER(o.name) LIKE :query OR LOWER(p.name) LIKE :query)
            )
            SQL;
        $params = ['query' => '%'.mb_strtolower(trim($input->query)).'%'];
        $from = ' FROM intervention_records WHERE 1 = 1';
        if ('all' !== $input->action) {
            $from .= ' AND reason = :action';
            $params['action'] = $input->action;
        }
        if ('all' !== $input->contractType) {
            $from .= ' AND contract_type = :contract_type';
            $params['contract_type'] = $input->contractType;
        }
        $connection = $this->em->getConnection();
        $total = (int) $connection->fetchOne($source.' SELECT COUNT(*)'.$from, $params);
        $records = $connection->fetchAllAssociative(
            $source.' SELECT *'.$from.' ORDER BY intervention_date ASC, contract_id DESC, contract_type ASC, reason ASC LIMIT 20 OFFSET :offset',
            [...$params, 'offset' => ($input->page - 1) * 20],
        );
        $items = array_map(static fn (array $record): array => [
            'id' => $record['contract_type'].'-'.$record['contract_id'].'-'.$record['reason'], 'contractId' => (int) $record['contract_id'],
            'contractType' => $record['contract_type'],
            'isSigned' => null === $record['is_signed'] ? null : 1 === (int) $record['is_signed'],
            'contractNum' => $record['contract_num'], 'interventionDate' => $record['intervention_date'],
            'reason' => $record['reason'], 'overdue' => $record['intervention_date'] < $today->format('Y-m-d'),
            'object' => ['id' => (int) $record['object_id'], 'name' => $record['object_name']],
            'space' => null === $record['space_id'] ? null : ['id' => (int) $record['space_id'], 'name' => $record['space_name']],
            'renter' => null === $record['renter_id'] ? null : ['id' => (int) $record['renter_id'], 'name' => $record['renter_name']],
            'unityProvider' => null === $record['provider_id'] ? null : ['id' => (int) $record['provider_id'], 'name' => $record['provider_name']],
        ], $records);

        return ['items' => $items, 'total' => $total, 'page' => $input->page, 'pageSize' => 20];
    }

    public function contractPageForObject(int $objectId, int $page): array
    {
        $source = $this->source();
        $from = ' FROM contracts c JOIN spaces s ON s.id = c.space_id JOIN objects o ON o.id = s.object_id
                  LEFT JOIN events ON events.contract_id = c.id
                  WHERE NOT c.is_deleted AND NOT s.is_deleted AND NOT o.is_deleted AND o.id = :object_id';
        $params = ['query' => '%', 'object_id' => $objectId];
        $connection = $this->em->getConnection();
        $total = (int) $connection->fetchOne($source.' SELECT COUNT(DISTINCT c.id)'.$from, $params);
        $ids = $connection->fetchFirstColumn(
            $source.' SELECT c.id'.$from.' GROUP BY c.id ORDER BY MIN(events.intervention_date) ASC NULLS LAST, c.id DESC LIMIT 20 OFFSET :offset',
            [...$params, 'offset' => ($page - 1) * 20],
        );

        return ['ids' => array_map(intval(...), $ids), 'total' => $total];
    }

    public function requiresAttention(int $contractId, ?\DateTimeImmutable $today = null): bool
    {
        return [] !== array_filter($this->attentionDates($contractId, $today));
    }

    public function attentionDates(int $contractId, ?\DateTimeImmutable $today = null): array
    {
        $records = $this->em->getConnection()->fetchAllAssociative(
            $this->source()." SELECT reason, intervention_date FROM events WHERE contract_id = :id AND EXISTS (SELECT 1 FROM notification_types nt WHERE nt.id = CASE events.reason WHEN 'expiry' THEN CAST(:expiry_type AS INT) ELSE CAST(:indexation_type AS INT) END AND nt.is_active AND events.intervention_date - nt.days <= :today)",
            ['query' => '%', 'expiry_type' => NotificationKind::ContractExpiry->value, 'indexation_type' => NotificationKind::ContractIndexation->value, 'id' => $contractId, 'today' => ($today ?? new \DateTimeImmutable('today'))->format('Y-m-d')],
        );
        $dates = ['expiry' => null, 'indexation' => null];
        foreach ($records as $record) {
            $dates[$record['reason']] = $record['intervention_date'];
        }

        return $dates;
    }

    private function source(): string
    {
        return <<<'SQL'
            WITH records AS (
                SELECT c.id AS contract_id, c.is_signed, c.contract_num, c.contract_date, c.annulment_date,
                       LEAST(c.expiry_date, c.annulment_date) AS effective_end,
                       CASE
                           WHEN c.increase_date IS DISTINCT FROM c.last_indexation_date THEN c.increase_date
                           WHEN c.increase_interval_months IS NOT NULL THEN
                               (c.increase_date + make_interval(months => c.increase_interval_months))::date
                       END AS pending_increase_date,
                       s.id AS space_id, s.name AS space_name, o.id AS object_id, o.name AS object_name,
                       r.id AS renter_id, r.name AS renter_name
                FROM contracts c
                JOIN spaces s ON s.id = c.space_id
                JOIN objects o ON o.id = s.object_id
                JOIN renters r ON r.id = c.renter_id
                WHERE NOT c.is_deleted AND NOT s.is_deleted AND NOT o.is_deleted AND LEAST(c.expiry_date, c.annulment_date) >= c.contract_date
                  AND (LOWER(c.contract_num) LIKE :query OR LOWER(s.name) LIKE :query
                       OR LOWER(o.name) LIKE :query OR LOWER(r.name) LIKE :query)
            ), events AS (
                SELECT records.*, effective_end AS intervention_date, 'expiry' AS reason FROM records
                WHERE annulment_date IS NULL AND NOT EXISTS (
                    SELECT 1 FROM contracts successor
                    WHERE NOT successor.is_deleted AND successor.space_id = records.space_id
                      AND successor.contract_date > records.effective_end
                      AND LEAST(successor.expiry_date, successor.annulment_date) >= successor.contract_date
                )
                UNION ALL
                SELECT records.*, pending_increase_date AS intervention_date, 'indexation' AS reason FROM records
                WHERE pending_increase_date >= contract_date AND pending_increase_date <= effective_end
            )
            SQL;
    }
}
