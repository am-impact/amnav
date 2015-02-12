<?php
namespace Craft;

class AmNav_NavigationRecord extends BaseRecord
{
    public function getTableName()
    {
        return 'amnav_navs';
    }

    protected function defineAttributes()
    {
        return array(
            'name'     => array(AttributeType::String, 'required' => true),
            'handle'   => array(AttributeType::String, 'required' => true),
            'settings' => array(AttributeType::Mixed)
        );
    }

    public function defineIndexes()
    {
        return array(
            array('columns' => array('handle'), 'unique' => true)
        );
    }

    public function scopes()
    {
        return array(
            'ordered' => array('order' => 'handle')
        );
    }
}