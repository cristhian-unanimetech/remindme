<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class MemoryTagModel extends Model
{
    protected $table            = 'memory_tags';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = ['memory_id', 'tag_id'];
    protected $useAutoIncrement = true;
    protected $useTimestamps    = false;
}
