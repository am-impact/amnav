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
         return Craft::t('a&m nav');
    }

    public function getVersion()
    {
        return '1.0';
    }

    public function getDeveloper()
    {
        return 'a&m impact';
    }

    public function getDeveloperUrl()
    {
        return 'http://www.am-impact.nl';
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
}