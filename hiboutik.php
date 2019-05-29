<?php

use Hiboutik\Prestashop\HPUtil;


if (!defined('_PS_VERSION_')) {
    exit;
}


require _PS_MODULE_DIR_.'/hiboutik/includes/Hiboutik/HiboutikAPI/autoloader.php';


class Hiboutik extends Module
{
  public function __construct()
  {
    $this->name = 'hiboutik';
    $this->tab = 'quick_bulk_update';
    $this->version = '1.2.3';
    $this->author = 'Hiboutik & Murelh Ntyandi';
    $this->need_instance = 0;
    $this->ps_versions_compliancy = [
      'min' => '1.6',
      'max' => _PS_VERSION_
    ];
    $this->bootstrap = true;

    parent::__construct();

    $this->displayName = $this->l('Hiboutik');
    $this->description = $this->l('Synchronize Hiboutik POS software and Prestashop');

    $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

    if (!Configuration::get('HIBOUTIK_ACCOUNT') or !Configuration::get('HIBOUTIK_USER')) {
      $this->warning = $this->l('Hiboutik module is not configured');
    }
  }


  /**
   * Installation
   */
  public function install()
  {
    if (!parent::install()) {
        return false;
    }

    $settings = HPUtil::getSettings();
    foreach ($settings as $anInput) {
      if (!Configuration::updateValue($anInput['name'], $anInput['default'])) {
        return false;
      }
    }

    /* Hook actionPaymentConfirmation : https://devdocs.prestashop.com/1.7/modules/concepts/hooks/list-of-hooks/#update-notes
     * Called after a payment has been validated
     * Located in: /classes/order/OrderHistory.php
     * array('id_order' => (int) Order ID);
     */
    $this->registerHook('actionPaymentConfirmation');

    return true;
  }


  /**
   * Uninstallation
   */
  public function uninstall() {
    if (!parent::uninstall() || !Configuration::deleteByName('MYMODULE_NAME')) {
      return false;
    }

    return true;
  }


  /**
   * Manage submitted form
   */
  public function getContent() {
    $output = [];

    $settings = HPUtil::getSettings();
    if (Tools::isSubmit('submit' . $this->name)) {
      foreach ($settings as $input) {
        $value = strval(Tools::getValue($input['name']));
        // HIBOUTIK_SHIPPING_PRODUCT_ID should be able to take the value 0
        if ($value === false or !Validate::isGenericName($value)) {
          $output[] = $this->displayError('<li><strong>' . $input['label'] . ' : </strong>'.$this->l('Invalid Configuration value').'</li>');
        } else {
          Configuration::updateValue($input['name'], trim($value));
          $output[] = $this->displayConfirmation('<li><strong>' . $input['label'] . ' : </strong>'.$this->l('Settings updated').'</li>');
        }
      }
    }

    if (Tools::isSubmit('hiboutik_action')) {
      switch ($_POST['hiboutik_action']) {
        case 'sync_hiboutik':
          $result = $this->synchronizeStock();
          if ($result === true) {
            $output[] = $this->displayConfirmation($this->l('Stock synchronized!'));
          } else {
            $output[] = $this->displayError($this->l('Error synchronizing stock: ').$result);
          }
          break;
      }
    }

    return ((count($output) > 0) ? '<ul style="list-style-type:none">' . implode('', $output) . '</ul>' : '') . $this->displayForm();
  }


  /**
   * Create HTML form
   */
  public function displayForm() {
    // Get default language
    $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');

    // Init Fields form array
    $fieldsForm[0]['form'] = [
      'legend' => [
        'title' => 'Hiboutik Settings',
      ],
      'input' => HPUtil::getSettings(),
      'submit' => [
        'title' => 'Save',
        'class' => 'btn btn-default pull-right'
      ]
    ];

    $helper = new HelperForm();

    // Module, token and currentIndex
    $helper->module = $this;
    $helper->name_controller = $this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');
    $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

    // Language
    $helper->default_form_language = $defaultLang;
    $helper->allow_employee_form_lang = $defaultLang;

    // Title and toolbar
    $helper->title = $this->displayName;
    $helper->show_toolbar = true;        // false -> remove toolbar
    $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
    $helper->submit_action = 'submit' . $this->name;
    $helper->toolbar_btn = [
        'save' => [
            'desc' => $this->l('Save'),
            'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
            '&token=' . Tools::getAdminTokenLite('AdminModules'),
        ],
        'back' => [
            'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
            'desc' => $this->l('Back to list')
        ]
    ];

    // Load current value
    foreach ($fieldsForm[0]['form']['input'] as $anInput) {
      $helper->fields_value[$anInput['name']] = Configuration::get($anInput['name']);
    }

    $action_link = AdminController::$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules');
    $i18n_synchronise = $this->l('Synchronisation');
    $i18n_get_stock = $this->l('Get stock from Hiboutik');
    $i18n_sync_now = $this->l('Sync Now');
    $sync_html = <<<HTML
<form action="$action_link" method="post" class="defaultForm form-horizontal hiboutik" novalidate="">
  <input type="hidden" name="hiboutik_action" value="sync_hiboutik">
  <div class="panel">
    <div class="panel-heading">$i18n_synchronise</div>
    <div class="form-wrapper">
      <div class="form-group">
        <label class="control-label col-lg-3">
          $i18n_get_stock
        </label>
        <div class="col-lg-9">
          <button type="submit" class="btn btn-primary">
            $i18n_sync_now
          </button>
        </div>
      </div>
    </div>
  </div>
</form>
HTML;

    // Show webhook link
    // Security
    $key = Configuration::get('HIBOUTIK_KEY');
    if (!$key) {
      $key = Configuration::get('HIBOUTIK_OAUTH_TOKEN');
    }
    $hash = HPUtil::myHash(Configuration::get('HIBOUTIK_ACCOUNT'), $key);
    $link = Context::getContext()->link->getModuleLink('hiboutik', 'Sync', [HPUtil::SECURITY_GET_PARAM => $hash]);
    $link_html = <<<HTML
<div class="panel">
<div class="panel-heading">Webhook</div>
  $link
</div>
HTML;

    return $helper->generateForm($fieldsForm).$sync_html.$link_html;
  }


  /**
   * Sync stock from Hiboutik to Prestashop
   *
   * The link between Prestashop and Hiboutik is the product's barcode in Hiboutik
   */
  protected function synchronizeStock() {
    $config = HPUtil::getHiboutikConfiguration();
    if (empty($config['HIBOUTIK_ACCOUNT']) or empty($config['HIBOUTIK_USER'])) {
      return $this->l('please fill in your Hiboutik connection details');
    }

    $hiboutik = HPUtil::apiConnect($config);
    $stock_available = $hiboutik->get('/stock_available/warehouse_id/' . $config['HIBOUTIK_STORE_ID']);
    if ($hiboutik->request_ok) {
      foreach ($stock_available as $item) {
        $id_ref = 0;
        $id = HPUtil::getIdByReference($item['product_barcode']);
        if (!$id) {
          $id = HPUtil::getIdByReferenceFromAttr($item['product_barcode']);
          $id_ref = HPUtil::getAttributeIdByRef($item['product_barcode']);
        }
        StockAvailable::setQuantity((int) $id, $id_ref, $item['stock_available']);
      }
    } else {
      $i18n_error_connecting = $this->l('error connecting to Hiboutik API: %s');
      return sprintf($i18n_error_connecting, $stock_available['error_description']);
    }
    return true;
  }


  /**
   * Synchronize sale from Prestashop to Hiboutik
   *
   * @param array $orderParam
   * @return void
   */
  public function hookActionPaymentConfirmation($orderParam) {
    $config = HPUtil::getHiboutikConfiguration();
    $message_retour = [];

    $hiboutik = HPUtil::apiConnect($config);

    $sale_already_sync = $hiboutik->get('/sales/search/ext_ref/'.$config['HIBOUTIK_SALE_ID_PREFIX'].$orderParam['id_order']);
    if ($hiboutik->request_ok) {
      if (!empty($sale_already_sync)) {
        $message_retour[] = $message_retour[] = "Sale already synced -> Aborting";
        return;
      }

      $order = new Order($orderParam['id_order']);

      $orderDetail = new OrderDetail();
      $currentOrderDetailList = $orderDetail->getList($orderParam['id_order']);
      $customer = $order->getCustomer();
      $customerInvoiceAddress = new Address($order->id_address_invoice);
      $customerDeliveryAddress = new Address($order->id_address_delivery);
      $customerInvoiceCountry = new Country($customerInvoiceAddress->id_country);
      $customerCurrency = (new Currency($order->id_currency))->iso_code;
      $vendor_id = (int) $config['HIBOUTIK_VENDOR_ID'];
      $store_id = (int) $config['HIBOUTIK_STORE_ID'];

      // Sale comments
      $messages_vente = (new Message())->getMessagesByOrderId($orderParam['id_order']);
      $message = '';
      foreach ($messages_vente as $msg) {
        $message .= $msg['message'];
      }

      // Le client existe?
      $hibou_customer = 0;
      if (!empty($customer)) {
        $client_hiboutik = $hiboutik->get('/customers/search/', [
          'email' => $customer->email
        ]);
        if (empty($client_hiboutik)) {// Le client Hiboutik n'existe pas
          //création du client
          $hibou_create_customer = $hiboutik->post('/customers/', [
          'customers_last_name'    => $customer->lastname,
          'customers_first_name'   => $customer->firstname,
          'customers_email'        => $customer->email,
          'customers_country'      => $customerInvoiceCountry->iso_code,
          'customers_tax_number'   => '',
          'customers_phone_number' => $customerInvoiceAddress->phone,
          'customers_birth_date'   => $customer->birthday,
          ]);
          $hibou_customer = $hibou_create_customer['customers_id'];
          $message_retour[] = "Client created : id $hibou_customer";
        } else {
          $hibou_customer = $client_hiboutik[0]['customers_id'];
          $message_retour[] = "Client found : id $hibou_customer";
        }
      }

      $prices_without_taxes = 1;
      $duty_free_sale = 1;
      if ($order->total_paid_tax_incl != $order->total_paid_tax_excl) {
        $prices_without_taxes = 0;
        $duty_free_sale = 0;
      }

      if ($vendor_id == 0) $vendor_id = 1;

      //création de la vente sur Hiboutik
      $hibou_sale = $hiboutik->post('/sales/', [
        'store_id'             => $store_id,
        'customer_id'          => $hibou_customer,
        'duty_free_sale'       => $duty_free_sale,
        'prices_without_taxes' => $prices_without_taxes,
        'currency_code'        => $customerCurrency,
        'vendor_id'            => $vendor_id
      ]);

      if (isset($hibou_sale['error'])) {
        $message_retour[] = "Error : Unable to create sale on Hiboutik";
        return;
      } else {
        $hibou_sale_id = $hibou_sale['sale_id'];
        $message_retour[] = "Sale created : id $hibou_sale_id";
      }

      foreach ($currentOrderDetailList as $item) {
        $my_product_id    = $item['product_id'];
        $my_variation_id  = $item['product_attribute_id'];
        $my_quantity      = $item['product_quantity'];
        $my_name          = $item['product_name'];
        $my_product_price = $prices_without_taxes ? $item['unit_price_tax_excl'] : $item['unit_price_tax_incl'];
        $commentaires     = "";

        //récupération du code barre du produit vendu
        $bcProduct = $item['product_reference'];

        //on interroge l'API Hiboutik pour savoir quel est le produit (id Hiboutik) qui correspond au code barre
        $product_hiboutik = $hiboutik->get("/products/search/barcode/$bcProduct/");
        if (!$hiboutik->request_ok or isset($product_hiboutik['error'])) {
          $message_retour[] = 'Error getting product id';
        }
        //si on a trouvé un produit a partir du code barre alors on récupère product_id & product_size
        if (isset($product_hiboutik[0])) {
          $id_prod = $product_hiboutik[0]['product_id'];
          $id_taille = $product_hiboutik[0]['product_size'];
          $message_retour[] = "Product $my_product_id x$my_quantity ($my_variation_id) #$bcProduct added";
        } else {
          //si aucun produit a été trouvé à partir du code barre alors on ajoute le produit inconnu (product_id = 0)
          $id_prod = 0;
          $id_taille = 0;
          $commentaires = "Unknown product\nSKU : $bcProduct\nName : $my_name";
          $message_retour[] = "Product $my_product_id x$my_quantity ($my_variation_id) #$bcProduct unknown";
        }

        //ajout du produit sur la vente
        $hibou_add_product = $hiboutik->post('/sales/add_product/', [
          'sale_id'          => $hibou_sale_id,
          'product_id'       => $id_prod,
          'size_id'          => $id_taille,
          'quantity'         => $my_quantity,
          'product_price'    => $my_product_price,
          'stock_withdrawal' => 1,
          'product_comments' => $commentaires
        ]);

        //si il y a une quelconque erreur alors on ajoute a nouveau le produit mais sans sortie stock (cas du produit géré en stock mais indisponible)
        if (isset($hibou_add_product['error'])) {
          $id_prod = 0;
          $id_taille = 0;
          $commentaires = "Error: {$hibou_add_product['error_description']};";
          if (
            isset($hibou_add_product['details']) and
            isset($hibou_add_product['details']['product_id']) and
            $hibou_add_product['details']['product_id'] === "This function does not handle packages"
          ) {
            $commentaires .= ' '.$this->l('This function does not handle packages');
          }
          $commentaires .= "\n\n{$item['product_name']}, id_prod : $id_prod & id_taille : $id_taille";
          $hibou_add_product = $hiboutik->post('/sales/add_product/', [
            'sale_id'          => $hibou_sale_id,
            'product_id'       => $id_prod,
            'size_id'          => $id_taille,
            'quantity'         => $item['quantity'],
            'product_price'    => $my_product_price,
            'stock_withdrawal' => 0,
            'product_comments' => $commentaires
          ]);
          $message_retour[] = "Product $my_product_id x$my_quantity ($my_variation_id) #$bcProduct -> error";
        }
      }

      //gestion de la livraison
      $carrier = new Carrier($order->id_carrier);
      $name_livraison = $carrier->name;
      $method_id_livraison = $carrier->shipping_method;
      $commentaires_livraison = ($this->l('Carrier: '))."$name_livraison\n".($this->l('Shipping method id: '))."$method_id_livraison";

      $my_product_price = $order->total_shipping_tax_incl;
      $message_retour[] = "Delivery $my_product_price added";
      //ajout de la livraison
      $hibou_add_product = $hiboutik->post('/sales/add_product/', [
        'sale_id'          => $hibou_sale_id,
        'product_id'       => $config['HIBOUTIK_SHIPPING_PRODUCT_ID'],
        'size_id'          => 0,
        'quantity'         => 1,
        'product_price'    => $my_product_price,
        'stock_withdrawal' => 1,
        'product_comments' => $commentaires_livraison
      ]);

      //commentaires de la vente
      $hibou_add_comment = $hiboutik->post('/sales/comments/', [
        'sale_id'  => $hibou_sale_id,
        'comments' => "order_id : {$config['HIBOUTIK_SALE_ID_PREFIX']}{$orderParam['id_order']} - ".$order->reference.($message ? "\nComments : $message" : '')
      ]);

      //identifiant unique de la vente
      $hibou_update_sale_ext_ref = $hiboutik->put("/sale/$hibou_sale_id", [
        'sale_id'        => $hibou_sale_id,
        'sale_attribute' => "ext_ref",
        'new_value'      => $config['HIBOUTIK_SALE_ID_PREFIX'].$orderParam['id_order']
      ]);

    } else {
      $message_retour[] = "Error connecting to Hiboutik API";
    }
  }
}
