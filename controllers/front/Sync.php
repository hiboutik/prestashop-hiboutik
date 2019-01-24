<?php
use PrestaShop\PrestaShop\Core\Order\OrderCore;


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
    if (empty($_POST)) {
      return;
    }
    require _PS_MODULE_DIR_ . '/hiboutik/includes/HiboutikJsonMessage.php';
//     require _PS_MODULE_DIR_ . '/hiboutik/includes/HiboutikLog.php';
//     Hiboutik\HiboutikLog::$destination = _PS_MODULE_DIR_.'/hiboutik/log/hiboutik.log';
//     Hiboutik\HiboutikLog::write($_POST);

    $json_msg = new Hiboutik\HiboutikJsonMessage();

    if (empty($_POST) or !isset($_POST['sale_id'])) {
      $json_msg->alert('warning', "Warning: sync route has been accessed but no data was received.")->show();
      exit();
    }

    $config = Hiboutik::getHiboutikConfiguration();

    // Abort if the sale closed in Hiboutik was created initially in Prestashop
    if (isset($_POST['sale_ext_ref']) and strpos($_POST['sale_ext_ref'], $config['HIBOUTIK_SALE_ID_PREFIX']) === 0) {
      $sale_no = substr($_POST['sale_ext_ref'], strlen($config['HIBOUTIK_SALE_ID_PREFIX']));
      $order = new Order($sale_no);
      if ($order->current_state !== null) {
//         $json_msg->alert('info', "This sale was created in Prestashop")->show();
        exit();
      }
    }

    if (isset($_POST['line_items'])) {
      $hiboutik = Hiboutik::apiConnect($config);
      foreach ($_POST['line_items'] as $item) {
        if ($config['HIBOUTIK_SHIPPING_PRODUCT_ID'] = $item['product_id']) {
          continue;
        }
        if (!isset($item['product_barcode'])) {
//           $json_msg->alert('warning', "Product does not have a barcode: '{$item['product_model']}', id {$item['product_id']}. Skipping...");
          continue;
        }
        $id = Hiboutik::getIdByReferenceFromAttr($item['product_barcode']);
        if ($id) {
          $id_ref = Hiboutik::getAttributeIdByRef($item['product_barcode']);
        } else {
          $id_ref = 0;
          $id = Hiboutik::getIdByReference($item['product_barcode']);
        }

        if (!$id) {
          $json_msg->alert('warning', "Cannot find product in Prestashop using barcode: '{$item['product_model']}', id {$item['product_id']}. Skipping...");
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
      $json_msg->alert('success', 'Sale successfully synchronized with Prestashop');
    } else {
      $json_msg->alert('warning', 'Warning: No products received from the Hiboutik webhook. Unable to synchronize sale '.$_POST['sale_id'].'.');
    }
    $json_msg->show();
    exit;
  }
}
