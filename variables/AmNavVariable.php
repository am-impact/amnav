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

    /**
     * Get a navigation structure as HTML.
     *
     * @param string $handle
     * @param array  $params
     *
     * @return string
     */
    public function getNav($handle, $params = array())
    {
        return craft()->amNav->getNav($handle, $params);
    }

    /**
     * Get a navigation structure without any HTML.
     *
     * @param string $handle
     *
     * @return array
     */
    public function getNavRaw($handle)
    {
        return craft()->amNav->getNavRaw($handle);
    }
}