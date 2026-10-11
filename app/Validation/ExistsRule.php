<?php

namespace App\Validation;

/**
 * Rule kustom: exists[table.field] — memastikan nilai ada di tabel.
 * Dipakai model (RoomModel, DeviceModel, dll) untuk cek foreign key.
 */
class ExistsRule
{
    public function exists($str, $params): bool
    {
        $str = (string) $str;

        if (strpos((string) $params, '.') === false) {
            return false;
        }

        [$table, $field] = explode('.', (string) $params, 2);

        $db = \Config\Database::connect();

        try {
            return $db->table($table)->where($field, $str)->countAllResults() > 0;
        } catch (\Throwable $e) {
            log_message('error', 'ExistsRule gagal: {msg}', ['msg' => $e->getMessage()]);

            return false;
        }
    }
}
