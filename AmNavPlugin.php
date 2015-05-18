<?php
/**
 * Navigation for Craft.
 *
 * @package   Am Nav
 * @author    Hubert Prein
 */
namespace Craft;

class AmNavPlugin extends BasePlugin
{
    public function getName()
    {
        $settings = $this->getSettings();
        if ($settings->pluginName) {
            return $settings->pluginName;
        }
        return Craft::t('a&m nav');
    }

    public function getVersion()
    {
        return '1.6.0';
    }

    public function getDeveloper()
    {
        return 'a&m impact';
    }

    public function getDeveloperUrl()
    {
        return 'http://www.am-impact.nl';
    }

    public function getSettingsHtml()
    {
        return craft()->templates->render('amnav/settings', array(
            'settings' => $this->getSettings()
        ));
    }

    /**
     * Plugin has control panel section.
     *
     * @return boolean
     */
    public function hasCpSection()
    {
        return true;
    }

    /**
     * Plugin has Control Panel routes.
     *
     * @return array
     */
    public function registerCpRoutes()
    {
        return array(
            'amnav' => array(
                'action' => 'amNav/navIndex'
            ),
            'amnav/new' => array(
                'action' => 'amNav/editNavigation'
            ),
            'amnav/edit/(?P<navId>\d+)' => array(
                'action' => 'amNav/editNavigation'
            ),
            'amnav/build/(?P<navId>\d+)' => array(
                'action' => 'amNav/buildNavigation'
            ),
            'amnav/build/(?P<navId>\d+)/(?P<locale>{handle})' => array(
                'action' => 'amNav/buildNavigation'
            )
        );
    }

    /**
     * Load a&m nav.
     */
    public function init()
    {
        if (! craft()->isConsole())
        {
            // Update nodes in a navigation if an Entry was saved
            craft()->on('entries.beforeSaveEntry', function(Event $event) {
                if (! $event->params['isNewEntry']) {
                    craft()->amNav_node->updateNodesForElement($event->params['entry'], ElementType::Entry);
                }
            });
            // Delete nodes from a navigation if an Entry was deleted
            craft()->on('entries.deleteEntry', function(Event $event) {
                craft()->amNav_node->deleteNodesForElement($event->params['entry'], ElementType::Entry);
            });

            // Update nodes in a navigation if an Category was saved
            craft()->on('categories.beforeSaveCategory', function(Event $event) {
                if (! $event->params['isNewCategory']) {
                    craft()->amNav_node->updateNodesForElement($event->params['category'], ElementType::Category);
                }
            });
            // Delete nodes from a navigation if an Category was deleted
            craft()->on('categories.deleteCategory', function(Event $event) {
                craft()->amNav_node->deleteNodesForElement($event->params['category'], ElementType::Category);
            });
        }
    }

    /**
     * Add commands to a&m command through this hook function.
     *
     * @return array
     */
    public function addCommands() {
        $pluginName = $this->getName();
        $commands = array(
            array(
                'name'    => $pluginName . ': ' . Craft::t('New navigation'),
                'url'     => UrlHelper::getUrl('amnav/new')
            ),
            array(
                'name'    => $pluginName . ': ' . Craft::t('Build navigation'),
                'more'    => true,
                'call'    => 'getNavigationsByCommand',
                'service' => 'amNav',
                'vars'    => array(
                    'command' => 'build'
                )
            ),
            array(
                'name'    => $pluginName . ': ' . Craft::t('Navigation settings'),
                'more'    => true,
                'call'    => 'getNavigationsByCommand',
                'service' => 'amNav',
                'vars'    => array(
                    'command' => 'settings'
                )
            )
        );
        return $commands;
    }

    /**
     * Plugin settings.
     *
     * @return array
     */
    protected function defineSettings()
    {
        return array(
            'pluginName'   => array(AttributeType::String),
            'canDoActions' => array(AttributeType::Bool, 'default' => false)
        );
    }
}