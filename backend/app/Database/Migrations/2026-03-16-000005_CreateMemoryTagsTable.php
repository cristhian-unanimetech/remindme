<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMemoryTagsTable extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('memory_tags')) {
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
            'tag_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['memory_id', 'tag_id']);
        $this->forge->addKey('tag_id');
        $this->forge->addForeignKey('memory_id', 'memories', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('tag_id', 'tags', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('memory_tags');
    }

    public function down(): void
    {
        $this->forge->dropTable('memory_tags', true);
    }
}
