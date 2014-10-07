<?php
namespace Craft;

class AmNav_PageRecord extends BaseRecord
{
    public function getTableName()
    {
        return 'amnav_pages';
    }

    protected function defineAttributes()
    {
        return array(
            'nav_id'  => array(AttributeType::Number, 'required' => true),
            'parent'  => array(AttributeType::Number, 'default' => null),
            'order'   => array(AttributeType::Number, 'default' => 0),
            'title'   => array(AttributeType::String, 'required' => true),
            'url'     => array(AttributeType::String, 'required' => true),
            'blank'   => array(AttributeType::Bool, 'default' => false),
            'enabled' => array(AttributeType::Bool, 'default' => true)
        );
    }
}