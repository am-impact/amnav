<?php
namespace Craft;

class AmNav_NavigationModel extends BaseModel
{
    protected function defineAttributes()
    {
        return array(
            'id'          => AttributeType::Number,
            'name'        => AttributeType::String,
            'handle'      => AttributeType::String,
            'settings'    => array(AttributeType::Mixed, 'default' => array(
                'canDeleteFromLevel' => '',
                'canMoveFromLevel' => '',
                'maxLevels' => '',
                'entrySources' => ''
            )),
            'dateCreated' => AttributeType::DateTime,
            'dateUpdated' => AttributeType::DateTime
        );
    }
}