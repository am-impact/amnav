<?php
namespace Craft;

class m150212_145000_AmNav_renamePagesToNodes extends BaseMigration
{
    public function safeUp()
    {
        $this->renameTable('amnav_pages', 'amnav_nodes');
    }
}