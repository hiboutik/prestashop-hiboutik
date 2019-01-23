<?php

//use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Stock\Stock;
use PrestaShop\PrestaShop\Core\Order\Order;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;

if (!defined('_PS_VERSION_')) {
    exit;
}

require _PS_MODULE_DIR_ . '/hiboutik/includes/Hiboutik/HiboutikAPI/autoloader.php';

//require _PS_CONTROLLER_DIR_ . '/front/ProductController.php';

class Hiboutik extends Module {

    public static $settingInput = [
      'HIBOUTIK_ACCOUNT' => [
          'label' => 'Account name',
          'name' => 'HIBOUTIK_ACCOUNT',
          'type' => 'text', 'size' => 255, 'required' => true,
          'default' => '',
          'desc' => 'If your Hiboutik URL is <strong>https://<span class="text-danger">my_account</span>.hiboutik.com/</strong>, your Hiboutik account is <strong>my_account</strong>.'
      ],
      'HIBOUTIK_USER' => [
          'label' => 'Email Address',
          'name' => 'HIBOUTIK_USER',
          'type' => 'text', 'size' => 255, 'required' => true,
          'default' => '',
          'desc' => 'Your Hiboutik e-mail address is mentioned on Hiboutik at <strong>Settings</strong> -> <strong>User</strong> -> <strong>API</strong>.'
      ],
      'HIBOUTIK_KEY' => [
          'label' => 'API Key',
          'name' => 'HIBOUTIK_KEY',
          'type' => 'text', 'size' => 255, 'required' => true,
          'default' => '',
          'desc' => 'It should looks like a long string. You will find it on Hiboutik at <strong>Settings</strong> -> <strong>User</strong> -> <strong>API</strong>'
      ],
      'HIBOUTIK_OAUTH_TOKEN' => [
          'label' => 'Oauth Token',
          'name' => 'HIBOUTIK_OAUTH_TOKEN',
          'placeholder' => '0',
          'default' => 'no',
          'type' => 'text', 'size' => 255, 'required' => false,
          'desc' => 'Your Hiboutik OAuth token. <strong>Type <span class="text-danger">no</span> if basic authentication is used</strong>'
      ],
      'HIBOUTIK_STORE_ID' => [
          'label' => 'Store ID',
          'name' => 'HIBOUTIK_STORE_ID',
          'placeholder' => '1',
          'default' => '1',
          'type' => 'text', 'size' => 255, 'required' => true,
          'desc' => 'If you do not know, put : 1. Or contact us.'
      ],
      'HIBOUTIK_VENDOR_ID' => [
          'label' => 'Vendor ID',
          'name' => 'HIBOUTIK_VENDOR_ID',
          'placeholder' => '1',
          'default' => '1',
          'type' => 'text', 'size' => 255, 'required' => true,
          'desc' => 'The vendor ID under which the synchronization will be made.<br>If you do not know, put : 1. Or contact us.'
      ],
      'HIBOUTIK_SHIPPING_PRODUCT_ID' => [
          'label' => 'Shipping Product ID',
          'name' => 'HIBOUTIK_SHIPPING_PRODUCT_ID',
          'placeholder' => '1',
          'default' => '1',
          'type' => 'text', 'size' => 255, 'required' => false,
          'desc' => 'The ID of the product in Hiboutik that designates shipping charges'
      ],
      'HIBOUTIK_SALE_ID_PREFIX' => [
          'label' => 'Hiboutik Sale ID Prefix',
          'name' => 'HIBOUTIK_SALE_ID_PREFIX',
          'type' => 'text', 'size' => 255, 'required' => false,
          'placeholder' => 'ps_',
          'default' => 'ps_',
          'placeholder' => 'ps_',
          'desc' => 'If you want to sort your sales in Hiboutik with ease, you can add a prefix to those who come from Prestashop.<br>Type this prefix here.'
      ]
    ];


    public function __construct() {
      $this->name = 'hiboutik';
      $this->tab = 'quick_bulk_update';
      $this->version = '1.1.0';
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

      if (!Configuration::get('MYMODULE_NAME')) {
        $this->warning = $this->l('No name provided');
      }
    }


    public function install() {
        if (!parent::install()) {
            return false;
        }

        foreach (self::$settingInput as $anInput) {
            if (!Configuration::updateValue($anInput['name'], $anInput['default'])) {
                return false;
            }
        }

        /* Hook actionPaymentConfirmation : https://devdocs.prestashop.com/1.7/modules/concepts/hooks/list-of-hooks/#update-notes
         * Called after a payment has been validated
         * Located in: /classes/order/OrderHistory.php
         * array('id_order' => (int) Order ID);
         *  */
        $this->registerHook('actionPaymentConfirmation');

        return true;
    }


    public function uninstall() {
        if (!parent::uninstall() || !Configuration::deleteByName('MYMODULE_NAME')) {
            return false;
        }

        return true;
    }


    public function getContent() {
        $output = [];

        if (Tools::isSubmit('submit' . $this->name)) {
            foreach (self::$settingInput as $key => $anInput) {
                self::$settingInput[$key]['safe_value'] = strval(Tools::getValue($anInput['name']));
                if (!self::$settingInput[$key]['safe_value'] || empty(self::$settingInput[$key]['safe_value']) || !Validate::isGenericName(self::$settingInput[$key]['safe_value'])) {
                    $output[] = $this->displayError('<li><strong>' . $anInput['label'] . ' : </strong>' . $this->l($this->l('Invalid Configuration value')) . '</li>');
                } else {
                    Configuration::updateValue($anInput['name'], self::$settingInput[$key]['safe_value']);
                    $output[] = $this->displayConfirmation('<li><strong>' . $anInput['label'] . ' : </strong>' . $this->l('Settings updated') . '</li>');
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
                $output[] = $this->displayError($this->l('Error synchronizing stock: '.$result));
              }
              break;
          }
        }

        return ((count($output) > 0) ? '<ul>' . implode('', $output) . '</ul>' : '') . $this->displayForm();
    }


    public function displayForm() {
// Get default language
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');

// Init Fields form array
        $fieldsForm[0]['form'] = [
            'legend' => [
                'title' => 'Hiboutik Settings',
            ],
            'input' => self::$settingInput,
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
        $sync_html = <<<HTML
<form action="$action_link" method="post" class="defaultForm form-horizontal hiboutik" novalidate="">
  <input type="hidden" name="hiboutik_action" value="sync_hiboutik">
  <div class="panel">
    <div class="panel-heading">Synchronisation</div>
    <div class="form-wrapper">
      <div class="form-group">
        <label class="control-label col-lg-3">
          Get stock from Hiboutik
        </label>
        <div class="col-lg-9">
          <button type="submit" class="btn btn-primary">
            Sync Now
          </button>
        </div>
      </div>
    </div>
  </div>
</form>
HTML;

    // Show webhook link
    $link = Context::getContext()->link->getModuleLink('hiboutik', 'Sync');
    $link_html = <<<HTML
<div class="panel">
  <div class="panel-heading">Webhook</div>
  $link
</div>
HTML;

        return $helper->generateForm($fieldsForm).$sync_html.$link_html;
    }


    public static function getHiboutikConfiguration() {
        $result = [];
        foreach (self::$settingInput as $key => $anInput) {
            $result[$key] = Configuration::get($anInput['name']);
        }
        return $result;
    }


    public function hookActionPaymentConfirmation($orderParam) {
      $config = self::getHiboutikConfiguration();
      $message_retour = [];

      $hiboutik = self::apiConnect($config);

      $sale_already_sync = $hiboutik->get('/sales/search/ext_ref/'.$config['HIBOUTIK_SALE_ID_PREFIX'].$orderParam['id_order']);
      if ($hiboutik->request_ok) {
        if (!empty($sale_already_sync)) {
          $message_retour[] = $message_retour[] = "Sale already synced -> Aborting";
          return;
        }

        $order = new OrderCore($orderParam['id_order']);
        $fields = $order->getFields();

        $orderDetail = new OrderDetail();
        $currentOrderDetailList = $orderDetail->getList($orderParam['id_order']);
        $customer = Context::getContext()->customer;
        $customerInvoiceAddress = new Address($orderParam['cart']->id_address_invoice);
        $customerDeliveryAddress = new Address($orderParam['cart']->id_address_delivery);
        $customerInvoiceCountry = new Country($customerInvoiceAddress->id_country);
        $customerCurrency = (new Currency($orderParam['cart']->id_currency))->iso_code;
        $vendor_id = (int) $config['HIBOUTIK_VENDOR_ID'];
        $store_id = (int) $config['HIBOUTIK_STORE_ID'];

        // Le client existe?
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
        if ($fields['total_paid_tax_incl'] != $fields['total_paid_tax_excl']) {
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
            return;
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
            $commentaires = "Results: " . print_r( $hibou_add_product, true );
            if ($hibou_add_product['details']['product_id'] == "This function does not handle packages") {
              $commentaires .= "\n\nid_prod : $id_prod & id_taille : $id_taille";
              $id_prod = 0;
              $id_taille = 0;
            }
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

          //gestion de la livraison
          $carrier = new Carrier($fields['id_carrier']);
          $name_livraison = $carrier->name;
          $method_id_livraison = $carrier->shipping_method;
          $commentaires_livraison = "$name_livraison\n$method_id_livraison";

          $my_product_price = $fields['total_shipping_tax_incl'];
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
          $hibou_add_product = $hiboutik->post('/sales/comments/', [
            'sale_id'  => $hibou_sale_id,
            'comments' => "order_id : $wc_order_id\nComments : $customer_note"
          ]);

          //identifiant unique de la vente
          $hibou_update_sale_ext_ref = $hiboutik->put("/sale/$hibou_sale_id", [
            'sale_id'        => $hibou_sale_id,
            'sale_attribute' => "ext_ref",
            'new_value'      => $config['HIBOUTIK_SALE_ID_PREFIX'].$orderParam['id_order']
          ]);
        }

      } else {
        $message_retour[] = "Error connecting to Hiboutik API";
      }
    }


/**
 * Sync stock from Hiboutik to Prestashop
 *
 * The link between Prestashop and Hiboutik is the product's barcode in Hiboutik
 */
    protected function synchronizeStock() {
      $config = self::getHiboutikConfiguration();
      if (empty($config['HIBOUTIK_ACCOUNT']) or empty($config['HIBOUTIK_USER'])) {
        return 'Cannot get settings values';
      }

      $hiboutik = self::apiConnect($config);
      $stock_available = $hiboutik->get('/stock_available/warehouse_id/' . $config['HIBOUTIK_STORE_ID']);
      if ($hiboutik->request_ok) {
//         print_r($stock_available);
        foreach ($stock_available as $item) {
          $id_ref = 0;
          if ($item['product_size'] != 0) {
            $id_ref = self::getAttributeIdByRef($item['product_barcode']);
          }
          $id = self::getIdByReference($item['product_barcode']);
          StockAvailable::setQuantity((int) $id, $id_ref, $item['stock_available']);
        }
      } else {
        return  'Error connecting to Hiboutik API: '.$stock_available['error_description'];
      }
      return true;
    }


/**
 * Connect to Hiboutik API
 *
 * Returns a configured instance of the HiboutikAPI class
 *
 * @param array $config Configuration array
 * @returns Hiboutik\HiboutikAPI
 */
    public static function apiConnect($config)
    {
      if ($config['HIBOUTIK_OAUTH_TOKEN'] == 'no' or $config['HIBOUTIK_OAUTH_TOKEN'] == '') {
        $hiboutik = new Hiboutik\HiboutikAPI($config['HIBOUTIK_ACCOUNT'], $config['HIBOUTIK_USER'], $config['HIBOUTIK_KEY']);
      } else {
        $hiboutik = new Hiboutik\HiboutikAPI($config['HIBOUTIK_ACCOUNT']);
        $hiboutik->oauth($config['HIBOUTIK_OAUTH_TOKEN']);
      }
      return $hiboutik;
    }


/**
 * Get product's id with reference
 *
 * @param string $ref Prestashop product reference
 * @return string Product id
 */
    public static function getIdByReference($ref)
    {
      if (empty($ref)) {
        return 0;
      }

      $query = new DbQuery();
      $query->select('p.id_product');
      $query->from('product', 'p');
      $query->where('p.reference = \''.pSQL($ref).'\'');

      return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }


/**
 * Get attributes's id with reference
 *
 * @param string $ref Prestashop product reference
 * @return int Attribute id
 */
    public static function getAttributeIdByRef($ref)
    {
      if (empty($ref)) {
        return 0;
      }

      $query = new DbQuery();
      $query->select('a.id_product_attribute');
      $query->from('product_attribute', 'a');
      $query->where('a.reference = \''.pSQL($ref).'\'');

      return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }


/**
 * Get product's id with reference to a size
 *
 * @param string $ref Prestashop product reference
 * @return int Attribute id
 */
    public static function getIdByReferenceFromAttr($ref)
    {
      if (empty($ref)) {
        return 0;
      }

      $query = new DbQuery();
      $query->select('a.id_product');
      $query->from('product_attribute', 'a');
      $query->where('a.reference = \''.pSQL($ref).'\'');

      return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }
}
