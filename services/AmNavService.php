<?php
namespace Craft;

/**
 * Navigation service
 */
class AmNavService extends BaseApplicationComponent
{
    private $_menu;
    private $_parseHtml = false;
    private $_parseParams = array();
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
        $pages = craft()->amNav_page->getAllPagesByMenuId($navId);
        if ($this->_parseHtml) {
            return $this->_buildNavHtml($pages);
        }
        return $this->_buildNav($pages);
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
        $this->_parseParams = $params;
        // We want HTML returned
        $this->_parseHtml = true;
        // Return build HTML
        return $this->getPagesByMenuId($menu->id);
    }

    /**
     * Get a navigation structure without any HTML.
     *
     * @param string $handle
     *
     * @throws Exception
     * @return array
     */
    public function getNavRaw($handle)
    {
        $menu = $this->getMenuByHandle($handle);
        if (! $menu) {
            throw new Exception(Craft::t('No menu exists with the handle “{handle}”.', array('handle' => $handle)));
        }
        // We want correct URLs now
        $this->_parseEnvironment = true;
        // Return the array structure
        return $this->getPagesByMenuId($menu->id);
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
        $nav = array();
        foreach ($pages as $page) {
            if ($page['parentId'] == $parentId) {
                $page['level'] = $level;
                if ($this->_parseEnvironment) {
                    $page['url'] = craft()->config->parseEnvironmentString($page['url']);
                }
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
    private function _buildNavHtml($pages, $parentId = 0, $level = 0)
    {
        // We only create an ID on the first UL
        $nav = sprintf("\n" . '<ul%1$s class="%2$s">',
            $level == 0 ? (isset($this->_parseParams['id']) ? ' id="' . $this->_parseParams['id'] .'"' : ' id="' . $this->_menu->handle .'"') : '',
            isset($this->_parseParams['classLevel' . $level]) ? $this->_parseParams['classLevel' . $level] : 'nav__level' . $level
        );

        $classActive = isset($this->_parseParams['classActive']) ? $this->_parseParams['classActive'] : 'active';

        // Add the pages to the navigation, but only if they are enabled
        foreach ($pages as $page) {
            if ($page['parentId'] == $parentId && $page['enabled']) {
                $nav .= sprintf("\n" . '<li%1$s><a href="%2$s"%3$s>%4$s</a>',
                    $this->_isPageActive($page['url']) ? ' class="' . $classActive . '"' : '',
                    craft()->config->parseEnvironmentString($page['url']),
                    $page['blank'] ? ' target="_blank"' : '',
                    $page['name']
                );

                // Get the child pages and add them to the navigation
                $children = $this->_buildNav($pages, $page['id'], $level + 1);
                if ($children) {
                    $nav .= $children;
                }
                $nav .= '</li>';
            }
        }
        $nav .= "\n</ul>";
        return TemplateHelper::getRaw($nav);
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
            }
            if ($found) {
                return true;
            }
        }
        return false;
    }
}