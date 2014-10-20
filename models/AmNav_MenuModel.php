<?php
namespace Craft;

class AmNav_MenuModel extends BaseModel
{
    protected function defineAttributes()
    {
        return array(
            'id'       => AttributeType::Number,
            'name'     => AttributeType::String,
            'handle'   => AttributeType::String,
            'settings' => array(AttributeType::Mixed, 'default' => array(
                'canDeleteFirstLevel' => true,
                'canMoveFirstLevel' => true,
                'maxLevels' => ''
            ))
        );
    }
}