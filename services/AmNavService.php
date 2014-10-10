<?php
namespace Craft;

/**
 * Navigation service
 */
class AmNavService extends BaseApplicationComponent
{
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
     * Get all pages by its menu ID.
     *
     * @param int $navId
     *
     * @return array
     */
    public function getPagesByMenuId($navId)
    {
        $pages = craft()->amNav_page->getAllPagesByMenuId($navId);
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
                $children = $this->_buildNav($pages, $page['id'], $level + 1);
                if ($children) {
                    $page['children'] = $children;
                }
                $nav[] = $page;
            }
        }
        return $nav;
    }
}