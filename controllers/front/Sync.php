<?php
use Hiboutik\Prestashop\HPUtil;
use Hiboutik\Utils\JsonMessage;
use Hiboutik\Utils\Logs;


/**
 * <ModuleName> => Hiboutik
 * <FileName> => Sync.php
 * Format expected: <ModuleName><FileName>ModuleFrontController
 *
 * Webhook for Hiboutik
 */
class HiboutikSyncModuleFrontController extends ModuleFrontController
{
  public function __construct()
  {
    $this->html = '';
    $this->display = 'view';
    $this->meta_title = $this->l('metatitle');
    $this->toolbar_title = $this->l('tollbartitle');

    parent::__construct();
  }


  /**
   * For GET requests
   *
   * Overrides parent method
   */
  public function initContent()
  {
    parent::initContent();

    $this->setTemplate('module:hiboutik/views/templates/front/sync.tpl');
  }


/**
 * For POST requests
 */
  public function postProcess()
  {
//     Logs::$destination = _PS_MODULE_DIR_.'/hiboutik/log/hiboutik.log';
//     Logs::write($_POST);
    if (empty($_POST)) {
      return;
    }

    $json_msg = new JsonMessage();

    // Security
    $config = HPUtil::getHiboutikConfiguration();
    $key = $config['HIBOUTIK_KEY'];
    if (!$key) {
      $key = $config['HIBOUTIK_OAUTH_TOKEN'];
    }
    $hash = isset($_GET[HPUtil::SECURITY_GET_PARAM]) ? $_GET[HPUtil::SECURITY_GET_PARAM] : null;
    if ($hash === null or !HPUtil::myHash($config['HIBOUTIK_ACCOUNT'], $key, $hash)) {
      $json_msg->alert('warning', $this->l('Prestashop: invalid authentication'))->show();
      exit();
    }

    if (!isset($_POST['sale_id'])) {
      $json_msg->alert('warning', $this->l('Prestashop: sync route has been accessed but no data was received'))->show();
      exit();
    }

    $config = HPUtil::getHiboutikConfiguration();

    // Abort if the sale closed in Hiboutik was created initially in Prestashop
    if (isset($_POST['sale_ext_ref']) and strpos($_POST['sale_ext_ref'], $config['HIBOUTIK_SALE_ID_PREFIX']) === 0) {
      $sale_no = substr($_POST['sale_ext_ref'], strlen($config['HIBOUTIK_SALE_ID_PREFIX']));
      $order = new Order($sale_no);
      if ($order->current_state !== null) {
        $json_msg->alert('warning', 'Sale created in Prestashop. Exiting.')->show();
        exit();
      }
    }

    if (isset($_POST['line_items'])) {
      $hiboutik = HPUtil::apiConnect($config);
      foreach ($_POST['line_items'] as $item) {
        if ($config['HIBOUTIK_SHIPPING_PRODUCT_ID'] == $item['product_id']) {
          continue;
        }
        if (!isset($item['product_barcode'])) {
//           $i18n_no_product_barcode = 'Prestashop: Product does not have a barcode: \'%s\', id %s. Skipping...';
//           $json_msg->alert('warning', sprintf($i18n_no_product_barcode, $item['product_model'], $item['product_id']));
          continue;
        }
        $id = HPUtil::getIdByReferenceFromAttr($item['product_barcode']);
        if ($id) {
          $id_ref = HPUtil::getAttributeIdByRef($item['product_barcode']);
        } else {
          $id_ref = 0;
          $id = HPUtil::getIdByReference($item['product_barcode']);
        }

        if (!$id) {
          $i18n_cannot_find_product = $this->l('Prestashop: Cannot find product in Prestashop using barcode: \'%s\', id %d. Skipping...');
          $json_msg->alert('warning', sprintf($i18n_cannot_find_product, $item['product_model'], $item['product_id']));
          continue;
        } else {
          // Returns array with stocks for each store
          $stocks_dispo = $hiboutik->get("/stock_available/product_id_size/{$item['product_id']}/{$item['product_size']}");
          foreach ($stocks_dispo as $stock) {
            if ($stock['warehouse_id'] == $config['HIBOUTIK_STORE_ID']) {
              $quantity = $stock['stock_available'];
              break;
            }
          }
          StockAvailable::setQuantity($id, $id_ref, $quantity);
        }
      }
      $json_msg->alert('success', $this->l('Prestashop: Sale successfully synchronized'));
    } else {
      $i18n_no_products_received = $this->l('Prestashop: No products received from the Hiboutik webhook. Unable to synchronize sale %d');
      $json_msg->alert('warning', sprintf($i18n_no_products_received, $_POST['sale_id']));
    }
    $json_msg->show();
    exit;
  }
}
