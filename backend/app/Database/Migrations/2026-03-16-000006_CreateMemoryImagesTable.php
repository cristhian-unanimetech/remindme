<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMemoryImagesTable extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('memory_images')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'memory_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'image_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'is_primary' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('memory_id');
        $this->forge->addForeignKey('memory_id', 'memories', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('memory_images');
    }

    public function down(): void
    {
        $this->forge->dropTable('memory_images', true);
    }
}
