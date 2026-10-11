<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
    protected $allowedFields    = [
        'name',
        'email',
        'password_hash',
        'role',
        'theme',
        'is_active',
        'last_login_at',
        'failed_login_attempts',
        'locked_until',
    ];

    protected $validationRules = [
        'name'  => 'required|max_length[100]',
        'email' => 'required|valid_email|max_length[190]|is_unique[users.email,id,{id}]',
        'role'  => 'required|in_list[admin,operator,viewer]',
    ];

    protected $validationMessages = [
        'email' => [
            'is_unique' => 'Email tersebut sudah digunakan oleh pengguna lain.',
        ],
    ];
}
