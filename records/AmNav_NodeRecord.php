<?php
namespace Craft;

class AmNav_NodeRecord extends BaseRecord
{
    public function getTableName()
    {
        return 'amnav_nodes';
    }

    protected function defineAttributes()
    {
        return array(
            'navId'       => array(AttributeType::Number, 'required' => true),
            'parentId'    => array(AttributeType::Number, 'default' => null),
            'order'       => array(AttributeType::Number, 'default' => 0),
            'name'        => array(AttributeType::String, 'required' => true),
            'url'         => array(AttributeType::String, 'default' => null),
            'listClass'   => array(AttributeType::String, 'default' => null),
            'blank'       => array(AttributeType::Bool, 'default' => false),
            'enabled'     => array(AttributeType::Bool, 'default' => true),
            'elementId'   => array(AttributeType::Number, 'default' => null),
            'elementType' => array(AttributeType::String, 'default' => null),
            'locale'      => array(AttributeType::Locale, 'default' => null)
        );
    }

    public function defineIndexes()
    {
        return array(
            array('columns' => array('elementId', 'elementType', 'locale'), 'unique' => false)
        );
    }
}