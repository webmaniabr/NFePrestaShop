<?php
if (!defined('_PS_VERSION_')) {
    exit;
}
require_once __DIR__ . '/Security.php';

/** AJAX authorization is based on the current session and the requested resource. */
class WebmaniaNFeAjax
{
    public static function resource($method)
    {
        $tabs = array('getAddressInfo' => 'AdminAddresses', 'checkForDoc' => 'AdminCustomers',
            'getNfeStatus' => 'AdminOrders', 'getProductInfo' => 'AdminProducts',
            'getCategoryNcm' => 'AdminCategories', 'getPSColumns' => 'AdminModules', 'recoverEmission' => 'AdminOrders');
        return is_string($method) && isset($tabs[$method]) ? $tabs[$method] : false;
    }

    public static function authorized($context, $method, $token, $admin)
    {
        if (!is_string($method) || !is_string($token)) { return false; }
        if ($admin) {
            if (empty($context->employee) || !$context->employee->isLoggedBack() ||
                !WebmaniaNFeSecurity::equals(Tools::getAdminTokenLite('AdminWebmaniaNfeAjax'), $token)) { return false; }
            $resource = self::resource($method);
            if ($resource === false) { return false; }
            $access = Profile::getProfileAccess((int) $context->employee->id_profile, (int) Tab::getIdFromClassName($resource));
            return !empty($access['view']) && ($method !== 'recoverEmission' || !empty($access['edit']));
        }
        // Front-office requests may only read an address belonging to this customer.
        if ($method !== 'getAddressInfo' || empty($context->customer) || !$context->customer->isLogged() ||
            !WebmaniaNFeSecurity::equals(Tools::getToken(false), $token)) { return false; }
        $id = Tools::getValue('addressID');
        if (!is_scalar($id) || !ctype_digit((string) $id) || (int) $id < 1) { return false; }
        $address = new Address((int) $id);
        return Validate::isLoadedObject($address) && !$address->deleted && (int) $address->id_customer === (int) $context->customer->id;
    }

    public static function dispatch($context, $admin)
    {
        $method = Tools::getValue('method');
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST' ||
            !self::authorized($context, $method, Tools::getValue('adminToken'), $admin)) {
            header('HTTP/1.1 403 Forbidden');
            exit;
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $db = Db::getInstance();
        $result = array();
        switch ($method) {
            case 'recoverEmission':
                $module = Module::getInstanceByName('webmaniabrnfe');
                $result = $module ? $module->recoverEmission(Tools::getValue('order_id'), Tools::getValue('recovery_key'), Tools::getValue('confirm_absent')) :
                    array('success' => false, 'message' => 'Módulo de NF-e indisponível.');
                break;
            case 'getAddressInfo':
                $result = $db->getRow('SELECT address_number, bairro FROM ' . _DB_PREFIX_ . 'address WHERE id_address = ' . (int) Tools::getValue('addressID'));
                break;
            case 'checkForDoc':
                $data = $db->getRow('SELECT nfe_document_number, nfe_document_type, nfe_razao_social, nfe_pj_ie FROM ' . _DB_PREFIX_ . 'customer WHERE id_customer = ' . (int) Tools::getValue('id_customer'));
                $result = array('document_number' => 'error');
                if (is_array($data) && !empty($data['nfe_document_number'])) {
                    $result = array('document_number' => $data['nfe_document_number'], 'document_type' => $data['nfe_document_type'], 'razao_social' => $data['nfe_razao_social'], 'ie' => $data['nfe_pj_ie']);
                }
                break;
            case 'getNfeStatus':
                $result = $db->getValue('SELECT nfe_issued FROM ' . _DB_PREFIX_ . 'orders WHERE id_order = ' . (int) Tools::getValue('order_id'));
                break;
            case 'getProductInfo':
                $result = $db->getRow('SELECT nfe_tax_class, nfe_ean_bar_code, nfe_ncm_code, nfe_cest_code, nfe_product_source FROM ' . _DB_PREFIX_ . 'product WHERE id_product = ' . (int) Tools::getValue('productID'));
                break;
            case 'getCategoryNcm':
                // Same table as WebmaniaBrNFe::categoryNcmTable(): PS 1.6 installs the column in category_lang.
                $ncmTable = version_compare(_PS_VERSION_, '1.7', '>=') ? 'category' : 'category_lang';
                $ncm = $db->getValue('SELECT nfe_category_ncm FROM ' . _DB_PREFIX_ . $ncmTable . ' WHERE id_category = ' . (int) Tools::getValue('id_category'));
                $result = $ncm ? array('ncm' => $ncm, 'result' => 'success') : array('result' => 'error');
                break;
            case 'getPSColumns':
                $table = Tools::getValue('table_name');
                $columns = array();
                if (is_string($table) && preg_match('/^[a-zA-Z0-9_]+$/D', $table)) {
                    // MySQL 8 returns information_schema columns in upper case unless aliased.
                    $rows = $db->executeS("SELECT column_name AS column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '" . pSQL($table) . "'");
                    foreach (is_array($rows) ? $rows : array() as $row) { if (isset($row['column_name'])) { $columns[] = $row['column_name']; } }
                }
                $result = array('results' => $columns);
                break;
        }
        echo json_encode($result);
        exit;
    }
}
