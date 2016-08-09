<?php
namespace Craft;

class m150403_093000_AmNav_nodesWithElements extends BaseMigration
{
    public function safeUp()
    {
        // Delete the old index
        $indexName = $this->dbConnection->getIndexName('amnav_nodes', 'entryId,locale');
        $sql = "SHOW INDEX FROM craft_amnav_nodes WHERE Key_name = '" . $indexName . "';";
        $result = $this->dbConnection->createCommand($sql)->execute(array());
        if ($result) {
            $this->dropIndex('amnav_nodes', 'entryId,locale');
        }

        // Edit stuff!
        $this->renameColumn('amnav_nodes', 'entryId', 'elementId');
        $this->alterColumn('amnav_nodes', 'url', array(
            'column' => ColumnType::Varchar,
            'null' => true
        ));

        // Add stuff!
        $this->addColumnAfter('amnav_nodes', 'elementType', array(ColumnType::Varchar, 'default' => null), 'elementId');
        $this->createIndex('amnav_nodes', 'elementId,elementType,locale');

        // Update existing records with an elementType
        $this->update('amnav_nodes', array('elementType' => ElementType::Entry), 'elementId IS NOT NULL');
    }
}
