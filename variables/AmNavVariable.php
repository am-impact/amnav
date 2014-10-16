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
     * Params possibilities:
     * - id                 ID for the navigation UL.
     * - class              Class for the navigation UL.
     * - classActive        The class for the active pages.
     * - classLevel2        Class for children. You can add a classLevel for every level you need
     *
     * - maxLevel           Build the navigation till a certain level.
     * - overrideStatus     Includes every page whatever the status.
     * - startFromId        Begin the navigation at a specifc page ID.
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
     * @param array  $params
     *
     * Params possibilities:
     * - id                 ID for the navigation UL.
     * - class              Class for the navigation UL.
     * - classActive        The class for the active pages.
     * - classLevel2        Class for children. You can add a classLevel for every level you need
     *
     * - maxLevel           Build the navigation till a certain level.
     * - overrideStatus     Includes every page whatever the status.
     * - startFromId        Begin the navigation at a specifc page ID.
     *
     * @return array
     */
    public function getNavRaw($handle, $params = array())
    {
        return craft()->amNav->getNavRaw($handle, $params);
    }
}