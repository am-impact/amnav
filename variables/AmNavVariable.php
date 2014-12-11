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
     * - class              Class name for the navigation UL.
     * - classActive        Class name for the active pages.
     * - classBlank         Class name for hyperlinks that have a _blank target.
     * - classLevel2        Class name for the children UL. You can add a classLevel for every level you need (e.g.: classLevel2, classLevel3).
     * - classChildren      Class name for a page that has children.
     * - classFirst         Class name for the first page in the navigation.
     *
     * - excludeUl          Exclude the main UL wrapper.
     * - maxLevel           Build the navigation till a certain level.
     * - overrideStatus     Includes every page whatever the status.
     * - startFromId        Begin the navigation at a specific page ID.
     *
     * @return string
     */
    public function getNav($handle, $params = array())
    {
        return craft()->amNav->getNav($handle, $params);
    }

    /**
     * Get a navigation title using its handle.
     *
     * @param string $handle
     *
     * @return string
     */
    public function getNavName($handle)
    {
        return craft()->amNav->getMenuNameByHandle($handle);
    }

    /**
     * Get a navigation structure without any HTML.
     *
     * @param string $handle
     * @param array  $params
     *
     * Params possibilities:
     * - maxLevel           Build the navigation till a certain level.
     * - overrideStatus     Includes every page whatever the status.
     * - startFromId        Begin the navigation at a specific page ID.
     *
     * @return array
     */
    public function getNavRaw($handle, $params = array())
    {
        return craft()->amNav->getNavRaw($handle, $params);
    }

    /**
     * Get an active page ID for a specific navigation's level.
     *
     * @param string $handle        Navigation handle.
     * @param int    $segmentLevel  Segment level.
     */
    public function getActivePageIdForLevel($handle, $segmentLevel = 1)
    {
        return craft()->amNav->getActivePageIdForLevel($handle, $segmentLevel);
    }

    /**
     * Get breadcrumbs as HTML.
     *
     * @param array  $params
     *
     * Params possibilities:
     * - id             ID for the breadcrumbs wrapper.
     * - class          Class name for the breadcrumbs wrapper.
     * - classLast      Class name for the last breadcrumb.
     * - wrapper        Wrapper element without the < and >.
     * - renameHome     Change the title of the home entry.
     * - lastIsLink     Whether the last breadcrumb should be a link.
     *
     * @return string
     */
    public function getBreadcrumbs($params = array())
    {
        return craft()->amNav->getBreadcrumbs($params);
    }
}