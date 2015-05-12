<?php
namespace Craft;

class AmNav_NodeModel extends BaseModel
{
    protected function defineAttributes()
    {
        return array(
            'id'          => AttributeType::Number,
            'navId'       => AttributeType::Number,
            'parentId'    => AttributeType::Number,
            'order'       => AttributeType::Number,
            'name'        => AttributeType::String,
            'url'         => AttributeType::String,
            'listClass'   => AttributeType::String,
            'blank'       => AttributeType::Bool,
            'enabled'     => AttributeType::Bool,
            'elementId'   => AttributeType::Number,
            'elementType' => AttributeType::String,
            'locale'      => AttributeType::Locale
        );
    }
}