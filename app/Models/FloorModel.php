<?php

namespace App\Models;

use CodeIgniter\Model;

class FloorModel extends Model
{
    protected $table            = 'floors';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
    protected $allowedFields    = ['name', 'slug', 'label', 'sort_order'];

    protected $validationRules = [
        'name' => 'required|max_length[50]',
        'slug' => 'required|max_length[50]',
    ];
}
