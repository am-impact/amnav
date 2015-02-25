<?php
namespace Craft;

// TODO: Rekening houden met instellingen (maxLevels etc)
// TODO: Alleen fieldType bij entries laten werken

class AmNav_NavigationPositionFieldType extends BaseFieldType
{
    public function getName()
    {
        return Craft::t('Position in navigation');
    }

    /**
     * Get FieldType's input HTML.
     *
     * @param string $name
     * @param mixed  $value
     *
     * @return string
     */
    public function getInputHtml($name, $value)
    {
        $currentNodeId = false;
        // Fix value if saved in the database
        if (($fixedValue = json_decode($value, true)) !== null) {
            $value = $fixedValue; // Override value
            if (isset($value['nodeId'])) {
                $currentNodeId = $value['nodeId'];

                // Check whether this node still exists
                $currentNode = craft()->amNav_node->getNodeById($currentNodeId);
                if (! $currentNode) {
                    // Don't save the nodeId for next save moment
                    $currentNodeId = false;
                    unset($value['nodeId']);
                }
            }
        }

        // Reformat the input name into something that looks more like an ID
        $id = craft()->templates->formatInputId($name);

        // Get the FieldType's settings
        $settings = $this->getSettings();

        // Get the navigation information
        $navigation = craft()->amNav->getNavigationById($settings['navId']);
        if (! $navigation) {
            throw new Exception(Craft::t('No navigation exists with the ID “{id}”.', array('id' => $settings['navId'])));
        }

        // Get the nodes added in this navigation
        $nodes = craft()->amNav->getNodesByNavigationId($settings['navId'], $this->element->locale);

        // Action options
        $actionOptions = array(
            'after'  => Craft::t('{action} the selected', array('action' => Craft::t('After'))),
            'before' => Craft::t('{action} the selected', array('action' => Craft::t('Before'))),
            'child'  => Craft::t('{action} the selected', array('action' => Craft::t('Under')))
        );
        if ($currentNodeId !== false) {
            $actionOptions = array_merge(array('keep' => Craft::t('Keep position')), $actionOptions);
        }

        return craft()->templates->render('amNav/navigationposition/input', array(
            'id' => $id,
            'name' => $name,
            'value' => $value,
            'navigation' => $navigation,
            'noNodes' => ! count($nodes),
            'positionOptions' => $this->_getPositionOptions($nodes, $currentNodeId, $value),
            'actionOptions' => $actionOptions
        ));
    }

    /**
     * After the element has been saved succesfully, update the navigation.
     */
    public function onAfterElementSave()
    {
        // Get the Element's content
        $content = $this->element->getContent();

        // Saved field data
        $fieldData = $content->{$this->model->handle};

        // Get the FieldType's settings
        $settings = $this->getSettings();

        // Create or update existing node
        if (isset($fieldData['determinePosition']) && ! empty($fieldData['determinePosition'])) {
            // Update existing node
            if (isset($fieldData['nodeId']) && ! empty($fieldData['nodeId'])) {
                $this->_updateNode($fieldData['nodeId'], $content, $fieldData, $settings);
            }
            // Create a new node
            elseif (isset($fieldData['position']) && isset($fieldData['action'])) {
                $this->_createNode($content, $fieldData, $settings);
            }
        }
        // Delete existing node
        elseif (isset($fieldData['nodeId']) && ! empty($fieldData['nodeId'])) {
            $this->_deleteNode($fieldData['nodeId'], $content);
        }
    }

    /**
     * Get FieldType's settings HTML.
     *
     * @return string
     */
    public function getSettingsHtml()
    {
        return craft()->templates->render('amNav/navigationposition/settings', array(
            'settings' => $this->getSettings(),
            'navigations' => craft()->amNav->getNavigations('id')
        ));
    }

    /**
     * FieldType settings.
     *
     * @return array
     */
    protected function defineSettings()
    {
        return array(
            'navId' => array(AttributeType::Number)
        );
    }

    /**
     * Get position options for given nodes.
     *
     * @param array $nodes
     * @param mixed $currentNodeId
     * @param bool  $skipFirst
     *
     * @return array
     */
    private function _getPositionOptions($nodes, $currentNodeId, $skipFirst = false)
    {
        $positionOptions = array();
        if (! $skipFirst && ! count($nodes)) {
            $positionOptions[] = array(
                'label' => Craft::t('Add to navigation'),
                'value' => 'add'
            );
        }
        foreach ($nodes as $node) {
            $label = '';
            for ($i = 1; $i < $node['level']; $i++) {
                $label .= '    ';
            }
            $label .= $node['name'];

            $positionOptions[] = array(
                'label'    => $label,
                'value'    => $node['id'],
                'disabled' => $currentNodeId !== false && $currentNodeId == $node['id'] ? true : false
            );
            if (isset($node['children'])) {
                foreach($this->_getPositionOptions($node['children'], $currentNodeId, true) as $childNode) {
                    $positionOptions[] = $childNode;
                }
            }
        }
        return $positionOptions;
    }

    /**
     * Get the previous ID based on a given ID.
     *
     * @param array $nodes
     * @param int   $parentId
     * @param int   $id
     *
     * @return bool|int
     */
    private function _getPrevNodeId($nodes, $parentId, $id)
    {
        foreach ($nodes as $key => $node) {
            if ($parentId == $node['parentId']) {
                if ($node['id'] == $id) {
                    if (isset($nodes[$key - 1])) {
                        return $nodes[$key - 1]['id'];
                    }
                    else {
                        return false;
                    }
                }
            }
            if (isset($node['children'])) {
                if (($foundId = $this->_getPrevNodeId($node['children'], $parentId, $id)) !== false) {
                    return $foundId;
                }
            }
        }
        return false;
    }

    /**
     * Create a new navigation node.
     *
     * @param ContentModel $content
     * @param array        $fieldData
     * @param array        $settings
     */
    private function _createNode($content, $fieldData, $settings)
    {
        $movePage = false;

        // Prepare node model
        $node = new AmNav_NodeModel();
        $node->navId = $settings['navId'];
        $node->entryId = $this->element->id;
        $node->name = $content->title;
        $node->url = '{siteUrl}' . $this->element->uri;
        $node->blank = false;
        $node->enabled = true;
        $node->locale = $this->element->locale;

        // Add to root?
        if ($fieldData['position'] == 'add') {
            $node->parentId = 0;
        }
        // Add to navigation node?
        elseif (is_numeric($fieldData['position'])) {
            switch ($fieldData['action']) {
                case 'after':
                    // Add it to the root first
                    $node->parentId = 0;

                    // We will move this page when it's saved
                    $movePage = true;

                    // Find parentId and prevId
                    $selectedNode = craft()->amNav_node->getNodeById($fieldData['position']);
                    $parentId     = $selectedNode ? $selectedNode['parentId'] : 0;
                    $prevId       = $fieldData['position']; // The current selected node
                    break;

                case 'before':
                    // Add it to the root first
                    $node->parentId = 0;

                    // We will move this page when it's saved
                    $movePage = true;

                    // Find parentId and prevId
                    $nodes        = craft()->amNav->getNodesByNavigationId($settings['navId'], $this->element->locale);
                    $selectedNode = craft()->amNav_node->getNodeById($fieldData['position']);
                    $parentId     = $selectedNode ? $selectedNode['parentId'] : 0;
                    $prevId       = $this->_getPrevNodeId($nodes, $parentId, $fieldData['position']);
                    break;

                default:
                    $node->parentId = $fieldData['position'];
                    break;
            }
        }
        // Ignore other positions
        else {
            return false;
        }

        // Save the node!
        if (($savedNode = craft()->amNav_node->saveNode($node)) !== false) {
            // Add saved node to the content
            $fieldData['nodeId'] = $savedNode->id;
            $content->{$this->model->handle} = $fieldData;
            $this->element->setContent($content);

            // Save content
            craft()->content->saveContent($this->element);

            // Do we have to move the page?
            if ($movePage !== false) {
                craft()->amNav_node->moveNode($savedNode, $parentId, $prevId);
            }
        }
    }

    /**
     * Update an existing navigation node.
     *
     * @param int          $nodeId
     * @param ContentModel $content
     * @param array        $fieldData
     * @param array        $settings
     */
    private function _updateNode($nodeId, $content, $fieldData, $settings)
    {
        // Do we keep the node in place?
        if ($fieldData['action'] == 'keep') {
            return false;
        }

        // Get node
        $node = craft()->amNav_node->getNodeById($nodeId);
        if (! $node) {
            return false;
        }

        // Move node?
        if (is_numeric($fieldData['position'])) {
            switch ($fieldData['action']) {
                case 'after':
                    // Find parentId and prevId
                    $selectedNode = craft()->amNav_node->getNodeById($fieldData['position']);
                    $parentId     = $selectedNode ? $selectedNode['parentId'] : 0;
                    $prevId       = $fieldData['position']; // The current selected node
                    break;

                case 'before':
                    // Find parentId and prevId
                    $nodes        = craft()->amNav->getNodesByNavigationId($settings['navId'], $this->element->locale);
                    $selectedNode = craft()->amNav_node->getNodeById($fieldData['position']);
                    $parentId     = $selectedNode ? $selectedNode['parentId'] : 0;
                    $prevId       = $this->_getPrevNodeId($nodes, $parentId, $fieldData['position']);
                    break;

                default:
                    $parentId = $fieldData['position'];
                    $prevId   = false;
                    break;
            }
            craft()->amNav_node->moveNode($node, $parentId, $prevId);
        }
    }

    /**
     * Delete a navigation node.
     *
     * @param int          $nodeId
     * @param ContentModel $content
     */
    private function _deleteNode($nodeId, $content)
    {
        if (craft()->amNav_node->deleteNodeById($nodeId)) {
            // Reset content
            $content->{$this->model->handle} = null;
            $this->element->setContent($content);

            // Save content
            craft()->content->saveContent($this->element);
        }
    }
}