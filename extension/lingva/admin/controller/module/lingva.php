<?php

namespace Opencart\Admin\Controller\Extension\Lingva\Module;
class Lingva extends \Opencart\System\Engine\Controller {

  public function index(): void {

    $this->load->language('extension/lingva/module/lingva');

    $this->document->setTitle($this->language->get('heading_title'));

    $data['breadcrumbs'] = [];

    $data['breadcrumbs'][] = [
      'text' => $this->language->get('text_home'),
      'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
    ];

    $data['breadcrumbs'][] = [
      'text' => $this->language->get('text_extension'),
      'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module')
    ];

    $data['breadcrumbs'][] = [
      'text' => $this->language->get('heading_title'),
      'href' => $this->url->link('extension/lingva/module/lingva', 'user_token=' . $this->session->data['user_token'])
    ];

    $this->load->model('localisation/language');
    $data['languages'] = $this->model_localisation_language->getLanguages();

    $this->load->model('catalog/category');
    $data['categories'] = $this->model_catalog_category->getCategories();

    $data['save'] = $this->url->link('extension/lingva/module/lingva|save', 'user_token=' . $this->session->data['user_token']);
    $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');

    $data['log'] = DIR_LOGS . 'lingva.log';

    $data['user_token'] = $this->session->data['user_token'];

    $data['url'] = $this->config->get('module_lingva_url') ? $this->config->get('module_lingva_url') : 'https://lingva.ml/api/v1';
    $data['translate_categories'] = $this->config->get('module_lingva_categories');
    $data['from_language_id'] = $this->config->get('module_lingva_from_language_id');
    $data['from_language_code'] = $this->config->get('module_lingva_from_language_code') ? $this->config->get('module_lingva_from_language_code') : 'en';
    $data['to_language_id'] = $this->config->get('module_lingva_to_language_id');
    $data['to_language_code'] = $this->config->get('module_lingva_to_language_code') ? $this->config->get('module_lingva_to_language_code') : 'uk';

    $data['header'] = $this->load->controller('common/header');
    $data['column_left'] = $this->load->controller('common/column_left');
    $data['footer'] = $this->load->controller('common/footer');

    $this->response->setOutput($this->load->view('extension/lingva/module/lingva', $data));
  }

  public function save(): void {

    $this->load->language('extension/lingva/module/lingva');

    $json = [];

    if (!$this->user->hasPermission('modify', 'extension/lingva/module/lingva')) {
      $json['error']['warning'] = $this->language->get('error_permission');
    }

    if (!$this->request->post['module_lingva_url']) {
      $json['error']['url'] = $this->language->get('error_url');
    }

    if (!$this->request->post['module_lingva_from_language_code']) {
      $json['error']['from_language_code'] = $this->language->get('error_from_language_code');
    }

    if (!$this->request->post['module_lingva_to_language_code']) {
      $json['error']['to_language_code'] = $this->language->get('error_to_language_code');
    }

    if (!$json) {
      $this->load->model('setting/setting');

      $this->model_setting_setting->editSetting('module_lingva', $this->request->post);

      $json['success'] = $this->language->get('text_success');
    }

    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(json_encode($json));
  }

  public function getTotalTranslated(): void {

    $this->load->language('extension/lingva/module/lingva');

    $json = [];

    $this->load->model('extension/lingva/module/lingva');

    if ($this->config->get('module_lingva_categories')) {

      $category_ids   = [];
      $category_ids[] = 0;

      foreach ((array) $this->config->get('module_lingva_categories') as $category_id) {
        $category_ids[] = $category_id;
      }

      $total_products = $this->model_extension_lingva_module_lingva->getTotalProducts($category_ids);
    } else {
      $total_products = 0;
    }

    $total_translated = $this->model_extension_lingva_module_lingva->getTotalProductsTranslated();

    $json['total'] = sprintf($this->language->get('text_translated'), $total_translated, $total_products);

    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(json_encode($json));
  }

  public function translate(): void {

    $json['translate'] = false;

    $this->load->model('extension/lingva/module/lingva');

    $log = new \Opencart\System\Library\Log('lingva.log');

    if ($this->config->get('module_lingva_categories') &&
        $this->config->get('module_lingva_from_language_code') &&
        $this->config->get('module_lingva_to_language_code') &&
        $this->config->get('module_lingva_from_language_id') &&
        $this->config->get('module_lingva_to_language_id') &&
        $this->config->get('module_lingva_url')) {

      $log->write(sprintf('[%s] %s', 'notice', sprintf('translation started using %s gateway', $this->config->get('module_lingva_url'))));

      $category_ids   = [];
      $category_ids[] = 0;

      foreach ((array) $this->config->get('module_lingva_categories') as $category_id) {
        $category_ids[] = $category_id;
      }

      foreach ((array) $this->model_extension_lingva_module_lingva->getProducts($category_ids, $this->config->get('module_lingva_from_language_id')) as $product) {

        $name        = false;
        $description = false;

        if (!empty($product['name'])) {
          if (false === $name = $this->_translate($product['name'])) {
            $log->write(sprintf('[%s] %s %s', 'error', sprintf('could not translate product name for product id %s', $product['product_id'])));
          }
        } else {
          $name = '';
        }

        if (!empty($product['description'])) {
          if (false === $description = $this->_translate($product['description'])) {
            $log->write(sprintf('[%s] %s %s', 'error', sprintf('could not translate product description for product id %s', $product['product_id'])));
          }
        } else {
          $description = '';
        }

        if ($name !== false && $description !== false) {

          $this->model_extension_lingva_module_lingva->updateProductDescription($product['product_id'],
                                                                                $this->config->get('module_lingva_to_language_id'),
                                                                                $name,
                                                                                $description);

          if ($this->model_extension_lingva_module_lingva->translateProduct($product['product_id'])) {

            $log->write(sprintf('[%s] %s', 'notice', sprintf('product %s successfuly translated', $product['product_id'])));

            $json['translate'] = true;
          }

        } else {

          $log->write(sprintf('[%s] %s', 'error', sprintf('could not receive required product id %s fields - operation stopped.', $product['product_id'])));
        }
      }
    } else {

      $log->write(sprintf('[%s] %s', 'error', 'settings required'));
    }

    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(json_encode($json));
  }

  public function eventProductDelete(string &$route, array &$args, mixed &$output): void {

    if (isset($args[0])) {
      $this->load->model('extension/lingva/module/lingva');
      $this->model_extension_lingva_module_lingva->deleteProduct($args[0]);
    }
  }

  public function install(): void {

    $this->load->model('setting/event');

    $this->model_setting_event->addEvent([
      'code'        => 'extension_lingva_product_delete',
      'description' => 'update lingva registry on product delete' ,
      'trigger'     => 'admin/model/catalog/product/deleteProduct/after',
      'action'      => 'extension/lingva/module/lingva|eventProductDelete',
      'status'      => 1,
      'sort_order'  => 0,
    ]);

    $this->load->model('extension/lingva/module/lingva');
    $this->model_extension_lingva_module_lingva->install();
  }

  public function uninstall(): void {

    $this->load->model('setting/event');
    $this->model_setting_event->deleteEventByCode('extension_lingva_product_delete');

    $this->load->model('extension/lingva/module/lingva');
    $this->model_extension_lingva_module_lingva->unInstall();
  }

  private function _translate(string $string): string {

    $string = urlencode(str_replace('/', '|', str_replace(['<br>', '<br/>', '<br />'], '<br/>', $string)));

    $values = [];
    foreach ((array) explode('%3Cbr%7C%3E', $string) as $value) {

      if (!empty(trim(urldecode($value), '<br|>'))) {

        if ($response = file_get_contents(sprintf('%s/%s/%s/%s',  trim($this->config->get('module_lingva_url'), '/'),
                                                                  $this->config->get('module_lingva_from_language_code'),
                                                                  $this->config->get('module_lingva_to_language_code'),
                                                                  $value))) {

          $response = json_decode($response, true);

          if (isset($response['translation']) && !empty($response['translation'])) {

            $values[] = $response['translation'];

          } else {

            return false;
          }

        } else {

          return false;
        }
      }
    }

    return str_replace('|', '/', implode('<br|>', $values));
  }
}
