<?php

namespace App\Models;

use CodeIgniter\Model;

class RoomModel extends Model
{
    protected $table            = 'rooms';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
    protected $allowedFields    = [
        'floor_id',
        'name',
        'description',
        'room_type',
        'mode',
        'occupancy',
        'vacant_since',
        'inventory_status',
        'floor_verification_status',
        'is_active',
        'archived_at',
        'map_x',
        'map_y',
        'map_w',
        'map_h',
    ];

    protected $validationRules = [
        'name'      => 'required|max_length[100]',
        'floor_id'  => 'required|integer|exists[floors.id]',
        'room_type' => 'permit_empty|in_list[closed,open_area]',
        'mode'      => 'permit_empty|in_list[active,standby,inactive]',
        'occupancy' => 'permit_empty|in_list[occupied,vacant]',
    ];

    protected $validationMessages = [
        'floor_id' => [
            'exists' => 'Lantai yang dipilih tidak valid.',
        ],
    ];
}
