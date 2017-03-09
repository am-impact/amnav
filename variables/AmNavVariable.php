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
     * @param string  $locale
     *
     * Params possibilities:
     * - id                  ID for the navigation UL.
     * - class               Class name for the navigation UL.
     * - classActive         Class name for the active nodes.
     * - classBlank          Class name for hyperlinks that have a _blank target.
     * - classLevel2         Class name for the children UL. You can add a classLevel for every level you need (e.g.: classLevel2, classLevel3).
     * - classChildren       Class name for a node that has children.
     * - classFirst          Class name for the first node in the navigation.
     *
     * - linkRel             Rel (relationship) for each hyperlink.
     *
     * - excludeUl           Exclude the main UL wrapper.
     * - maxLevel            Build the navigation till a certain level.
     * - overrideStatus      Includes every node whatever the status.
     * - startFromId         Begin the navigation at a specific node ID.
     * - ignoreActiveChilds  Won't make a node active if a node's child is active.
     *
     * @return string
     */
    public function getNav($handle, $params = array(), $locale = null)
    {
        return craft()->amNav->getNav($handle, $params, $locale);
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
        return craft()->amNav->getNavigationNameByHandle($handle);
    }

    /**
     * Get a navigation structure without any HTML.
     *
     * @param string $handle
     * @param array  $params
     * @param string  $locale
     *
     * Params possibilities:
     * - maxLevel           Build the navigation till a certain level.
     * - overrideStatus     Includes every node whatever the status.
     * - startFromId        Begin the navigation at a specific node ID.
     *
     * @return array
     */
    public function getNavRaw($handle, $params = array(), $locale = null)
    {
        return craft()->amNav->getNavRaw($handle, $params, $locale);
    }

    /**
     * Get an active node ID for a specific navigation's level.
     *
     * @param string $handle        Navigation handle.
     * @param int    $segmentLevel  Segment level.
     *
     * @deprecated Use getActiveNodeIdForLevel instead.
     */
    public function getActivePageIdForLevel($handle, $segmentLevel = 1)
    {
        return $this->getActiveNodeIdForLevel($handle, $segmentLevel);
    }

    /**
     * Get an active node ID for a specific navigation's level.
     *
     * @param string $handle        Navigation handle.
     * @param int    $segmentLevel  Segment level.
     */
    public function getActiveNodeIdForLevel($handle, $segmentLevel = 1)
    {
        return craft()->amNav->getActiveNodeIdForLevel($handle, $segmentLevel);
    }

    /**
     * Get a node by its ID.
     *
     * @param int $nodeId
     *
     * @return AmNav_NodeModel|null
     */
    public function getNodeById($id)
    {
        return craft()->amNav_node->getNodeById($id);
    }

    /**
     * Get breadcrumbs as HTML.
     *
     * @param array  $params
     *
     * Params possibilities:
     * - id             ID for the breadcrumbs wrapper.
     * - class          Class name for the breadcrumbs wrapper.
     * - classDefault   Default class name for every breadcrumb.
     * - classFirst     Class name for the first breadcrumb.
     * - classLast      Class name for the last breadcrumb.
     * - wrapper        Wrapper element without the < and >.
     * - beforeText     Text before the first item, like 'You are here:'.
     * - renameHome     Change the title of the home entry.
     * - lastIsLink     Whether the last breadcrumb should be a link.
     * - customNodes    Add custom nodes after the elements are handled.
     *                  [ { title: 'A title', url: 'an url' }, { title: 'A title', url: 'an url' } ]
     *
     * @return string
     */
    public function getBreadcrumbs($params = array())
    {
        return craft()->amNav->getBreadcrumbs($params);
    }

    /**
     * Get a breadcrumbs without any HTML.
     *
     * @return array
     */
    public function getBreadcrumbsRaw()
    {
        return craft()->amNav->getBreadcrumbsRaw();
    }
}
