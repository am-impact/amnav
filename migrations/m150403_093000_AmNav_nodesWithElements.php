<?php
namespace Craft;

class m150403_093000_AmNav_nodesWithElements extends BaseMigration
{
    public function safeUp()
    {
        // Delete the old index
        $this->dropIndex('amnav_nodes', 'entryId,locale');

        // Edit stuff!
        $this->renameColumn('amnav_nodes', 'entryId', 'elementId');

        // Add stuff!
        $this->addColumnAfter('amnav_nodes', 'elementType', array(ColumnType::Varchar, 'default' => null), 'elementId');
        $this->createIndex('amnav_nodes', 'elementId,elementType,locale');

        // Update existing records with an elementType
        $this->update('amnav_nodes', array('elementType' => ElementType::Entry), 'elementId IS NOT NULL');
    }
}