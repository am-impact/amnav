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
     * Saves a menu.
     *
     * @param AmNav_MenuModel $menu
     *
     * @return bool
     */
    public function saveMenu(AmNav_MenuModel $menu)
    {
        // Menu data
        if ($menu->id) {
            $menuRecord = AmNav_MenuRecord::model()->findById($menu->id);

            if (! $menuRecord) {
                throw new Exception(Craft::t('No event exists with the ID “{id}”', array('id' => $event->id)));
            }
        }
        else {
            $menuRecord = new AmNav_MenuRecord();
            $menuRecord->name = $menu->name;
            $menuRecord->handle = $menu->handle;
        }

        // Set attributes
        $menuRecord->name = $menu->name;
        $menuRecord->handle = $menu->handle;

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
}