<?php
namespace Craft;

/**
 * Pages controller
 */
class AmNav_PagesController extends BaseController
{
    /**
     * Add a new page.
     */
    public function actionSaveNewPage()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        // New PageModel
        $page = new AmNav_PageModel();
        $returnData  = array(
            'success' => false,
            'message' => Craft::t('Not all required fields are filled out.')
        );

        // Set attributes
        $attributes = craft()->request->getPost();
        if (! empty($attributes['navId']) && ! empty($attributes['name']) && ! empty($attributes['url'])) {
            // Make sure we save a valid URL
            if (substr($attributes['url'], 0, 3) == 'www') {
                $attributes['url'] = 'http://' . $attributes['url'];
            }

            // Is there an entry ID available?
            if (isset($attributes['entryId'])) {
                $page->setAttribute('entryId', $attributes['entryId']);
            }

            $page->setAttributes(array(
                'navId'    => $attributes['navId'],
                'parentId' => 0,
                'name'     => $attributes['name'],
                'url'      => $attributes['url'],
                'blank'    => false,
                'enabled'  => true
            ));

            // Save the page!
            if (($page = craft()->amNav_page->savePage($page)) !== false) {
                $returnData['success']  = true;
                $returnData['message']  = Craft::t('Page added.');
                $returnData['pageData'] = array(
                    'id'       => $page->id,
                    'parentId' => $page->parentId,
                    'order'    => $page->order,
                    'name'     => $page->name,
                    'url'      => $page->url,
                    'blank'    => $page->blank,
                    'enabled'  => $page->enabled
                );
            }
        }

        // Return result
        $this->returnJson($returnData);
    }

    /**
     * Moves a page.
     */
    public function actionMovePage()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $pageId = craft()->request->getRequiredPost('pageId');

        // Get page
        $page = craft()->amNav_page->getPageById($pageId);
        if (! $page) {
            throw new HttpException(404);
        }

        // Get post values
        $prevId   = craft()->request->getPost('prevId', false);
        $parentId = craft()->request->getPost('parentId', 0);

        // Move page!
        $result = craft()->amNav_page->movePage($page, $parentId, $prevId);

        $this->returnJson(array('success' => $result));
    }

    /**
     * Deletes a page.
     */
    public function actionDeletePage()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $pageId = craft()->request->getRequiredPost('pageId');

        $result = craft()->amNav_page->deletePageById($pageId);
        $returnData = array(
            'success' => $result,
            'message' => Craft::t('Page deleted.')
        );
        $this->returnJson($returnData);
    }

    /**
     * Get HTML to edit a page.
     */
    public function actionGetEditorHtml()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $pageId = craft()->request->getRequiredPost('pageId');

        // Get page
        $variables['page'] = craft()->amNav_page->getPageById($pageId);
        if (! $variables['page']) {
            throw new HttpException(404);
        }

        $returnData['html'] = $this->renderTemplate('amNav/_editor', $variables, true);

        $this->returnJson($returnData);
    }
}