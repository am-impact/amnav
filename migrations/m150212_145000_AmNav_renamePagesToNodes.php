<?php
namespace Craft;

class m150212_145000_AmNav_renamePagesToNodes extends BaseMigration
{
    public function safeUp()
    {
        if (craft()->db->tableExists('amnav_pages')) {
            $this->renameTable('amnav_pages', 'amnav_nodes');
        }
    }
}