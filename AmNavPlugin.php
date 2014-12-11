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
        return '1.1.8';
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
            'amnav'                       => array('action' => 'amNav/navIndex'),
            'amnav/new'                   => array('action' => 'amNav/editMenu'),
            'amnav/edit/(?P<menuId>\d+)'  => array('action' => 'amNav/editMenu'),
            'amnav/build/(?P<menuId>\d+)' => array('action' => 'amNav/buildMenu')
        );
    }

    /**
     * Load a&m nav.
     */
    public function init()
    {
        // Update pages in a navigation if an Entry was saved
        craft()->on('entries.beforeSaveEntry', function(Event $event) {
            if (! $event->params['isNewEntry']) {
                craft()->amNav_page->updatePagesForEntry($event->params['entry'], true);
            }
        });
        // Update pages again, since the URI update is only available after the Entry has been saved
        craft()->on('entries.saveEntry', function(Event $event) {
            if (! $event->params['isNewEntry']) {
                craft()->amNav_page->updatePagesForEntry($event->params['entry']);
            }
        });
        // Delete pages from a navigation if an Entry was deleted
        craft()->on('entries.deleteEntry', function(Event $event) {
            craft()->amNav_page->deletePagesForEntry($event->params['entry']);
        });
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