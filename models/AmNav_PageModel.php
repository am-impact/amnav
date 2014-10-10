<?php
namespace Craft;

class AmNav_PageModel extends BaseModel
{
    protected function defineAttributes()
    {
        return array(
            'id'       => AttributeType::Number,
            'navId'    => AttributeType::Number,
            'parentId' => AttributeType::Number,
            'order'    => AttributeType::Number,
            'name'     => AttributeType::String,
            'url'      => AttributeType::String,
            'blank'    => AttributeType::Bool,
            'enabled'  => AttributeType::Bool,
            'entryId'  => AttributeType::Number
        );
    }
}