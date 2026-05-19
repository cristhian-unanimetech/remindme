<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class MemoryModel extends Model
{
    protected $table            = 'memories';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'title',
        'content',
        'memory_date',
        'spotify_url',
        'song_title',
        'artist_name',
        'album_name',
        'cover_url',
        'embed_url',
        'embed_html',
        'mood_color',
    ];
    protected $useAutoIncrement = true;
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';
}
