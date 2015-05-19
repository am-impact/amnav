<?php
namespace Craft;

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
        if (is_string($value) && ($fixedValue = json_decode($value, true)) !== null) {
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
                elseif ($currentNode->locale != $this->element->locale) {
                    // When an entry is activated for a different locale, it contains the data
                    // from the locale entry that was created from an earlier moment.
                    // We don't want the fieldtype to use this data though, so unset it.
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

        // Load resources
        $js = sprintf(
            'new Craft.NavigationPosition("%s", %s);',
            $id,
            json_encode($navigation['settings'])
        );
        craft()->templates->includeJs($js);
        craft()->templates->includeJsResource('amnav/js/NavigationPosition.min.js');
        craft()->templates->includeCssResource('amnav/css/NavigationPosition.css');
        craft()->templates->includeTranslations('Position here', 'Add to navigation');

        return craft()->templates->render('amNav/navigationposition/input', array(
            'id' => $id,
            'name' => $name,
            'value' => $value,
            'navigation' => $navigation,
            'nodes' => $nodes
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
            // Only if the user chose a position!
            if (isset($fieldData['positionChosen']) && $fieldData['positionChosen'] && isset($fieldData['parentId']) && isset($fieldData['prevId'])) {
                // Update existing node
                if (isset($fieldData['nodeId']) && ! empty($fieldData['nodeId'])) {
                    $this->_updateNode($content, $fieldData);
                }
                else {
                    $this->_createNode($content, $settings['navId'], $fieldData);
                }
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
     * Create a new navigation node.
     *
     * @param ContentModel $content
     * @param int          $navId
     * @param array        $fieldData
     */
    private function _createNode($content, $navId, $fieldData)
    {
        $parentId = (int)$fieldData['parentId'];
        $prevId = (int)$fieldData['prevId'];

        // Prepare node model
        $node = new AmNav_NodeModel();
        $node->navId = $navId;
        $node->elementId = $this->element->id;
        $node->elementType = $this->element->elementType;
        $node->name = $content->title;
        $node->blank = false;
        $node->enabled = true;
        $node->locale = $this->element->locale;
        $node->parentId = $parentId;

        // Save the node!
        if (($savedNode = craft()->amNav_node->saveNode($node)) !== false) {
            // Add saved node to the content
            $fieldData['nodeId'] = $savedNode->id;
            $content->{$this->model->handle} = $fieldData;
            $this->element->setContent($content);

            // Save content
            craft()->content->saveContent($this->element);

            // Do we have to move the page?
            if ($prevId >= 0) {
                // Unset prevId if it's a 'before' position type
                if ($prevId === 0) {
                    $prevId = false;
                }
                craft()->amNav_node->moveNode($savedNode, $parentId, $prevId);
            }
        }
    }

    /**
     * Update an existing navigation node.
     *
     * @param ContentModel $content
     * @param array        $fieldData
     */
    private function _updateNode($content, $fieldData)
    {
        // Get node
        $node = craft()->amNav_node->getNodeById($fieldData['nodeId']);
        if (! $node) {
            return false;
        }

        // Set information
        $parentId = (int)$fieldData['parentId'];
        $prevId = (int)$fieldData['prevId'];
        // UnsetprevId if it's a 'before' or 'under' position type
        if ($prevId === -1 || $prevId === 0) {
            $prevId = false;
        }

        // Move the node!
        craft()->amNav_node->moveNode($node, $parentId, $prevId);
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