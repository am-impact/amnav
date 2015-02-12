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

        $variables['menus'] = craft()->amNav->getMenus();
        $variables['settings'] = $plugin->getSettings();

        $this->renderTemplate('amNav/_index', $variables);
    }

    /**
     * Create or edit a menu.
     *
     * @param array $variables
     */
    public function actionEditMenu(array $variables = array())
    {
        // Get menu if available
        if (! empty($variables['menuId'])) {
            $variables['menu'] = craft()->amNav->getMenuById($variables['menuId']);

            if (! $variables['menu']) {
                throw new HttpException(404);
            }
        }
        else {
            $variables['menu'] = new AmNav_MenuModel();
        }

        // Render the template
        $this->renderTemplate('amNav/_edit', $variables);
    }

    /**
     * Create or edit a menu.
     *
     * @param array $variables
     */
    public function actionBuildMenu(array $variables = array())
    {
        if (empty($variables['menuId'])) {
            throw new HttpException(404);
        }

        // Get menu
        $variables['menu'] = craft()->amNav->getMenuById($variables['menuId']);

        if (! $variables['menu']) {
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

        // Get proper siteUrl
        $siteUrl = craft()->config->getLocalized('siteUrl', $locale);

        // Get saved pages
        $variables['pages'] = craft()->amNav->getPagesByMenuId($variables['menuId'], $locale);
        $variables['parentOptions'] = craft()->amNav->getParentOptions($variables['pages']);

        // Load javascript
        $js = sprintf(
            'new Craft.AmNav(%d, {
                locale: "%s",
                siteUrl: "%s",
                isAdmin: %s,
                maxLevels: %s,
                canDeleteFromLevel: %d,
                canMoveFromLevel: %d
            });',
            $variables['menuId'],
            $locale,
            $siteUrl,
            craft()->userSession->isAdmin() ? 'true' : 'false',
            $variables['menu']->settings['maxLevels'] ?: 'null',
            $variables['menu']->settings['canDeleteFromLevel'] ?: 0,
            $variables['menu']->settings['canMoveFromLevel'] ?: 0
        );
        craft()->templates->includeJs($js);
        craft()->templates->includeJsResource('amnav/js/AmNav.min.js');
        craft()->templates->includeCssResource('amnav/css/AmNav.css');
        craft()->templates->includeTranslations('Are you sure you want to delete “{name}” and its descendants?');

        // Render the template
        $this->renderTemplate('amNav/_build', $variables);
    }

    /**
     * Deletes a menu.
     */
    public function actionDeleteMenu()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $menuId = craft()->request->getRequiredPost('id');

        $result = craft()->amNav->deleteMenuById($menuId);
        $this->returnJson(array('success' => $result));
    }

    /**
     * Saves a menu.
     */
    public function actionSaveMenu()
    {
        $this->requirePostRequest();

        // Get menu if available
        $menuId = craft()->request->getPost('menuId');
        if ($menuId) {
            $menu = craft()->amNav->getMenuById($menuId);

            if (! $menu) {
                throw new Exception(Craft::t('No navigation exists with the ID “{id}”.', array('id' => $menuId)));
            }
        }
        else {
            $menu = new AmNav_MenuModel();
        }

        // Set attributes
        $attributes = craft()->request->getPost();
        if (! is_numeric($attributes['settings']['maxLevels'])) {
            $attributes['settings']['maxLevels'] = '';
        }
        if (! is_numeric($attributes['settings']['canDeleteFromLevel'])) {
            $attributes['settings']['canDeleteFromLevel'] = '';
        }
        if (! is_numeric($attributes['settings']['canMoveFromLevel'])) {
            $attributes['settings']['canMoveFromLevel'] = '';
        }
        $menu->setAttributes(array(
            'name' => $attributes['name'],
            'handle' => $attributes['handle'],
            'settings' => $attributes['settings']
        ));

        // Save menu
        if (craft()->amNav->saveMenu($menu)) {
            craft()->userSession->setNotice(Craft::t('Navigation saved.'));
            $this->redirectToPostedUrl($menu);
        }
        else {
            craft()->userSession->setError(Craft::t('Couldn’t save navigation.'));

            // Send the menu back to the template
            craft()->urlManager->setRouteVariables(array(
                'menu' => $menu
            ));
        }
    }
}