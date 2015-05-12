<?php
namespace Craft;

class m150512_105600_AmNav_addOptionalClass extends BaseMigration
{
    public function safeUp()
    {
        // Add column
        $this->addColumnAfter('amnav_nodes', 'listClass', array(ColumnType::Varchar, 'default' => null), 'url');
    }
}