<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class MemoryImageModel extends Model
{
    protected $table            = 'memory_images';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = ['memory_id', 'image_path', 'is_primary'];
    protected $useAutoIncrement = true;
    protected $useTimestamps    = false;
    protected $createdField     = 'created_at';
    protected $updatedField     = '';
}
