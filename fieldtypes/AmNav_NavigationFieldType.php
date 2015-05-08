<?php
namespace Craft;

class AmNav_NavigationFieldType extends BaseFieldType
{
    public function getName()
    {
        return Craft::t('Display navigation');
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
        // Reformat the input name into something that looks more like an ID
        $id = craft()->templates->formatInputId($name);

        return craft()->templates->render('amNav/navigation/input', array(
            'id' => $id,
            'value' => $value,
            'name'  => $name,
            'navigations' => craft()->amNav->getNavigations('handle')
        ));
    }
}