<?php

namespace Config;

use App\Libraries\Authenticator;
use App\Services\AuditLogService;
use App\Services\AutomationService;
use App\Services\DashboardService;
use App\Services\DeviceControlService;
use App\Services\DevicePositionService;
use App\Services\InventoryService;
use App\Services\OccupancyService;
use App\Services\OverrideService;
use App\Services\SchedulerService;
use App\Services\SettingsService;
use App\Services\SimulationSensorService;
use CodeIgniter\Config\BaseService;

/**
 * Registrasi service aplikasi ORBIT Building.
 */
class Services extends BaseService
{
    public static function auth(bool $getShared = true): Authenticator
    {
        if ($getShared) {
            return static::getSharedInstance('auth');
        }

        return new Authenticator();
    }

    public static function auditLog(bool $getShared = true): AuditLogService
    {
        if ($getShared) {
            return static::getSharedInstance('auditLog');
        }

        return new AuditLogService();
    }

    public static function settings(bool $getShared = true): SettingsService
    {
        if ($getShared) {
            return static::getSharedInstance('settings');
        }

        return new SettingsService();
    }

    public static function deviceControl(bool $getShared = true): DeviceControlService
    {
        if ($getShared) {
            return static::getSharedInstance('deviceControl');
        }

        return new DeviceControlService();
    }

    public static function devicePosition(bool $getShared = true): DevicePositionService
    {
        if ($getShared) {
            return static::getSharedInstance('devicePosition');
        }

        return new DevicePositionService();
    }

    public static function inventory(bool $getShared = true): InventoryService
    {
        if ($getShared) {
            return static::getSharedInstance('inventory');
        }

        return new InventoryService();
    }

    public static function occupancy(bool $getShared = true): OccupancyService
    {
        if ($getShared) {
            return static::getSharedInstance('occupancy');
        }

        return new OccupancyService();
    }

    public static function overrideService(bool $getShared = true): OverrideService
    {
        if ($getShared) {
            return static::getSharedInstance('overrideService');
        }

        return new OverrideService();
    }

    public static function automation(bool $getShared = true): AutomationService
    {
        if ($getShared) {
            return static::getSharedInstance('automation');
        }

        return new AutomationService();
    }

    public static function scheduler(bool $getShared = true): SchedulerService
    {
        if ($getShared) {
            return static::getSharedInstance('scheduler');
        }

        return new SchedulerService();
    }

    public static function simulationSensor(bool $getShared = true): SimulationSensorService
    {
        if ($getShared) {
            return static::getSharedInstance('simulationSensor');
        }

        return new SimulationSensorService();
    }

    public static function dashboard(bool $getShared = true): DashboardService
    {
        if ($getShared) {
            return static::getSharedInstance('dashboard');
        }

        return new DashboardService();
    }
}
