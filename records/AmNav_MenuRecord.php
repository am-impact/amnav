<?php
namespace Craft;

class AmNav_MenuRecord extends BaseRecord
{
    public function getTableName()
    {
        return 'amnav_navs';
    }

    protected function defineAttributes()
    {
        return array(
            'name'   => array(AttributeType::String, 'required' => true),
            'handle' => array(AttributeType::String, 'required' => true)
        );
    }
}