<?php
namespace Craft;

class m150403_093000_AmNav_nodesWithElements extends BaseMigration
{
    public function safeUp()
    {
        // Delete the old index
        $this->renameTable('amnav_nodes', 'amnav_pages');
        $this->dropIndex('amnav_pages', 'entryId,locale');
        $this->renameTable('amnav_pages', 'amnav_nodes');

        // Edit stuff!
        $this->renameColumn('amnav_nodes', 'entryId', 'elementId');

        // Add stuff!
        $this->addColumnAfter('amnav_nodes', 'elementType', array(ColumnType::Varchar, 'default' => null), 'elementId');
        $this->createIndex('amnav_nodes', 'elementId,elementType,locale');

        // Update existing records with an elementType
        $this->update('amnav_nodes', array('elementType' => ElementType::Entry), 'elementId IS NOT NULL');
    }
}