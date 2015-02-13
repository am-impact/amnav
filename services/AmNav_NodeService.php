<?php
namespace Craft;

/**
 * Node service
 */
class AmNav_NodeService extends BaseApplicationComponent
{
    private $_nodes;

    /**
     * Get a navigation by its ID.
     *
     * @param int $nodeId
     *
     * @return AmNav_NodeModel|null
     */
    public function getNodeById($nodeId)
    {
        $nodeRecord = AmNav_NodeRecord::model()->findById($nodeId);
        if ($nodeRecord) {
            return AmNav_NodeModel::populateModel($nodeRecord);
        }
        return null;
    }

    /**
     * Get all nodes by a navigation ID.
     *
     * @param int    $navId
     * @param string $locale
     * @param bool   $reset
     *
     * @return array
     */
    public function getAllNodesByNavigationId($navId, $locale, $reset = false)
    {
        if (! $reset && isset($this->_nodes[$navId])) {
            return $this->_nodes[$navId];
        }
        $this->_nodes[$navId] = craft()->db->createCommand()
            ->select('*')
            ->from('amnav_nodes')
            ->where(array('navId' => $navId, 'locale' => $locale))
            ->order(array('parentId asc', 'order asc'))
            ->queryAll();
        return $this->_nodes[$navId];
    }

    /**
     * Saves a node.
     *
     * @param AmNav_NodeModel $node
     *
     * @throws Exception
     * @return bool|AmNav_NodeModel
     */
    public function saveNode(AmNav_NodeModel $node)
    {
        $isNewNode = !$node->id;

        // Node data
        if ($node->id) {
            $nodeRecord = AmNav_NodeRecord::model()->findById($node->id);

            if (! $nodeRecord) {
                throw new Exception(Craft::t('No node exists with the ID “{id}”.', array('id' => $node->id)));
            }
        }
        else {
            $nodeRecord = new AmNav_NodeRecord();
        }

        // Set attributes
        $nodeRecord->setAttributes($node->getAttributes());
        if ($isNewNode) {
            $nodeRecord->order = $this->_getNewOrderNumber($node->navId, $node->parentId, $node->locale);
        }

        // Validate
        $nodeRecord->validate();
        $node->addErrors($nodeRecord->getErrors());

        // Save node
        if (! $node->hasErrors()) {
            if ($nodeRecord->save()) {
                return AmNav_NodeModel::populateModel($nodeRecord);
            }
        }
        return false;
    }

    /**
     * Moves a node.
     *
     * @param AmNav_NodeModel $node
     * @param int             $parentId New parent ID.
     * @param mixed           $prevId   The node ID of the node above the moved node.
     *
     * @return bool
     */
    public function moveNode(AmNav_NodeModel $node, $parentId = 0, $prevId = false)
    {
        $nodeRecord = AmNav_NodeRecord::model()->findById($node->id);
        if (! $nodeRecord) {
            throw new Exception(Craft::t('No node exists with the ID “{id}”.', array('id' => $node->id)));
        }

        // Set new parent ID
        $nodeRecord->parentId = $parentId;

        // Get new order
        $nodes = $this->getAllNodesByNavigationId($node->navId, $node->locale);
        $order = 0;
        // Should the moved node be the first?
        if ($prevId === false) {
            $nodeRecord->order = $order;
            $order ++;
        }
        foreach ($nodes as $node) {
            if ($node['parentId'] == $parentId) {
                // Is the moved node after this one?
                if ($prevId !== false && $prevId == $node['id']) {
                    // Update current
                    $this->_updateNodeById($node['id'], array('order' => $order));
                    $order ++;

                    // Update moved
                    $nodeRecord->order = $order;
                }
                else {
                    $this->_updateNodeById($node['id'], array('order' => $order));
                }
                $order ++;
            }
        }

        // Save moved node!
        $result = $nodeRecord->save();

        // Update the whole order of the structure
        $this->_updateOrderForNavigationId($nodeRecord->navId, $nodeRecord->locale);

        return $result;
    }

    /**
     * Delete a node by its ID.
     *
     * @param int $nodeId
     *
     * @return bool
     */
    public function deleteNodeById($nodeId)
    {
        $node = $this->getNodeById($nodeId);
        if ($node) {
            $nodes = $this->getAllNodesByNavigationId($node->navId, $node->locale);

            // Get children IDs
            $nodeIds = $this->_getChildrenIds($nodes, $node->id);

            // Add this node ID
            $nodeIds[] = $node->id;

            // Delete all!
            $result = craft()->db->createCommand()->delete('amnav_nodes', array('in', 'id', $nodeIds));
            if ($result) {
                // Update nodes order
                $this->_updateOrderForNavigationId($node->navId, $node->locale);
            }
            return $result;
        }
        return false;
    }

    /**
     * Update nodes in a navigation based on the Entry that was just saved.
     *
     * @param EntryModel $entry
     * @param bool       $beforeEntryEvent
     * @param bool       $skipUpdatingDescendants
     */
    public function updateNodesForEntry(EntryModel $entry, $beforeEntryEvent = false, $skipUpdatingDescendants = false)
    {
        $nodeRecords = AmNav_NodeRecord::model()->findAllByAttributes(array(
            'entryId' => $entry->id,
            'locale'  => $entry->locale
        ));
        if (count($nodeRecords)) {
            // Get Entry model with data before it's being saved
            $beforeSaveEntry = $beforeEntryEvent ? craft()->entries->getEntryById($entry->id, $entry->locale) : false;

            if (! $skipUpdatingDescendants) {
                $updatedDescendants = false;
            }

            // Update node records
            foreach ($nodeRecords as $nodeRecord) {
                // Set update data
                $newUrl = '{siteUrl}' . str_ireplace('__home__', '', $entry->uri);
                $updateData = array(
                    'url' => $newUrl,
                    'enabled' => $entry->enabled,
                );

                // Only update the node name if they were the same before the Entry was saved
                if ($beforeSaveEntry && $beforeSaveEntry->title == $nodeRecord->name) {
                    $updateData['name'] = $entry->title;
                }

                // Save this node!
                $this->_updateNodeById($nodeRecord->id, $updateData);

                // Update URLs from possible entry descendant nodes
                if (! $skipUpdatingDescendants && ! $updatedDescendants && $newUrl != $nodeRecord->url) {
                    $updatedDescendants = true;
                    $this->_updateDescendantsForEntry($entry);
                }
            }
        }
    }

    /**
     * Delete nodes in a navigation based on the Entry that was just deleted.
     *
     * @param EntryModel $entry
     */
    public function deleteNodesForEntry(EntryModel $entry)
    {
        $nodeRecords = AmNav_NodeRecord::model()->findAllByAttributes(array(
            'entryId' => $entry->id,
            'locale'  => $entry->locale
        ));
        foreach ($nodeRecords as $nodeRecord) {
            $this->deleteNodeById($nodeRecord->id);
        }
    }

    /**
     * Update entry descendants' nodes.
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
                $this->updateNodesForEntry($descendant, false, true);
            }
        }
    }

    /**
     * Get all children IDs for a node.
     *
     * @param array $nodes
     * @param int   $parentId
     *
     * @return array
     */
    private function _getChildrenIds($nodes, $parentId)
    {
        $ids = array();
        foreach ($nodes as $node) {
            if ($node['parentId'] == $parentId) {
                $childrenIds = $this->_getChildrenIds($nodes, $node['id']);
                if ($childrenIds) {
                    $ids = array_merge($ids, $childrenIds);
                }
                $ids[] = $node['id'];
            }
        }
        return $ids;
    }

    /**
     * Get an order number for a new node.
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
            ->from('amnav_nodes')
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
     * Update the order of every node.
     *
     * @param int    $navId
     * @param string $locale
     * @param bool   $nodes
     * @param int    $parentId
     */
    private function _updateOrderForNavigationId($navId, $locale, $nodes = false, $parentId = 0)
    {
        // Get nodes for first run
        if ($nodes === false) {
            $nodes = $this->getAllNodesByNavigationId($navId, $locale, true);
        }

        // Update order
        $order = 0;
        foreach ($nodes as $node) {
            if ($node['parentId'] == $parentId) {
                // Update current node's order
                $this->_updateNodeById($node['id'], array('order' => $order));
                $order ++;

                // Update order for child nodes
                $this->_updateOrderForNavigationId($navId, $locale, $nodes, $node['id']);
            }
        }
    }

    /**
     * Update node information in the database.
     *
     * @param int   $nodeId
     * @param array $data
     *
     * @return bool
     */
    private function _updateNodeById($nodeId, $data)
    {
        return craft()->db->createCommand()->update('amnav_nodes', $data, array('id' => $nodeId));
    }
}