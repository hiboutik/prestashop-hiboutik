<?php

namespace Hiboutik\Prestashop;

use Hiboutik\HiboutikAPI;
use \Db;
use \DbQuery;
use \Module;
use \Configuration;

/**
 * @package Hiboutik\Prestashop\Util
 */
class HPUtil {

    /** @var const */
    const MODULE_NAME = 'hiboutik';

    /** @var const */
    const SECURITY_GET_PARAM = 'k';

    /** @var array Settings declaration */
    public static $settingInput;

    /** @var \Module */
    public static $module;

    /**
     * Get settings with translations
     *
     * @param void
     * @return array Settings
     */
    public static function getSettings() {
        if (self::$settingInput === null) {
            if (self::$settingInput === null) {
                self::$module = Module::getInstanceByName(self::MODULE_NAME);
            }
            self::$settingInput = [
                'HIBOUTIK_ACCOUNT' => [
                    'label' => self::$module->l('Account name', 'HPUtil'),
                    'name' => 'HIBOUTIK_ACCOUNT',
                    'type' => 'text', 'size' => 255, 'required' => true,
                    'default' => '',
                    'desc' => self::$module->l('If your Hiboutik URL is') . ' <strong>https://<span class="text-danger">my_account</span>.hiboutik.com,</strong>' . self::$module->l('your Hiboutik account is') . ' <strong class="text-danger">my_account.</strong>'
                ],
                'HIBOUTIK_USER' => [
                    'label' => self::$module->l('Email Address', 'HPUtil'),
                    'name' => 'HIBOUTIK_USER',
                    'type' => 'text', 'size' => 255, 'required' => true,
                    'default' => '',
                    'desc' => self::$module->l('Your Hiboutik e-mail address is mentioned on Hiboutik at') . ' <strong>' . self::$module->l('Settings') . '</strong> -> <strong>' . self::$module->l('User') . '</strong> -> <strong>API</strong>.'
                ],
                'HIBOUTIK_KEY' => [
                    'label' => self::$module->l('API Key', 'HPUtil'),
                    'name' => 'HIBOUTIK_KEY',
                    'type' => 'text', 'size' => 255, 'required' => true,
                    'default' => '',
                    'desc' => 'It should looks like a long string. You will find it on Hiboutik at <strong>Settings</strong> -> <strong>User</strong> -> <strong>API</strong>'
                ],
                'HIBOUTIK_OAUTH_TOKEN' => [
                    'label' => self::$module->l('Oauth Token', 'HPUtil'),
                    'name' => 'HIBOUTIK_OAUTH_TOKEN',
                    'placeholder' => 'no',
                    'default' => 'no',
                    'type' => 'text', 'size' => 255, 'required' => false,
                    'desc' => self::$module->l('Your Hiboutik OAuth token.') . self::$module->l('Basic authentication : ') . ' <strong class="text-danger">no</strong>'
                ],
                'HIBOUTIK_STORE_ID' => [
                    'label' => self::$module->l('Store ID', 'HPUtil'),
                    'name' => 'HIBOUTIK_STORE_ID',
                    'placeholder' => '1',
                    'default' => '1',
                    'type' => 'text', 'size' => 255, 'required' => true,
                    'desc' => self::$module->l('If you do not know, put : 1. Or contact us.')
                ],
                'HIBOUTIK_VENDOR_ID' => [
                    'label' => self::$module->l('Vendor ID', 'HPUtil'),
                    'name' => 'HIBOUTIK_VENDOR_ID',
                    'placeholder' => '1',
                    'default' => '1',
                    'type' => 'text', 'size' => 255, 'required' => true,
                    'desc' => self::$module->l('The vendor ID under which the synchronization will be made.') . '<br>' . self::$module->l('If you do not know, put : 1. Or contact us.')
                ],
                'HIBOUTIK_SHIPPING_PRODUCT_ID' => [
                    'label' => self::$module->l('Shipping Product ID', 'HPUtil'),
                    'name' => 'HIBOUTIK_SHIPPING_PRODUCT_ID',
                    'placeholder' => '1',
                    'default' => '1',
                    'type' => 'text', 'size' => 255, 'required' => false,
                    'desc' => self::$module->l('The ID of the product in Hiboutik that designates shipping charges')
                ],
                'HIBOUTIK_SALE_ID_PREFIX' => [
                    'label' => self::$module->l('Hiboutik Sale ID Prefix', 'HPUtil'),
                    'name' => 'HIBOUTIK_SALE_ID_PREFIX',
                    'type' => 'text', 'size' => 255, 'required' => false,
                    'placeholder' => 'ps_',
                    'default' => 'ps_',
                    'placeholder' => 'ps_',
                    'desc' => self::$module->l('If you want to sort your sales in Hiboutik with ease, you can add a prefix to those who come from Prestashop.')
                ]
            ];
        }
        return self::$settingInput;
    }

    /**
     * Get configuration
     */
    public static function getHiboutikConfiguration() {
        $result = [];
        $settings = self::getSettings();
        foreach ($settings as $key => $anInput) {
            $result[$key] = Configuration::get($anInput['name']);
        }
        return $result;
    }

    /**
     * Connect to Hiboutik API
     *
     * Returns a configured instance of the HiboutikAPI class
     *
     * @param array $config Configuration array
     * @returns Hiboutik\HiboutikAPI
     */
    public static function apiConnect($config) {
        if ($config['HIBOUTIK_OAUTH_TOKEN'] == 'no' or $config['HIBOUTIK_OAUTH_TOKEN'] == '') {
            $hiboutik = new HiboutikAPI($config['HIBOUTIK_ACCOUNT'], $config['HIBOUTIK_USER'], $config['HIBOUTIK_KEY']);
        } else {
            $hiboutik = new HiboutikAPI($config['HIBOUTIK_ACCOUNT']);
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
    public static function getIdByReference($ref) {
        if (empty($ref)) {
            return 0;
        }

        $query = new DbQuery();
        $query->select('p.id_product');
        $query->from('product', 'p');
        $query->where('p.reference = \'' . pSQL($ref) . '\'');

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }

    /**
     * Get attributes's id with reference
     *
     * @param string $ref Prestashop product reference
     * @return int Attribute id
     */
    public static function getAttributeIdByRef($ref) {
        if (empty($ref)) {
            return 0;
        }

        $query = new DbQuery();
        $query->select('a.id_product_attribute');
        $query->from('product_attribute', 'a');
        $query->where('a.reference = \'' . pSQL($ref) . '\'');

        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }

    /**
     * Get product's id with reference to a size
     *
     * @param string $ref Prestashop product reference
     * @return int Attribute id
     */
    public static function getIdByReferenceFromAttr($ref) {
        if (empty($ref)) {
            return 0;
        }

        $query = new DbQuery();
        $query->select('a.id_product');
        $query->from('product_attribute', 'a');
        $query->where('a.reference = \'' . pSQL($ref) . '\'');

        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }

    /**
     * Generate and check hashes
     *
     * @param string $string Value to hash
     * @param string $key Shared secret key used to generate the HMAC
     * @param string $check_hash Optional; hash to compare
     *
     * @return string|bool
     */
    public static function myHash($string, $key, $check_hash = null) {
        $hmac = hash_hmac('sha256', $string, $key);

        if ($check_hash === null) {
            return $hmac;
        }

        // Preventing timing attacks
        if (function_exists('\hash_equals'/* notice the namespace */)) {
            return hash_equals($check_hash, $hmac);
        }

        // Preventing timing attacks for PHP < v5.6.0
        $len_hash = strlen($hmac);
        $len_hash_rcv = strlen($check_hash);
        if ($len_hash !== $len_hash_rcv) {
            return false;
        }
        $equal = true;
        for ($i = $len_hash - 1; $i !== -1; $i--) {
            if ($hmac[$i] !== $check_hash[$i]) {
                $equal = false;
            }
        }
        return $equal;
    }

}
