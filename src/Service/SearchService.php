<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Global header search. ILIKE on Postgres (case-insensitive) is good enough
 * for the assignment's expected corpus size; we never run it inside loops
 * — one query per entity kind.
 *
 * Each result has the same {kind, id, title, subtitle} shape so the
 * frontend can route to /{kind}/{id} uniformly.
 */
final readonly class SearchService
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @return list<array{kind: string, id: int, title: string, subtitle: string}>
     */
    public function search(string $query, int $limit = 10): array
    {
        $needle = mb_strtolower(trim($query));
        if ($needle === '') {
            return [];
        }

        $results = [];
        $like = '%' . $needle . '%';

        $positionStmt = $this->connection->prepare(
            "SELECT id, title, short_description AS subtitle, company AS hint
             FROM position
             WHERE LOWER(title) LIKE :q
                OR LOWER(short_description) LIKE :q
                OR LOWER(company) LIKE :q
             ORDER BY updated_at DESC
             LIMIT :lim"
        );
        $positionStmt->bindValue('q', $like);
        $positionStmt->bindValue('lim', $limit, \Doctrine\DBAL\ParameterType::INTEGER);
        foreach ($positionStmt->executeQuery()->fetchAllAssociative() as $row) {
            $results[] = [
                'kind' => 'positions',
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'subtitle' => trim((string) ($row['hint'] ?? '') . ' — ' . (string) ($row['subtitle'] ?? ''), ' —'),
            ];
        }

        $attributeStmt = $this->connection->prepare(
            "SELECT id, name AS title, description AS subtitle, data_type AS hint
             FROM attribute_definition
             WHERE LOWER(name) LIKE :q
                OR LOWER(description) LIKE :q
             ORDER BY name ASC
             LIMIT :lim"
        );
        $attributeStmt->bindValue('q', $like);
        $attributeStmt->bindValue('lim', $limit, \Doctrine\DBAL\ParameterType::INTEGER);
        foreach ($attributeStmt->executeQuery()->fetchAllAssociative() as $row) {
            $results[] = [
                'kind' => 'attributes',
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'subtitle' => (string) ($row['hint'] ?? '') . ' · ' . (string) ($row['subtitle'] ?? ''),
            ];
        }

        $userStmt = $this->connection->prepare(
            "SELECT u.id, u.email AS title,
                    TRIM(CONCAT(p.first_name, ' ', p.last_name)) AS subtitle
             FROM \"user\" u
             LEFT JOIN profile p ON p.id = u.id
             WHERE LOWER(u.email) LIKE :q
                OR LOWER(p.first_name) LIKE :q
                OR LOWER(p.last_name) LIKE :q
             ORDER BY u.id DESC
             LIMIT :lim"
        );
        $userStmt->bindValue('q', $like);
        $userStmt->bindValue('lim', $limit, \Doctrine\DBAL\ParameterType::INTEGER);
        foreach ($userStmt->executeQuery()->fetchAllAssociative() as $row) {
            $subtitle = (string) ($row['subtitle'] ?? '');
            $results[] = [
                'kind' => 'profile',
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'subtitle' => $subtitle !== '' ? $subtitle : 'user',
            ];
        }

        return $results;
    }
}