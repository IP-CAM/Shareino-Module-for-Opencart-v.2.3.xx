<?php

class ControllerExtensionModuleShareino extends Controller
{

    public function install()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "shareino_synchronize` (
            `id` BIGINT NOT NULL AUTO_INCREMENT,
            `product_id` BIGINT NOT NULL,
            `date_sync` DATETIME NOT NULL,
            `date_modified` DATETIME NOT NULL,
             PRIMARY KEY(`id`),
             UNIQUE(`product_id`));");
    }

    public function uninstall()
    {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "shareino_synchronize`;");
    }

    public function index()
    {
        /*
         * Default model
         */
        $this->load->model('setting/setting');
        $this->load->language('extension/module/shareino');

        /*
         * Default value
         */
        $shareino = [
            'shareino_category' => 0,
            'shareino_api_token' => $this->config->get('shareino_api_token')
        ];
        $this->model_setting_setting->editSetting('shareino', $shareino);

        /*
         * ShareINO model
         */
        $this->load->model('shareino/products');


        $data['shareino_api_token_title'] = $this->language->get('shareino_api_token');
        $data['heading_title'] = $this->language->get('heading_title');
        $this->document->setTitle($this->language->get('heading_title'));

        /*
         * Loading up some URLS.
         */
        $data['token'] = $this->session->data['token'];
        $data['action'] = $this->url->link('extension/module/shareino', 'token=' . $this->session->data['token'], 'SSL');
        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');
        $data['column_left'] = $this->load->controller('common/column_left');

        /*
         * Breadcrumb
         */
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=module', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $data['heading_title'],
            'href' => $this->url->link('extension/module/shareino', 'token=' . $this->session->data['token'], true)
        );

        /*
         * Save ShareINO tokan to local database
         */
        $data['error_warning'] = '';
        $data['shareino_api_token'] = '';
        if (isset($this->request->post['shareino_api_token'])) {
            if (strlen($this->request->post['shareino_api_token']) > 3) {
                $data['shareino_api_token'] = $this->request->post['shareino_api_token'];
                $this->request->post['shareino_category'] = 0;
                $this->model_setting_setting->editSetting('shareino', $this->request->post);
                $data['error_warning'] = $this->language->get('shareino_api_token_save');
            } else {
                $data['error_warning'] = $this->language->get('shareino_api_token_error');
            }
        } elseif (strlen($this->config->get('shareino_api_token')) > 0) {
            $data['shareino_api_token'] = $this->config->get('shareino_api_token');
        }

        /*
         * return to view
         */
        $this->destroyProducts();
        $data['countProduct'] = $this->model_shareino_products->getCount();
        $this->response->setOutput($this->load->view('extension/module/shareino.tpl', $data));
    }

    public function syncCategory()
    {
        $this->load->model('setting/setting');
        $shareino = [
            'shareino_category' => 1,
            'shareino_api_token' => $this->config->get('shareino_api_token')
        ];
        $this->model_setting_setting->editSetting('shareino', $shareino);

        /*
         * Send category to ShareINO
         */
        if (isset($this->request->post['id'])) {

            $this->load->model('shareino/categories');
            $this->load->model('shareino/requset');

            $categories = $this->model_shareino_categories->getCategories();
            $result = $this->model_shareino_requset->sendRequset('categories/sync', $categories, 'POST');

            $this->response->setOutput(json_encode($result));
        }
    }

    public function SyncProducts()
    {
        $this->load->model('setting/setting');
        if ($this->config->get('shareino_category') === '0') {
            $this->syncCategory();
        }

        /*
         * Send products to ShareINO
         */
        if (isset($this->request->post['pageNumber'])) {
            $limit = $this->request->post['split'];

            $this->response->addHeader('Content-Type: application/json');

            $this->load->model('shareino/products');
            $this->load->model('shareino/requset');

            $response = json_encode(array('status' => true, 'code' => 200, 'message' => 'فرایند ارسال محصولات به طول می انجامد لطفا صبور باشید.'));

            $products = array();
            if ($this->model_shareino_products->getIdes($limit)) {
                $products = $this->model_shareino_products->products($this->model_shareino_products->getIdes($limit));
            }

            if (!empty($products)) {
                $response = $this->model_shareino_requset->sendRequset('products', json_encode($products), 'POST');
            }

            $this->response->setOutput(json_encode($response));
        }
    }

    public function destroyProducts()
    {
        //call list ids for delete
        $this->load->model('shareino/synchronize');
        $listDestroy = $this->model_shareino_synchronize->destroy();

        //send request for delete
        $this->load->model('shareino/requset');
        $this->model_shareino_requset->deleteProducts($listDestroy);
    }

}
