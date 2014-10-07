<?php
namespace Craft;

class AmNavVariable
{
    /**
     * Get the Plugin's name.
     *
     * @example {{ craft.amNav.name }}
     * @return string
     */
    public function getName()
    {
        $plugin = craft()->plugins->getPlugin('amnav');
        return $plugin->getName();
    }
}