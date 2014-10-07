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
        $variables['menus'] = craft()->amNav->getMenus();

        $this->renderTemplate('amNav/_index', $variables);
    }

    /**
     * Create or edit a menu.
     *
     * @param array $variables
     */
    public function actionEditMenu(array $variables = array())
    {
        // Retrieve menu if available
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

        // Retrieve menu
        $variables['menu'] = craft()->amNav->getMenuById($variables['menuId']);

        if (! $variables['menu']) {
            throw new HttpException(404);
        }

        // Load javascript
        $js = 'new Craft.AmNav();';
        craft()->templates->includeJs($js);
        craft()->templates->includeJsResource('amnav/js/AmNav.js');

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

        // Retrieve menu if available
        $menuId = craft()->request->getPost('menuId');
        if ($menuId) {
            $menu = craft()->amNav->getMenuById($menuId);

            if (! $menu) {
                throw new Exception(Craft::t('No menu exists with the ID “{id}”.', array('id' => $menuId)));
            }
        }
        else {
            $menu = new AmNav_MenuModel();
        }

        // Set attributes
        $attributes = craft()->request->getPost();
        $menu->setAttributes(array(
            'name' => $attributes['name'],
            'handle' => $attributes['handle']
        ));

        // Save menu
        if (craft()->amNav->saveMenu($menu)) {
            craft()->userSession->setNotice(Craft::t('Menu saved.'));
            $this->redirectToPostedUrl($menu);
        }
        else {
            craft()->userSession->setError(Craft::t('Couldn’t save menu.'));

            // Send the menu back to the template
            craft()->urlManager->setRouteVariables(array(
                'menu' => $menu
            ));
        }
    }
}