<?php
namespace Craft;

class m150217_112800_AmNav_expandPageData extends BaseMigration
{
    public function safeUp()
    {
        // Add columns
        $this->addColumnAfter('amnav_nodes', 'locale', array(ColumnType::Locale, 'default' => null), 'entryId');

        // Add index
        $this->createIndex('amnav_nodes', 'entryId,locale');

        // Update existing records with the primary locale
        craft()->db->createCommand()->update('amnav_nodes', array('locale' => craft()->i18n->getPrimarySiteLocaleId()));
    }
}