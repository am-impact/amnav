<?php
namespace Craft;

/**
 * Page service
 */
class AmNav_PageService extends BaseApplicationComponent
{
    private $_pages;

    /**
     * Get a menu by its ID.
     *
     * @param int $pageId
     *
     * @return AmNav_PageModel|null
     */
    public function getPageById($pageId)
    {
        $pageRecord = AmNav_PageRecord::model()->findById($pageId);
        if ($pageRecord) {
            return AmNav_PageModel::populateModel($pageRecord);
        }
        return null;
    }

    /**
     * Get all pages by a menu ID.
     *
     * @param int    $menuId
     * @param string $locale
     * @param bool   $reset
     *
     * @return array
     */
    public function getAllPagesByMenuId($menuId, $locale, $reset = false)
    {
        if (! $reset && isset($this->_pages[$menuId])) {
            return $this->_pages[$menuId];
        }
        $this->_pages[$menuId] = craft()->db->createCommand()
            ->select('*')
            ->from('amnav_pages')
            ->where(array('navId' => $menuId, 'locale' => $locale))
            ->order(array('parentId asc', 'order asc'))
            ->queryAll();
        return $this->_pages[$menuId];
    }

    /**
     * Saves a page.
     *
     * @param AmNav_PageModel $page
     *
     * @throws Exception
     * @return bool|AmNav_PageModel
     */
    public function savePage(AmNav_PageModel $page)
    {
        $isNewPage = !$page->id;

        // Page data
        if ($page->id) {
            $pageRecord = AmNav_PageRecord::model()->findById($page->id);

            if (! $pageRecord) {
                throw new Exception(Craft::t('No page exists with the ID “{id}”.', array('id' => $page->id)));
            }
        }
        else {
            $pageRecord = new AmNav_PageRecord();
        }

        // Set attributes
        $pageRecord->setAttributes($page->getAttributes());
        if ($isNewPage) {
            $pageRecord->order = $this->_getNewOrderNumber($page->navId, $page->parentId, $page->locale);
        }

        // Validate
        $pageRecord->validate();
        $page->addErrors($pageRecord->getErrors());

        // Save page
        if (! $page->hasErrors()) {
            if ($pageRecord->save()) {
                return AmNav_PageModel::populateModel($pageRecord);
            }
        }
        return false;
    }

    /**
     * Moves a page.
     *
     * @param AmNav_PageModel $page
     * @param int             $parentId New parent ID.
     * @param mixed           $prevId   The page ID of the page above the moved page.
     *
     * @return bool
     */
    public function movePage(AmNav_PageModel $page, $parentId = 0, $prevId = false)
    {
        $pageRecord = AmNav_PageRecord::model()->findById($page->id);
        if (! $pageRecord) {
            throw new Exception(Craft::t('No page exists with the ID “{id}”.', array('id' => $page->id)));
        }

        // Set new parent ID
        $pageRecord->parentId = $parentId;

        // Get new order
        $pages = $this->getAllPagesByMenuId($page->navId, $page->locale);
        $order = 0;
        // Should the moved page be the first?
        if ($prevId === false) {
            $pageRecord->order = $order;
            $order ++;
        }
        foreach ($pages as $page) {
            if ($page['parentId'] == $parentId) {
                // Is the moved page after this one?
                if ($prevId !== false && $prevId == $page['id']) {
                    // Update current
                    $this->_updatePageById($page['id'], array('order' => $order));
                    $order ++;

                    // Update moved
                    $pageRecord->order = $order;
                }
                else {
                    $this->_updatePageById($page['id'], array('order' => $order));
                }
                $order ++;
            }
        }

        // Save moved page!
        $result = $pageRecord->save();

        // Update the whole order of the structure
        $this->_updateOrderForMenuId($pageRecord->navId, $pageRecord->locale);

        return $result;
    }

    /**
     * Delete a page by its ID.
     *
     * @param int $pageId
     *
     * @return bool
     */
    public function deletePageById($pageId)
    {
        $page = $this->getPageById($pageId);
        if ($page) {
            $pages = $this->getAllPagesByMenuId($page->navId, $page->locale);

            // Get children IDs
            $pageIds = $this->_getChildrenIds($pages, $page->id);

            // Add this page ID
            $pageIds[] = $page->id;

            // Delete all!
            $result = craft()->db->createCommand()->delete('amnav_pages', array('in', 'id', $pageIds));
            if ($result) {
                // Update pages order
                $this->_updateOrderForMenuId($page->navId, $page->locale);
            }
            return $result;
        }
        return false;
    }

    /**
     * Update pages in a navigation based on the Entry that was just saved.
     *
     * @param EntryModel $entry
     * @param bool       $beforeEntryEvent
     * @param bool       $skipUpdatingDescendants
     */
    public function updatePagesForEntry(EntryModel $entry, $beforeEntryEvent = false, $skipUpdatingDescendants = false)
    {
        $pageRecords = AmNav_PageRecord::model()->findAllByAttributes(array(
            'entryId' => $entry->id,
            'locale'  => $entry->locale
        ));
        if (count($pageRecords)) {
            // Get Entry model with data before it's being saved
            $beforeSaveEntry = $beforeEntryEvent ? craft()->entries->getEntryById($entry->id, $entry->locale) : false;

            if (! $skipUpdatingDescendants) {
                $updatedDescendants = false;
            }

            // Update page records
            foreach ($pageRecords as $pageRecord) {
                // Set update data
                $newUrl = '{siteUrl}' . str_ireplace('__home__', '', $entry->uri);
                $updateData = array(
                    'url' => $newUrl,
                    'enabled' => $entry->enabled,
                );

                // Only update the page name if they were the same before the Entry was saved
                if ($beforeSaveEntry && $beforeSaveEntry->title == $pageRecord->name) {
                    $updateData['name'] = $entry->title;
                }

                // Save this page!
                $this->_updatePageById($pageRecord->id, $updateData);

                // Update URLs from possible entry descendant pages
                if (! $skipUpdatingDescendants && ! $updatedDescendants && $newUrl != $pageRecord->url) {
                    $updatedDescendants = true;
                    $this->_updateDescendantsForEntry($entry);
                }
            }
        }
    }

    /**
     * Delete pages in a navigation based on the Entry that was just deleted.
     *
     * @param EntryModel $entry
     */
    public function deletePagesForEntry(EntryModel $entry)
    {
        $pageRecords = AmNav_PageRecord::model()->findAllByAttributes(array(
            'entryId' => $entry->id,
            'locale'  => $entry->locale
        ));
        foreach ($pageRecords as $pageRecord) {
            $this->deletePageById($pageRecord->id);
        }
    }

    /**
     * Update entry descendants' pages.
     *
     * @param EntryModel $entry
     */
    private function _updateDescendantsForEntry(EntryModel $entry)
    {
        $criteria = craft()->elements->getCriteria(ElementType::Entry);
        $criteria->descendantOf = $entry;
        $criteria->locale = $entry->locale;
        $descendants = $criteria->find();

        if (count($descendants)) {
            foreach ($descendants as $descendant) {
                $this->updatePagesForEntry($descendant, false, true);
            }
        }
    }

    /**
     * Get all children IDs for a page.
     *
     * @param array $pages
     * @param int   $parentId
     *
     * @return array
     */
    private function _getChildrenIds($pages, $parentId)
    {
        $ids = array();
        foreach ($pages as $page) {
            if ($page['parentId'] == $parentId) {
                $childrenIds = $this->_getChildrenIds($pages, $page['id']);
                if ($childrenIds) {
                    $ids = array_merge($ids, $childrenIds);
                }
                $ids[] = $page['id'];
            }
        }
        return $ids;
    }

    /**
     * Get an order number for a new page.
     *
     * @param int    $navId
     * @param int    $parentId
     * @param string $locale
     *
     * @return int
     */
    private function _getNewOrderNumber($navId, $parentId, $locale)
    {
        $attributes = array(
            'navId'    => $navId,
            'parentId' => $parentId,
            'locale'   => $locale
        );
        $latestOrderNumber = craft()->db->createCommand()
            ->select('order')
            ->from('amnav_pages')
            ->where($attributes)
            ->order('order desc')
            ->limit(1)
            ->queryRow();
        if ($latestOrderNumber) {
            return (int)$latestOrderNumber['order'] + 1;
        }
        return 0;
    }

    /**
     * Update the order of every page.
     *
     * @param int    $menuId
     * @param string $locale
     * @param bool   $pages
     * @param int    $parentId
     */
    private function _updateOrderForMenuId($menuId, $locale, $pages = false, $parentId = 0)
    {
        // Get pages for first run
        if ($pages === false) {
            $pages = $this->getAllPagesByMenuId($menuId, $locale, true);
        }

        // Update order
        $order = 0;
        foreach ($pages as $page) {
            if ($page['parentId'] == $parentId) {
                // Update current page's order
                $this->_updatePageById($page['id'], array('order' => $order));
                $order ++;

                // Update order for child pages
                $this->_updateOrderForMenuId($menuId, $locale, $pages, $page['id']);
            }
        }
    }

    /**
     * Update page information in the database.
     *
     * @param int   $pageId
     * @param array $data
     *
     * @return bool
     */
    private function _updatePageById($pageId, $data)
    {
        return craft()->db->createCommand()->update('amnav_pages', $data, array('id' => $pageId));
    }
}