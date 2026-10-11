<?php

namespace App\Services\Drivers;

/**
 * Kontrak adapter perangkat.
 *
 * Untuk MVP hanya ada implementasi simulasi. Pada masa depan, adapter
 * MQTT / AWS IoT Core / gateway ESP32 dapat ditambahkan tanpa mengubah
 * controller dan service yang memanggil interface ini.
 *
 * Penting: keberhasilan di sini berarti "adapter melaporkan sukses".
 * Pada implementasi nyata, keberhasilan harus berdasarkan acknowledgement
 * atau telemetry perangkat fisik.
 */
interface DeviceDriverInterface
{
    /**
     * Menyalakan perangkat.
     *
     * @param array<string, mixed> $device Baris perangkat dari database
     *
     * @return array{success: bool, message: string, power_state: string, connection_state: string}
     */
    public function turnOn(array $device): array;

    /**
     * Mematikan perangkat.
     *
     * @param array<string, mixed> $device Baris perangkat dari database
     *
     * @return array{success: bool, message: string, power_state: string, connection_state: string}
     */
    public function turnOff(array $device): array;

    /**
     * Mengubah suhu target AC (16–30 °C).
     *
     * @param array<string, mixed> $device     Baris perangkat dari database
     * @param float                $temperature Suhu target dalam °C
     *
     * @return array{success: bool, message: string, power_state: string, connection_state: string, target_temperature: float}
     */
    public function setTemperature(array $device, float $temperature): array;

    /**
     * Mengubah mode AC (cool / dry).
     *
     * @param array<string, mixed> $device Baris perangkat dari database
     * @param string               $mode   'cool' atau 'dry'
     *
     * @return array{success: bool, message: string, power_state: string, connection_state: string, ac_mode: string}
     */
    public function setMode(array $device, string $mode): array;

    /**
     * Mengaktifkan / menonaktifkan mode boost (powerful) AC.
     *
     * @param array<string, mixed> $device Baris perangkat dari database
     * @param bool                 $boost  true = aktif
     *
     * @return array{success: bool, message: string, power_state: string, connection_state: string, ac_boost: bool}
     */
    public function setBoost(array $device, bool $boost): array;

    /**
     * Mengubah perangkat ke mode sleep.
     *
     * @param array<string, mixed> $device Baris perangkat dari database
     *
     * @return array{success: bool, message: string, power_state: string, connection_state: string}
     */
    public function sleep(array $device): array;

    /**
     * Mematikan perangkat secara permanen (shutdown).
     *
     * @param array<string, mixed> $device Baris perangkat dari database
     *
     * @return array{success: bool, message: string, power_state: string, connection_state: string}
     */
    public function shutdown(array $device): array;
}
