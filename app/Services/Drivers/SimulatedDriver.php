<?php

namespace App\Services\Drivers;

/**
 * Driver simulasi lokal.
 *
 * Tidak menyentuh perangkat fisik apa pun. Status yang dihasilkan adalah
 * data simulasi dan selalu ditandai dengan source = 'simulation'.
 */
class SimulatedDriver implements DeviceDriverInterface
{
    public function turnOn(array $device): array
    {
        return [
            'success'          => true,
            'message'          => 'Perintah simulasi ON diterima.',
            'power_state'      => 'on',
            'connection_state' => 'online',
        ];
    }

    public function turnOff(array $device): array
    {
        return [
            'success'          => true,
            'message'          => 'Perintah simulasi OFF diterima.',
            'power_state'      => 'off',
            'connection_state' => 'online',
        ];
    }

    public function setTemperature(array $device, float $temperature): array
    {
        return [
            'success'            => true,
            'message'            => 'Suhu target diubah ke ' . $temperature . '°C.',
            'power_state'        => 'on',
            'connection_state'   => 'online',
            'target_temperature' => $temperature,
        ];
    }

    public function setMode(array $device, string $mode): array
    {
        return [
            'success'          => true,
            'message'          => 'Mode AC diubah ke ' . $mode . '.',
            'power_state'      => 'on',
            'connection_state' => 'online',
            'ac_mode'          => $mode,
        ];
    }

    public function setBoost(array $device, bool $boost): array
    {
        return [
            'success'          => true,
            'message'          => $boost ? 'Mode Powerful diaktifkan.' : 'Mode Powerful dinonaktifkan.',
            'power_state'      => 'on',
            'connection_state' => 'online',
            'ac_boost'         => $boost,
        ];
    }

    public function sleep(array $device): array
    {
        return [
            'success'          => true,
            'message'          => 'Perangkat masuk mode sleep.',
            'power_state'      => 'sleep',
            'connection_state' => 'online',
        ];
    }

    public function shutdown(array $device): array
    {
        return [
            'success'          => true,
            'message'          => 'Perangkat dimatikan (shutdown).',
            'power_state'      => 'off',
            'connection_state' => 'online',
        ];
    }
}
