<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class TagModel extends Model
{
    protected $table            = 'tags';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = ['name'];
    protected $useAutoIncrement = true;
    protected $useTimestamps    = false;
    protected $createdField     = 'created_at';
    protected $updatedField     = '';
}
