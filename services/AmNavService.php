<?php
namespace Craft;

/**
 * Navigation service
 */
class AmNavService extends BaseApplicationComponent
{
    private $_menu;
    private $_params;
    private $_parseHtml = false;
    private $_parseEnvironment = false;
    private $_siteUrl;
    private $_addTrailingSlash = false;
    private $_activePageIds = array();

    /**
     * Get all build menus.
     *
     * @return array
     */
    public function getMenus()
    {
        $menuRecords = AmNav_MenuRecord::model()->findAll();
        return AmNav_MenuModel::populateModels($menuRecords);
    }

    /**
     * Get a menu by its ID.
     *
     * @param int $menuId
     *
     * @return AmNav_MenuModel|null
     */
    public function getMenuById($menuId)
    {
        $menuRecord = AmNav_MenuRecord::model()->findById($menuId);
        if ($menuRecord) {
            return AmNav_MenuModel::populateModel($menuRecord);
        }
        return null;
    }

    /**
     * Get a menu by its handle.
     *
     * @param string $handle
     *
     * @return AmNav_MenuModel|null
     */
    public function getMenuByHandle($handle)
    {
        $menuRecord = AmNav_MenuRecord::model()->findByAttributes(array('handle' => $handle));
        if ($menuRecord) {
            return AmNav_MenuModel::populateModel($menuRecord);
        }
        return null;
    }

    /**
     * Get a menu name by its handle.
     *
     * @param string $handle
     *
     * @return string|null
     */
    public function getMenuNameByHandle($handle)
    {
        $menuRecord = AmNav_MenuRecord::model()->findByAttributes(array('handle' => $handle));
        if ($menuRecord) {
            $menu = AmNav_MenuModel::populateModel($menuRecord);
            return $menu->name;
        }
        return null;
    }

    /**
     * Get all pages by its menu ID.
     *
     * @param int $navId
     *
     * @return array
     */
    public function getPagesByMenuId($navId)
    {
        // Set necessary variables
        $this->_siteUrl = craft()->getSiteUrl();
        $this->_addTrailingSlash = craft()->config->get('addTrailingSlashesToUrls');

        // Start at the root by default
        $parentId = 0;

        // Do we have to start from a specific page ID?
        $startFromId = $this->_getParam('startFromId' , false);
        if ($startFromId !== false) {
            $parentId = $startFromId;
        }

        $pages = craft()->amNav_page->getAllPagesByMenuId($navId);
        if ($this->_parseHtml) {
            return $this->_buildNavHtml($pages, $parentId);
        }
        return $this->_buildNav($pages, $parentId);
    }

    /**
     * Get parent options for given pages.
     *
     * @param array $pages
     * @param bool  $skipFirst
     *
     * @return array
     */
    public function getParentOptions($pages, $skipFirst = false)
    {
        $parentOptions = array();
        if (! $skipFirst) {
            $parentOptions[] = array(
                'label' => '',
                'value' => 0
            );
        }
        foreach ($pages as $page) {
            $label = '';
            for ($i = 1; $i < $page['level']; $i++) {
                $label .= '    ';
            }
            $label .= $page['name'];

            $parentOptions[] = array(
                'label' => $label,
                'value' => $page['id']
            );
            if (isset($page['children'])) {
                foreach($this->getParentOptions($page['children'], true) as $childPage) {
                    $parentOptions[] = $childPage;
                }
            }
        }
        return $parentOptions;
    }

    /**
     * Saves a menu.
     *
     * @param AmNav_MenuModel $menu
     *
     * @throws Exception
     * @return bool
     */
    public function saveMenu(AmNav_MenuModel $menu)
    {
        // Menu data
        if ($menu->id) {
            $menuRecord = AmNav_MenuRecord::model()->findById($menu->id);

            if (! $menuRecord) {
                throw new Exception(Craft::t('No menu exists with the ID “{id}”.', array('id' => $menu->id)));
            }
        }
        else {
            $menuRecord = new AmNav_MenuRecord();
        }

        // Set attributes
        $menuRecord->setAttributes($menu->getAttributes());
        $menuRecord->setAttribute('settings', json_encode($menu->settings));

        // Validate
        $menuRecord->validate();
        $menu->addErrors($menuRecord->getErrors());

        // Save menu
        if (! $menu->hasErrors()) {
            // Save in database
            return $menuRecord->save();
        }
        return false;
    }

    /**
     * Delete a menu by its ID.
     *
     * @param int $menuId
     *
     * @return bool
     */
    public function deleteMenuById($menuId)
    {
        craft()->db->createCommand()->delete('amnav_pages', array('navId' => $menuId));
        return craft()->db->createCommand()->delete('amnav_navs', array('id' => $menuId));
    }

    /**
     * Get a navigation structure as HTML.
     *
     * @param string $handle
     * @param array  $params
     *
     * @throws Exception
     * @return string
     */
    public function getNav($handle, $params)
    {
        $menu = $this->getMenuByHandle($handle);
        if (! $menu) {
            throw new Exception(Craft::t('No menu exists with the handle “{handle}”.', array('handle' => $handle)));
        }
        $this->_menu = $menu;
        // We want correct URLs now
        $this->_parseEnvironment = true;
        // Get the params
        $this->_setParams($params);
        // We want HTML returned
        $this->_parseHtml = true;
        // Return build HTML
        return $this->getPagesByMenuId($menu->id);
    }

    /**
     * Get a navigation structure without any HTML.
     *
     * @param string $handle
     * @param array  $params
     *
     * @throws Exception
     * @return array
     */
    public function getNavRaw($handle, $params)
    {
        $menu = $this->getMenuByHandle($handle);
        if (! $menu) {
            throw new Exception(Craft::t('No menu exists with the handle “{handle}”.', array('handle' => $handle)));
        }
        $this->_menu = $menu;
        // We want correct URLs now
        $this->_parseEnvironment = true;
        // Get the params
        $this->_setParams($params);
        // We don't want HTML returned
        $this->_parseHtml = false;
        // Return the array structure
        return $this->getPagesByMenuId($menu->id);
    }

    /**
     * Get an active page ID for a specific navigation's level.
     *
     * @param string $handle        Navigation handle.
     * @param int    $segmentLevel  Segment level.
     *
     * @return int|bool
     */
    public function getActivePageIdForLevel($handle, $segmentLevel = 1)
    {
        if (isset($this->_activePageIds[$handle][$segmentLevel])) {
            return $this->_activePageIds[$handle][$segmentLevel];
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
     * @param string $url
     *
     * @return string
     */
    private function _parseUrl($url)
    {
        $isAnchor   = substr(str_replace('{siteUrl}', '', $url), 0, 1) == '#';
        $isSiteLink = strpos($url, '{siteUrl}') !== false;
        $isHomepage = str_replace('{siteUrl}', '', $url) == '';
        $url        = str_replace('{siteUrl}', $this->_siteUrl, $url);
        if ($this->_addTrailingSlash && ! $isAnchor && $isSiteLink && ! $isHomepage) {
            $url .= '/';
        }
        return $url;
    }

    /**
     * Check whether the URL is currently active.
     *
     * @param array $page
     *
     * @return bool
     */
    private function _isPageActive($page)
    {
        $url = $page['url'];
        $path = craft()->request->getPath();
        $segments = craft()->request->getSegments();

        $url = str_replace('{siteUrl}', '', $url);
        if ($url == $path) {
            $this->_activePageIds[ $this->_menu->handle ][1] = $page['id'];
            return true;
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
                $this->_activePageIds[ $this->_menu->handle ][$count - 1] = $page['id'];
                return true;
            }
        }
        return false;
    }

    /**
     * Get active entries based on URI.
     *
     * @return array
     */
    private function _getActiveEntries()
    {
        $entries = array();
        $segments = craft()->request->getSegments();

        // Add homepage
        $criteria = craft()->elements->getCriteria(ElementType::Entry);
        $criteria->uri = '__home__';
        $entry = $criteria->first();
        if ($entry) {
            $entries[] = $entry;
        }

        // Find other entries
        if (count($segments)) {
            $count = 0; // Start at second
            $segmentString = $segments[0]; // Add first
            while ($count < count($segments)) {
                // Get entry
                $criteria = craft()->elements->getCriteria(ElementType::Entry);
                $criteria->uri = $segmentString;
                $criteria->status = null;
                $entry = $criteria->first();

                // Add entry to active entries
                if ($entry) {
                    $entries[] = $entry;
                }

                // Search for next possible entry
                $count ++;
                if (isset($segments[$count])) {
                    $segmentString .= '/' . $segments[$count];
                }
            }
        }
        return $entries;
    }

    /**
     * Create the navigation based on parent IDs and order.
     *
     * @param array $pages
     * @param int   $parentId
     * @param int   $level
     *
     * @return array
     */
    private function _buildNav($pages, $parentId = 0, $level = 1)
    {
        // Do we have a maximum level?
        if ($this->_parseEnvironment) {
            $maxLevel = $this->_getParam('maxLevel' , false);
            if ($maxLevel !== false && $level > $maxLevel) {
                return false;
            }
        }

        $nav = array();
        foreach ($pages as $page) {
            if ($page['parentId'] == $parentId) {
                // Do additional stuff if we use this function from the front end
                if ($this->_parseEnvironment) {
                    if ($page['enabled'] || $this->_getParam('overrideStatus', false)) {
                        $page['active'] = $this->_isPageActive($page);
                        $page['url'] = $this->_parseUrl($page['url']);
                    }
                    else {
                        // Skip this page
                        continue;
                    }
                }

                $page['level'] = $level;
                $children = $this->_buildNav($pages, $page['id'], $level + 1);
                if ($children) {
                    $page['children'] = $children;
                }
                $nav[] = $page;
            }
        }
        return $nav;
    }

    /**
     * Create the navigation HTML based on parent IDs and order.
     *
     * @param array $pages
     * @param int   $parentId
     * @param int   $level
     *
     * @return string
     */
    private function _buildNavHtml($pages, $parentId = 0, $level = 1)
    {
        // Do we have a maximum level?
        $maxLevel = $this->_getParam('maxLevel' , false);
        if ($maxLevel !== false && $level > $maxLevel) {
            return false;
        }

        // If we don't find any pages at the end, don't return an empty UL
        $foundPages = false;

        // Create UL
        $nav = '';
        if ($level == 1) {
            if (! $this->_getParam('excludeUl', false)) {
                $nav = sprintf("\n" . '<ul id="%1$s" class="%2$s">',
                    $this->_getParam('id', $this->_menu->handle),
                    $this->_getParam('class', 'nav')
                );
            }
        } else {
            $nav = sprintf("\n" . '<ul class="%1$s">',
                $this->_getParam('classLevel' . $level, 'nav__level' . $level)
            );
        }

        // Add the pages to the navigation, but only if they are enabled
        $count = 0;
        foreach ($pages as $page) {
            if ($page['parentId'] == $parentId && ($page['enabled'] || $this->_getParam('overrideStatus', false))) {
                $count ++;
                $foundPages = true;

                // Get children
                $children = $this->_buildNavHtml($pages, $page['id'], $level + 1);

                // Set page classes
                $pageClasses = array();
                if ($children) {
                    $pageClasses[] = $this->_getParam('classChildren', 'has-children');
                }
                if ($this->_isPageActive($page)) {
                    $pageClasses[] = $this->_getParam('classActive', 'active');
                }
                if ($level == 1 && $count == 1) {
                    $pageClasses[] = $this->_getParam('classFirst', 'first');
                }

                // Add curent page
                $nav .= sprintf("\n" . '<li%1$s><a%5$s href="%2$s"%3$s>%4$s</a>',
                    count($pageClasses) ? ' class="' . implode(' ', $pageClasses) . '"' : '',
                    $this->_parseUrl($page['url']),
                    $page['blank'] ? ' target="_blank"' : '',
                    $page['name'],
                    $this->_getParam('classBlank', false) !== false ? ' class="' . $this->_getParam('classBlank', false) . '"' : ''
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
        if ($foundPages) {
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
        // Get active entries
        $activeEntries = $this->_getActiveEntries();

        // Create breadcrumbs
        $length = count($activeEntries);
        $breadcrumbs = "\n" . sprintf('<%1$s%2$s%3$s xmlns:v="http://rdf.data-vocabulary.org/#">',
            $this->_getParam('wrapper', 'ol'),
            $this->_getParam('id', false) ? ' id="' . $this->_getParam('id', '') . '"' : '',
            $this->_getParam('class', false) ? ' class="' . $this->_getParam('class', '') . '"' : ''
        );
        foreach ($activeEntries as $index => $entry) {
            // First
            if ($index == 0) {
                $breadcrumbs .= sprintf("\n" . '<li typeof="v:Breadcrumb"><a href="%1$s" title="%2$s" rel="v:url" property="v:title">%2$s</a></li>',
                    $entry->url,
                    $this->_getParam('renameHome', $entry->title)
                );
            }
            // Last
            elseif ($index == $length - 1)
            {
                $breadcrumb = sprintf('<span property="v:title">%1$s</span>',
                    $entry->title
                );
                if ($this->_getParam('lastIsLink', false)) {
                    $breadcrumb = sprintf('<a href="%1$s" title="%2$s" rel="v:url" property="v:title">%2$s</a>',
                        $entry->url,
                        $entry->title
                    );
                }
                $breadcrumbs .= sprintf("\n" . '<li class="%1$s" typeof="v:Breadcrumb">%2$s</li>',
                    $this->_getParam('classLast', 'last'),
                    $breadcrumb
                );
            }
            else {
                $breadcrumbs .= sprintf("\n" . '<li typeof="v:Breadcrumb"><a href="%1$s" title="%2$s" rel="v:url" property="v:title">%2$s</a></li>',
                    $entry->url,
                    $entry->title
                );
            }
        }
        $breadcrumbs .= "\n" . sprintf('</%1$s>',
            $this->_getParam('wrapper', 'ol')
        );
        return TemplateHelper::getRaw($breadcrumbs);
    }
}