<?php
namespace Craft;

/**
 * Navigation service
 */
class AmNavService extends BaseApplicationComponent
{
    private $_menu;
    private $_params = array();
    private $_parseHtml = false;
    private $_parseEnvironment = false;

    /**
     * Get all build menus.
     *
     * @return array
     */
    public function getMenus()
    {
        return AmNav_MenuRecord::model()->findAll();
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
     * Get all pages by its menu ID.
     *
     * @param int $navId
     *
     * @return array
     */
    public function getPagesByMenuId($navId)
    {
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
        // We want correct URLs now
        $this->_parseEnvironment = true;
        // Get the params
        $this->_setParams($params);
        // Return the array structure
        return $this->getPagesByMenuId($menu->id);
    }

    /**
     * Set parameters for the navigation HTML output.
     *
     * @param array $params
     */
    private function _setParams($params)
    {
        foreach ($params as $paramKey => $paramValue) {
            $this->_params[$paramKey] = $paramValue;
        }
    }

    /**
     * Get parameter value.
     *
     * @param string $name
     * @param mixed $default
     *
     * @return mixed
     */
    private function _getParam($name, $default)
    {
        return isset($this->_params[$name]) ? $this->_params[$name] : $default;
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
                        $page['active'] = $this->_isPageActive($page['url']);
                        $page['url'] = craft()->config->parseEnvironmentString($page['url']);
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
        if ($level == 1) {
            $nav = sprintf("\n" . '<ul id="%1$s" class="%2$s">',
                $this->_getParam('id', $this->_menu->handle),
                $this->_getParam('class', 'nav')
            );
        } else {
            $nav = sprintf("\n" . '<ul class="%1$s">',
                $this->_getParam('classLevel' . $level, 'nav__level' . $level)
            );
        }

        // Add the pages to the navigation, but only if they are enabled
        foreach ($pages as $page) {
            if ($page['parentId'] == $parentId && ($page['enabled'] || $this->_getParam('overrideStatus', false))) {
                $foundPages = true;
                $nav .= sprintf("\n" . '<li%1$s><a href="%2$s"%3$s>%4$s</a>',
                    $this->_isPageActive($page['url']) ? ' class="' . $this->_getParam('classActive', 'active') . '"' : '',
                    craft()->config->parseEnvironmentString($page['url']),
                    $page['blank'] ? ' target="_blank"' : '',
                    $page['name']
                );

                // Get the child pages and add them to the navigation
                $children = $this->_buildNavHtml($pages, $page['id'], $level + 1);
                if ($children) {
                    $nav .= $children;
                }
                $nav .= '</li>';
            }
        }
        $nav .= "\n</ul>";
        if ($foundPages) {
            return TemplateHelper::getRaw($nav);
        }
        else {
            return false;
        }
    }

    /**
     * Check whether the URL is currently active.
     *
     * @param string $url
     *
     * @return bool
     */
    private function _isPageActive($url)
    {
        $path = craft()->request->getPath();
        $segments = craft()->request->getSegments();

        $url = str_replace('{siteUrl}', '', $url);
        if ($url == $path) {
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
                return true;
            }
        }
        return false;
    }
}