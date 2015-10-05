<?php
namespace Craft;

/**
 * Navigation controller
 */
class AmNavController extends BaseController
{
    /**
     * Navigation index
     */
    public function actionNavIndex()
    {
        $plugin = craft()->plugins->getPlugin('amnav');

        $variables['navigations'] = craft()->amNav->getNavigations();
        $variables['settings'] = $plugin->getSettings();

        $this->renderTemplate('amNav/_index', $variables);
    }

    /**
     * Create or edit a navigation.
     *
     * @param array $variables
     */
    public function actionEditNavigation(array $variables = array())
    {
        // Get navigation if available
        if (! empty($variables['navId'])) {
            $variables['navigation'] = craft()->amNav->getNavigationById($variables['navId']);

            if (! $variables['navigation']) {
                throw new HttpException(404);
            }
        }
        else {
            $variables['navigation'] = new AmNav_NavigationModel();
        }

        // Get element type
        $entryElementType = $this->_getElementTypeInfo(ElementType::Entry);
        $variables['entryType'] = $entryElementType['type'];
        $variables['entrySources'] = $entryElementType['sources'];

        // Render the template
        $this->renderTemplate('amNav/_edit', $variables);
    }

    /**
     * Create or edit a navigation.
     *
     * @param array $variables
     */
    public function actionBuildNavigation(array $variables = array())
    {
        if (empty($variables['navId'])) {
            throw new HttpException(404);
        }

        // Get navigation
        $variables['navigation'] = craft()->amNav->getNavigationById($variables['navId']);

        if (! $variables['navigation']) {
            throw new HttpException(404);
        }

        // Get locale
        if (isset($variables['locale'])) {
            $locale = $variables['locale'];
        }
        else {
            $locale = craft()->i18n->getPrimarySiteLocaleId();
            $variables['locale'] = $locale;
        }

        // Set element sources
        $entrySources = '*';
        if (isset($variables['navigation']->settings['entrySources'])) {
            $entrySources = $variables['navigation']->settings['entrySources'];
        }

        // Get proper siteUrl
        $siteUrl = craft()->config->getLocalized('siteUrl', $locale);
        if (! $siteUrl) {
            $siteUrl = craft()->getSiteUrl();
        }

        // Get saved nodes
        $variables['nodes'] = craft()->amNav->getNodesByNavigationId($variables['navId'], $locale);
        $variables['parentOptions'] = craft()->amNav->getParentOptions($variables['nodes'], ($variables['navigation']->settings['maxLevels'] ?: false));

        // Load javascript
        $js = sprintf(
            'new Craft.AmNav(%d, {
                locale: "%s",
                siteUrl: "%s",
                isAdmin: %s,
                entrySources: %s,
                maxLevels: %s,
                canDeleteFromLevel: %d,
                canMoveFromLevel: %d
            });',
            $variables['navId'],
            $locale,
            $siteUrl,
            craft()->userSession->isAdmin() ? 'true' : 'false',
            json_encode($entrySources),
            $variables['navigation']->settings['maxLevels'] ?: 'null',
            $variables['navigation']->settings['canDeleteFromLevel'] ?: 0,
            $variables['navigation']->settings['canMoveFromLevel'] ?: 0
        );
        craft()->templates->includeJs($js);
        craft()->templates->includeJsResource('amnav/js/AmNav.min.js');
        craft()->templates->includeCssResource('amnav/css/AmNav.css');
        craft()->templates->includeTranslations('Are you sure you want to delete “{name}” and its descendants?', 'Asset', 'Category', 'Entry', 'Manual');

        // Render the template
        $this->renderTemplate('amNav/_build', $variables);
    }

    /**
     * Deletes a navigation.
     */
    public function actionDeleteNavigation()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $navId = craft()->request->getRequiredPost('id');

        $result = craft()->amNav->deleteNavigationById($navId);
        $this->returnJson(array('success' => $result));
    }

    /**
     * Saves a navigation.
     */
    public function actionSaveNavigation()
    {
        $this->requirePostRequest();

        // Get navigation if available
        $navId = craft()->request->getPost('navId');
        if ($navId) {
            $navigation = craft()->amNav->getNavigationById($navId);

            if (! $navigation) {
                throw new Exception(Craft::t('No navigation exists with the ID “{id}”.', array('id' => $navId)));
            }
        }
        else {
            $navigation = new AmNav_NavigationModel();
        }

        // Set attributes
        $attributes = craft()->request->getPost();
        if (! isset($attributes['settings']['entrySources']) || $attributes['settings']['entrySources'] == '') {
            $attributes['settings']['entrySources'] = '*';
        }
        if (! is_numeric($attributes['settings']['maxLevels'])) {
            $attributes['settings']['maxLevels'] = '';
        }
        if (! is_numeric($attributes['settings']['canDeleteFromLevel'])) {
            $attributes['settings']['canDeleteFromLevel'] = '';
        }
        if (! is_numeric($attributes['settings']['canMoveFromLevel'])) {
            $attributes['settings']['canMoveFromLevel'] = '';
        }
        $navigation->setAttributes(array(
            'name' => $attributes['name'],
            'handle' => $attributes['handle'],
            'settings' => $attributes['settings']
        ));

        // Save navigation
        if (craft()->amNav->saveNavigation($navigation)) {
            craft()->userSession->setNotice(Craft::t('Navigation saved.'));
            $this->redirectToPostedUrl($navigation);
        }
        else {
            craft()->userSession->setError(Craft::t('Couldn’t save navigation.'));

            // Send the navigation back to the template
            craft()->urlManager->setRouteVariables(array(
                'navigation' => $navigation
            ));
        }
    }

    /**
     * Get elementType information.
     *
     * @param string $type
     *
     * @return array
     */
    private function _getElementTypeInfo($type)
    {
        $sources = array();
        $elementType = craft()->elements->getElementType($type);
        foreach ($elementType->getSources() as $key => $source) {
            if (!isset($source['heading'])) {
                $sources[] = array('label' => $source['label'], 'value' => $key);
            }
        }
        return array(
            'type' => $elementType->getName(),
            'sources' => $sources
        );
    }
}
