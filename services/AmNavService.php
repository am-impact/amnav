<?php
namespace Craft;

/**
 * Navigation service
 */
class AmNavService extends BaseApplicationComponent
{
    private $_navigation;
    private $_params;
    private $_parseHtml = false;
    private $_parseEnvironment = false;
    private $_siteUrl;
    private $_addTrailingSlash = false;
    private $_activeNodeIds = array();
    private $_activeNodeIdsForLevel = array();

    /**
     * Get all build navigations.
     *
     * @param string $indexBy      [Optional] Return the navigations indexed by an attribute.
     * @param bool   $indexAllData [Optional] Whether to return all the data or just the navigation name.
     *
     * @return array
     */
    public function getNavigations($indexBy = null, $indexAllData = false)
    {
        $navigationRecords = AmNav_NavigationRecord::model()->ordered()->findAll();
        $navigations = AmNav_NavigationModel::populateModels($navigationRecords);
        if ($indexBy !== null) {
            $indexedNavigations = array();
            foreach ($navigations as $navigation) {
                $indexedNavigations[$navigation->$indexBy] = $indexAllData ? $navigation : $navigation->name;
            }
            return $indexedNavigations;
        }
        return $navigations;
    }

    /**
     * Get navigations by a given command.
     *
     * @param array $variables
     *
     * @return array
     */
    public function getNavigationsByCommand($variables)
    {
        // We need to know which type
        if (! isset($variables['command'])) {
            return false;
        }

        // Do we have any navigations?
        $navigations = $this->getNavigations();
        if (! $navigations) {
            craft()->amCommand->setReturnMessage(Craft::t('There are no navigations yet.'));
            return false;
        }

        // Return commands based on given command type
        $commands = array();
        $commandType = $variables['command'] == 'settings' ? 'edit' : 'build';
        foreach ($navigations as $navigation) {
            $commands[] = array(
                'name' => $navigation->name,
                'url'  => UrlHelper::getUrl('amnav/' . $commandType . '/' . $navigation->id)
            );
        }
        return $commands;
    }

    /**
     * Get a navigation by its ID.
     *
     * @param int $navId
     *
     * @return AmNav_NavigationModel|null
     */
    public function getNavigationById($navId)
    {
        $navigationRecord = AmNav_NavigationRecord::model()->findById($navId);
        if ($navigationRecord) {
            return AmNav_NavigationModel::populateModel($navigationRecord);
        }
        return null;
    }

    /**
     * Get a navigation by its handle.
     *
     * @param string $handle
     *
     * @return AmNav_NavigationModel|null
     */
    public function getNavigationByHandle($handle)
    {
        $navigationRecord = AmNav_NavigationRecord::model()->findByAttributes(array('handle' => $handle));
        if ($navigationRecord) {
            return AmNav_NavigationModel::populateModel($navigationRecord);
        }
        return null;
    }

    /**
     * Get a navigation name by its handle.
     *
     * @param string $handle
     *
     * @return string|null
     */
    public function getNavigationNameByHandle($handle)
    {
        $navigationRecord = AmNav_NavigationRecord::model()->findByAttributes(array('handle' => $handle));
        if ($navigationRecord) {
            $navigation = AmNav_NavigationModel::populateModel($navigationRecord);
            return $navigation->name;
        }
        return null;
    }

    /**
     * Get all nodes by its navigation ID.
     *
     * @param int    $navId
     * @param string $locale
     *
     * @return array
     */
    public function getNodesByNavigationId($navId, $locale = null)
    {
        // Fallback to current locale
        $locale = is_null($locale) ? craft()->language : $locale;

        // Set necessary variables
        $this->_siteUrl = craft()->getSiteUrl();
        $this->_addTrailingSlash = craft()->config->get('addTrailingSlashesToUrls');

        // Start at the root by default
        $parentId = 0;

        // Do we have to start from a specific node ID?
        $startFromId = $this->_getParam('startFromId' , false);
        if ($startFromId !== false) {
            $parentId = $startFromId;
        }

        // Get nodes
        $nodes = craft()->amNav_node->getAllNodesByNavigationId($navId, $locale);

        // Find the active nodes if needed
        if ($this->_parseEnvironment) {
            $this->_setActiveNodes($nodes);
        }

        // Return the navigation
        if ($this->_parseHtml) {
            return $this->_buildNavHtml($nodes, $parentId);
        }
        return $this->_buildNav($nodes, $parentId);
    }

    /**
     * Get parent options for given nodes.
     *
     * @param array $nodes
     * @param mixed $maxLevel
     * @param bool  $skipFirst
     *
     * @return array
     */
    public function getParentOptions($nodes, $maxLevel = false, $skipFirst = false)
    {
        $parentOptions = array();
        if (! $skipFirst) {
            $parentOptions[] = array(
                'label' => '',
                'value' => 0
            );
        }
        foreach ($nodes as $node) {
            $label = '';
            for ($i = 1; $i < $node['level']; $i++) {
                $label .= '    ';
            }
            $label .= $node['name'];

            $parentOptions[] = array(
                'label' => $label,
                'value' => $node['id'],
                'disabled' => ($maxLevel !== false && $node['level'] >= $maxLevel) ? true : false
            );
            if (isset($node['children'])) {
                foreach($this->getParentOptions($node['children'], $maxLevel, true) as $childNode) {
                    $parentOptions[] = $childNode;
                }
            }
        }
        return $parentOptions;
    }

    /**
     * Saves a navigation.
     *
     * @param AmNav_NavigationModel $navigation
     *
     * @throws Exception
     * @return bool
     */
    public function saveNavigation(AmNav_NavigationModel $navigation)
    {
        // Navigation data
        if ($navigation->id) {
            $navigationRecord = AmNav_NavigationRecord::model()->findById($navigation->id);

            if (! $navigationRecord) {
                throw new Exception(Craft::t('No navigation exists with the ID “{id}”.', array('id' => $navigation->id)));
            }
        }
        else {
            $navigationRecord = new AmNav_NavigationRecord();
        }

        // Set attributes
        $navigationRecord->setAttributes($navigation->getAttributes());
        $navigationRecord->setAttribute('settings', json_encode($navigation->settings));

        // Validate
        $navigationRecord->validate();
        $navigation->addErrors($navigationRecord->getErrors());

        // Save navigation
        if (! $navigation->hasErrors()) {
            // Save in database
            return $navigationRecord->save();
        }
        return false;
    }

    /**
     * Delete a navigation by its ID.
     *
     * @param int $navId
     *
     * @return bool
     */
    public function deleteNavigationById($navId)
    {
        craft()->db->createCommand()->delete('amnav_nodes', array('navId' => $navId));
        return craft()->db->createCommand()->delete('amnav_navs', array('id' => $navId));
    }

    /**
     * Get a navigation structure as HTML.
     *
     * @param string $handle
     * @param array  $params
     * @param array  $locale
     *
     * @throws Exception
     * @return string
     */
    public function getNav($handle, $params, $locale = null)
    {
        $navigation = $this->getNavigationByHandle($handle);

        // Check for a missing nav and report the error appropriately
        if (! $navigation) {
            $e = new Exception(Craft::t('No navigation exists with the handle “{handle}”.', array('handle' => $handle)));
            if ($this->_isQuietErrorsEnabled()) {
                Craft::log('Error::', $e->getMessage(), LogLevel::Warning);
                return $navigation;
            } else {
                throw $e;
            }
        }

        $this->_navigation = $navigation;
        // We want correct URLs now
        $this->_parseEnvironment = true;
        // Get the params
        $this->_setParams($params);
        // We want HTML returned
        $this->_parseHtml = true;
        // Return build HTML
        return $this->getNodesByNavigationId($navigation->id, $locale);
    }

    /**
     * Get a navigation structure without any HTML.
     *
     * @param string $handle
     * @param array  $params
     * @param string  $locale
     *
     * @throws Exception
     * @return array
     */
    public function getNavRaw($handle, $params, $locale = null)
    {
        $navigation = $this->getNavigationByHandle($handle);

        // Check for a missing nav and report the error appropriately
        if (! $navigation) {
            $e = new Exception(Craft::t('No navigation exists with the handle “{handle}”.', array('handle' => $handle)));
            if ($this->_isQuietErrorsEnabled()) {
                Craft::log('Error::', $e->getMessage(), LogLevel::Warning);
                return $navigation;
            } else {
                throw $e;
            }
        }

        $this->_navigation = $navigation;
        // We want correct URLs now
        $this->_parseEnvironment = true;
        // Get the params
        $this->_setParams($params);
        // We don't want HTML returned
        $this->_parseHtml = false;
        // Return the array structure
        return $this->getNodesByNavigationId($navigation->id, $locale);
    }

    /**
     * Get an active node ID for a specific navigation's level.
     *
     * @param string $handle        Navigation handle.
     * @param int    $segmentLevel  Segment level.
     *
     * @return int|bool
     */
    public function getActiveNodeIdForLevel($handle, $segmentLevel = 1)
    {
        if (isset($this->_activeNodeIdsForLevel[$handle][$segmentLevel])) {
            return $this->_activeNodeIdsForLevel[$handle][$segmentLevel];
        }
        return false;
    }

    /**
     * Get a navigation structure as HTML.
     *
     * @param array $params
     *
     * @return string
     */
    public function getBreadcrumbs($params)
    {
        // Get the params
        $this->_setParams($params);
        // Return built HTML
        return $this->_buildBreadcrumbsHtml();
    }

    /**
     * Set parameters for the navigation HTML output.
     *
     * @param array $params
     */
    private function _setParams($params)
    {
        $this->_params = array();
        foreach ($params as $paramKey => $paramValue) {
            $this->_params[$paramKey] = $paramValue;
        }
    }

    /**
     * Get parameter value.
     *
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    private function _getParam($name, $default)
    {
        return isset($this->_params[$name]) ? $this->_params[$name] : $default;
    }

    /**
     * Parse URL.
     *
     * @param array $node
     *
     * @return string
     */
    private function _parseUrl($node)
    {
        switch ($node['elementType']) {
            case ElementType::Asset:
                $asset = craft()->assets->getFileById($node['elementId'], $node['locale']);
                $url = $asset->getUrl();
                break;

            default:
                $url        = ! empty($node['elementId']) ? '{siteUrl}' . $node['elementUrl'] : $node['url'];
                $url        = str_replace('__home__', '', $url);
                $isAnchor   = substr(str_replace('{siteUrl}', '', $url), 0, 1) == '#';
                $isSiteLink = strpos($url, '{siteUrl}') !== false;
                $isHomepage = str_replace('{siteUrl}', '', $url) == '';
                $url        = str_replace('{siteUrl}', $this->_siteUrl, $url);
                if ($this->_addTrailingSlash && ! $isAnchor && $isSiteLink && ! $isHomepage) {
                    $url .= '/';
                }
                break;
        }
        return $url;
    }

    /**
     * Check if nodes should be active based on the current URL.
     *
     * @param array $nodes
     */
    private function _setActiveNodes($nodes)
    {
        $path = craft()->request->getPath();
        $segments = craft()->request->getSegments();
        $segmentCount = count($segments) > 0 ? count($segments) : 1;

        // Set empty array for specific navigation
        $this->_activeNodeIds[ $this->_navigation->handle ] = array();
        $this->_activeNodeIdsForLevel[ $this->_navigation->handle ] = array();

        foreach ($nodes as $node) {
            if ($node['elementType'] == 'Asset') { continue; }

            $url = ! empty($node['elementId']) ? $node['elementUrl'] : $node['url'];
            $url = str_replace('{siteUrl}', '', $url);
            $url = str_replace('__home__', '', $url);
            if (substr($url, 0, 1) == '/') {
                $url = substr($url, 1); // Fix for relative URLs
            }
            if ($url == $path) {
                $this->_activeNodeIds[ $this->_navigation->handle ][] = $node['id'];
                $this->_activeNodeIdsForLevel[ $this->_navigation->handle ][ $segmentCount ] = $node['id'];
            }
            if (count($segments)) {
                $found = false;
                $count = 1; // Start at second
                $segmentString = $segments[0]; // Add first
                while ($count < count($segments)) {
                    if ($url == $segmentString) {
                        $found = true;
                        break;
                    }
                    $segmentString .= '/' . $segments[$count];
                    $count ++;
                }
                if ($found) {
                    $this->_activeNodeIds[ $this->_navigation->handle ][] = $node['id'];
                    $this->_activeNodeIdsForLevel[ $this->_navigation->handle ][$count] = $node['id'];
                }
            }
        }
    }

    /**
     * Check whether the current node is active.
     *
     * @param array $node
     *
     * @return bool
     */
    private function _isNodeActive($node)
    {
        return in_array($node['id'], $this->_activeNodeIds[ $this->_navigation->handle ]);
    }

    /**
     * Check whether a node has an active child.
     *
     * @param array $nodes
     * @param int   $parentId
     *
     * @return bool
     */
    private function _isChildActive($nodes, $parentId)
    {
        foreach ($nodes as $node) {
            if ($node['parentId'] == $parentId) {
                // Is current node active?
                if (in_array($node['id'], $this->_activeNodeIds[ $this->_navigation->handle ])) {
                    return true;
                }
                // Is any of it's children active?
                $childrenResult = $this->_isChildActive($nodes, $node['id']);
                if ($childrenResult) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Get active elements based on URI.
     *
     * @return array
     */
    private function _getActiveElements()
    {
        $elements = array();
        $segments = craft()->request->getSegments();

        // Add homepage
        $element = craft()->elements->getElementByUri('__home__');
        if ($element) {
            $elements[] = $element;
        }

        // Find other elements
        if (count($segments)) {
            $count = 0; // Start at second
            $segmentString = $segments[0]; // Add first
            while ($count < count($segments)) {
                // Get element
                $element = craft()->elements->getElementByUri($segmentString);

                // Add element to active elements
                if ($element) {
                    $elements[] = $element;
                }

                // Search for next possible element
                $count ++;
                if (isset($segments[$count])) {
                    $segmentString .= '/' . $segments[$count];
                }
            }
        }
        return $elements;
    }

    /**
     * Create the navigation based on parent IDs and order.
     *
     * @param array $nodes
     * @param int   $parentId
     * @param int   $level
     *
     * @return array
     */
    private function _buildNav($nodes, $parentId = 0, $level = 1)
    {
        // Do we have a maximum level?
        if ($this->_parseEnvironment) {
            $maxLevel = $this->_getParam('maxLevel' , false);
            if ($maxLevel !== false && $level > $maxLevel) {
                return false;
            }
        }

        $nav = array();
        foreach ($nodes as $node) {
            if ($node['parentId'] == $parentId) {
                // Do additional stuff if we use this function from the front end
                if ($this->_parseEnvironment) {
                    if ($node['enabled'] || $this->_getParam('overrideStatus', false)) {
                        $node['active'] = $this->_isNodeActive($node);
                        $node['hasActiveChild'] = $this->_isChildActive($nodes, $node['id']);
                        $node['url'] = $this->_parseUrl($node);
                    }
                    else {
                        // Skip this node
                        continue;
                    }
                }

                $node['level'] = $level;
                $children = $this->_buildNav($nodes, $node['id'], $level + 1);
                $node['hasChildren'] = $children ? true : false;
                if ($children) {
                    $node['children'] = $children;
                }
                $nav[] = $node;
            }
        }
        return $nav;
    }

    /**
     * Create the navigation HTML based on parent IDs and order.
     *
     * @param array $nodes
     * @param int   $parentId
     * @param int   $level
     *
     * @return string
     */
    private function _buildNavHtml($nodes, $parentId = 0, $level = 1)
    {
        // Do we have a maximum level?
        $maxLevel = $this->_getParam('maxLevel' , false);
        if ($maxLevel !== false && $level > $maxLevel) {
            return false;
        }

        // If we don't find any nodes at the end, don't return an empty UL
        $foundNodes = false;

        // Create UL
        $nav = '';
        if ($level == 1) {
            if (! $this->_getParam('excludeUl', false)) {
                $id = $this->_getParam('id', $this->_navigation->handle);
                $class = $this->_getParam('class', 'nav');

                $nav = sprintf("\n" . '<ul%1$s%2$s>',
                    $id !== false ? ' id="' . $id . '"' : '',
                    $class !== false ? ' class="' . $class . '"' : ''
                );
            }
        } else {
            $nav = sprintf("\n" . '<ul class="%1$s">',
                $this->_getParam('classLevel' . $level, 'nav__level' . $level)
            );
        }

        // Add the nodes to the navigation, but only if they are enabled
        $count = 0;
        foreach ($nodes as $node) {
            if ($node['parentId'] == $parentId && ($node['enabled'] || $this->_getParam('overrideStatus', false))) {
                $count ++;
                $foundNodes = true;

                // Get children
                $children = $this->_buildNavHtml($nodes, $node['id'], $level + 1);

                // Set node classes
                $nodeClasses = array();
                if ($children) {
                    $nodeClasses[] = $this->_getParam('classChildren', 'has-children');
                }
                if ($this->_isNodeActive($node)) {
                    $nodeClasses[] = $this->_getParam('classActive', 'active');
                }
                if ($this->_getParam('ignoreActiveChilds', false) === false) {
                    if ($this->_isChildActive($nodes, $node['id']) && ! in_array($this->_getParam('classActive', 'active'), $nodeClasses)) {
                        $nodeClasses[] = $this->_getParam('classActive', 'active');
                    }
                }
                if ($level == 1 && $count == 1) {
                    $nodeClasses[] = $this->_getParam('classFirst', 'first');
                }
                if (! empty($node['listClass'])) {
                    $nodeClasses[] = $node['listClass'];
                }

                // Set hyperlink attributes
                $hyperlinkAttributes = array(
                    'title="' . $node['name'] . '"'
                );
                if ($node['blank']) {
                    $hyperlinkAttributes[] = 'target="_blank"';

                    if ($this->_getParam('classBlank', false) !== false) {
                        $hyperlinkAttributes[] = 'class="' . $this->_getParam('classBlank', '') . '"';
                    }
                }
                if ($this->_getParam('linkRel', false) !== false) {
                    $hyperlinkAttributes[] = 'rel="' . $this->_getParam('linkRel', '') . '"';
                }

                // Add curent node
                $nav .= sprintf("\n" . '<li%1$s><a href="%2$s"%4$s>%3$s</a>',
                    count($nodeClasses) ? ' class="' . implode(' ', $nodeClasses) . '"' : '',
                    $this->_parseUrl($node),
                    $node['name'],
                    ' ' . implode(' ', $hyperlinkAttributes)
                );

                // Add children to the navigation
                if ($children) {
                    $nav .= $children;
                }
                $nav .= '</li>';
            }
        }
        if ($level == 1) {
            if (! $this->_getParam('excludeUl', false)) {
                $nav .= "\n</ul>";
            }
        }
        else {
            $nav .= "\n</ul>";
        }
        if ($foundNodes) {
            return TemplateHelper::getRaw($nav);
        }
        else {
            return false;
        }
    }

    /**
     * Create the breadcrumbs HTML.
     *
     * @return string
     */
    private function _buildBreadcrumbsHtml()
    {
        // Get active elements
        $nodes = $this->_getActiveElements();

        // Do we have custom nodes?
        $customNodes = $this->_getParam('customNodes', false);
        if ($customNodes && is_array($customNodes) && count($customNodes)) {
            $nodes = array_merge($nodes, $customNodes);
        }

        // Create breadcrumbs
        $length = count($nodes);
        $breadcrumbs = "\n" . sprintf('<%1$s%2$s%3$s xmlns:v="http://rdf.data-vocabulary.org/#">',
            $this->_getParam('wrapper', 'ol'),
            $this->_getParam('id', false) ? ' id="' . $this->_getParam('id', '') . '"' : '',
            $this->_getParam('class', false) ? ' class="' . $this->_getParam('class', '') . '"' : ''
        );

        // Before text
        if ($this->_getParam('beforeText', false)) {
            $breadcrumbs .= sprintf("\n" . '<li%1$s><span>%2$s</span></li>',
                $this->_getParam('classDefault', false) ? ' class="' . $this->_getParam('classDefault', '') . '"' : '',
                $this->_getParam('beforeText', '')
            );
        }

        foreach ($nodes as $index => $node) {
            $nodeTitle = is_array($node) ? (isset($node['title']) ? $node['title'] : Craft::t('Unknown')) : $node->title;
            $nodeUrl = is_array($node) ? (isset($node['url']) ? $node['url'] : '') : $node->url;

            // Gather node classes
            $childClasses = array();
            if ($this->_getParam('classDefault', false)) {
                $childClasses[] = $this->_getParam('classDefault', '');
            }

            // First
            if ($index == 0) {
                $childClasses[] = $this->_getParam('classFirst', 'first');
                $breadcrumbs .= sprintf("\n" . '<li%1$s typeof="v:Breadcrumb"><a href="%2$s" title="%3$s" rel="v:url" property="v:title">%3$s</a></li>',
                    $childClasses ? ' class="' . implode(' ', $childClasses) . '"' : '',
                    $nodeUrl,
                    $this->_getParam('renameHome', $nodeTitle)
                );
            }
            // Last
            elseif ($index == $length - 1)
            {
                $childClasses[] = $this->_getParam('classLast', 'last');
                $breadcrumb = sprintf('<span property="v:title">%1$s</span>',
                    $nodeTitle
                );
                if ($this->_getParam('lastIsLink', false)) {
                    $breadcrumb = sprintf('<a href="%1$s" title="%2$s" rel="v:url" property="v:title">%2$s</a>',
                        $nodeUrl,
                        $nodeTitle
                    );
                }
                $breadcrumbs .= sprintf("\n" . '<li%1$s typeof="v:Breadcrumb">%2$s</li>',
                    $childClasses ? ' class="' . implode(' ', $childClasses) . '"' : '',
                    $breadcrumb
                );
            }
            else {
                $breadcrumbs .= sprintf("\n" . '<li%1$s typeof="v:Breadcrumb"><a href="%2$s" title="%3$s" rel="v:url" property="v:title">%3$s</a></li>',
                    $childClasses ? ' class="' . implode(' ', $childClasses) . '"' : '',
                    $nodeUrl,
                    $nodeTitle
                );
            }
        }
        $breadcrumbs .= "\n" . sprintf('</%1$s>',
            $this->_getParam('wrapper', 'ol')
        );
        return TemplateHelper::getRaw($breadcrumbs);
    }

    /**
     * Check whether to log errors or throw them.
     *
     * @return bool
     */
    private function _isQuietErrorsEnabled()
    {
        $plugin = craft()->plugins->getPlugin('amnav');
        $settings = $plugin->getSettings();
        if($settings->quietErrors) {
            return true;
        }

        return false;
    }

}
