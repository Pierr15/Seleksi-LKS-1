<?php

namespace App\Database\Seeds\Concerns;

/**
 * Metode bantu untuk seeder idempoten.
 */
trait IdempotentSeeder
{
    protected function rowCount(string $table, array $where): int
    {
        return $this->db->table($table)->where($where)->countAllResults();
    }

    protected function exists(string $table, array $where): bool
    {
        return $this->rowCount($table, $where) > 0;
    }
}
