<?php

if(!defined('_PS_VERSION_')){
  exit;
}

//Define main version
if(!defined('_MAIN_PS_VERSION_')) {
  if (version_compare(_PS_VERSION_, '1.7', '>=')) {
    define('_MAIN_PS_VERSION_', '1.7');
  } else {
    define('_MAIN_PS_VERSION_', substr(_PS_VERSION_, 0, 3));
  }
}

//Webmania SDK to issue invoices
require_once __DIR__ . '/sdk/NFe.php';
require_once __DIR__ . '/sdk/Emission.php';

//PDF Merger to print NFe DANFEs
require_once __DIR__ . '/inc/pdf/PDFMerger.php';

class WebmaniaBrNFe extends Module{
  public $settings = array();
  public $uniq_key;
  private $automaticEmissionOrders = array();
  private $emissionTokens = array();
  private $emissionRelease = array();


  public function __construct(){

    $this->name = 'webmaniabrnfe';
    $this->tab = 'administration';
    $this->version = '3.0.3';
    $this->author = 'WebmaniaBR';
    $this->need_instance = 0;
    $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    $this->bootstrap = true;

    $this->uniq_key = Configuration::get($this->name.'uniq_key');

    //Webmania API Credentials
    $this->settings = array(
        'oauth_access_token'        => Configuration::get($this->name.'access_token'),
        'oauth_access_token_secret' => Configuration::get($this->name.'access_token_secret'),
        'consumer_key'              => Configuration::get($this->name.'consumer_key'),
        'consumer_secret'           => Configuration::get($this->name.'consumer_secret'),
    );

    $this->initContext();
    parent::__construct();

    //Verify if has module settings and cURL is enabled
    if(Module::isInstalled('webmaniabrnfe', 'AdminModules') && isset($this->context->controller->controller_type) &&  $this->context->controller->controller_type == 'admin'){
      $this->checkAuthentication();
      $this->checkCURL();
    }

    //Set Module infos
    $this->displayName = $this->l('WebmaniaBR NF-e');
    $this->description = $this->l('Módulo de emissão de Nota Fiscal Eletrônica para PrestaShop através da REST API da WebmaniaBR®.');
    $this->confirmUninstall = $this->l('Tem certeza que deseja desinstalar este módulo?');

  }

  /**
   * Get PrestaShop version for compatibility checks
   * @return string
   */
  public function getPrestaShopVersion() {
    return defined('_PS_VERSION_') ? _PS_VERSION_ : '1.6.0.0';
  }

  /**
   * Check if running on modern PrestaShop (8+)
   * @return bool
   */
  public function isModernPrestaShop() {
    return version_compare($this->getPrestaShopVersion(), '8.0.0', '>=');
  }

  /**
   * Check if running on PrestaShop 8+
   * @return bool
   */
  public function isPrestaShop8Plus() {
    return version_compare($this->getPrestaShopVersion(), '8.0.0', '>=');
  }

  /**
   * Check if running on PrestaShop 9+
   * @return bool
   */
  public function isPrestaShop9Plus() {
    return version_compare($this->getPrestaShopVersion(), '9.0.0', '>=');
  }

  /**
   * Format price with compatibility for different PS versions
   * @param float $price
   * @param Currency|null $currency
   * @return string
   */
  public function formatPriceCompatible($price, $currency = null) {
    if (method_exists($this->context, 'getCurrentLocale') && $this->isPrestaShop8Plus()) {
      $iso = $currency ? $currency->iso_code : ($this->context->currency ? $this->context->currency->iso_code : null);
      return $this->context->getCurrentLocale()->formatPrice($price, $iso);
    }
    return Tools::displayPrice($price, $currency ?: $this->context->currency);
  }

  /**
   * Get admin token with compatibility
   * @param string $controller
   * @return string
   */
  public function getAdminTokenCompatible($controller) {
    if (class_exists('Tools') && method_exists('Tools', 'getAdminTokenLite')) {
      return Tools::getAdminTokenLite($controller);
    }
    return Tools::getAdminToken($controller.(int)Tab::getIdFromClassName($controller).(int)$this->context->employee->id);
  }

  /** Permission of the current employee on a back-office tab ('1'/'0' on PS 1.6, roles on 1.7+). */
  public function employeeCan($tab, $permission) {
    $employee = isset($this->context->employee) ? $this->context->employee : null;
    if (!is_object($employee) || empty($employee->id_profile)) { return false; }
    $access = Profile::getProfileAccess((int) $employee->id_profile, (int) Tab::getIdFromClassName($tab));
    return is_array($access) && !empty($access[$permission]);
  }

  /** Table/column names configured for document mapping may only be plain identifiers. */
  public function isSqlIdentifier($name) {
    return is_string($name) && (bool) preg_match('/^[a-zA-Z0-9_]{1,64}$/D', $name);
  }

  /** alterTable() stores the category NCM in category_lang on PS 1.6 and in category on 1.7+. */
  public function categoryNcmTable() {
    return _MAIN_PS_VERSION_ >= '1.7' ? 'category' : 'category_lang';
  }

  /** Callback URL from the configured shop domain, never from Host/X-Forwarded-Host. */
  public function notificationUrl($uniq_key, $order_id) {
    return Tools::getShopDomainSsl(true).__PS_BASE_URI__.'?retorno_nfe='.rawurlencode((string) $uniq_key).'&order_id='.(int) $order_id;
  }

  /** Automatic hooks only issue the first NF-e of an order; re-issuance stays a manual decision. */
  public function emitirNfeAutomatico($order_id) {
    $order_id = (int) $order_id;
    if ($order_id < 1 || Configuration::get($this->name.'automatic_emit') != 'on') { return array(); }
    if (WebmaniaNFeSecurity::invoices(Db::getInstance()->getValue('SELECT nfe_info FROM '._DB_PREFIX_.'orders WHERE id_order = '.$order_id, false))) { return array(); }
    if (isset($this->automaticEmissionOrders[$order_id])) { return array(); }
    $this->automaticEmissionOrders[$order_id] = true;
    try { return $this->emitirNfe($order_id); }
    finally { unset($this->automaticEmissionOrders[$order_id]); }
  }

  /** Webservice, CLI and IPN scripts have no controller; front controllers have no "confirmations". */
  private function controllerMessage($property, $message) {
    $controller = isset($this->context->controller) ? $this->context->controller : null;
    if (!is_object($controller) || !isset($controller->$property) || !is_array($controller->$property)) { return; }
    $messages = $controller->$property;
    $messages[] = $message;
    $controller->$property = $messages;
  }

  /** Transport data is saved by display hooks: require POST and order edit permission. */
  public function canSaveTransportInfo() {
    return Tools::getValue('nfe-transporte-info') && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' &&
      $this->employeeCan('AdminOrders', 'edit');
  }

  public function ensureAjaxController() {
    if (Tab::getIdFromClassName('AdminWebmaniaNfeAjax')) { return true; }
    $tab = new Tab();
    $tab->active = 0;
    $tab->id_parent = -1;
    $tab->class_name = 'AdminWebmaniaNfeAjax';
    $tab->module = $this->name;
    foreach (Language::getLanguages(false) as $language) { $tab->name[(int) $language['id_lang']] = 'Webmania NF-e AJAX'; }
    return $tab->add();
  }

  public function install(){
    if (!$this->ensureAjaxController()) { return false; }


    //Set Default config values
    $configValues = $this->getConfigInitValues();
    foreach($configValues as $key => $value){
      if(!Configuration::get($key)){
        Configuration::updateValue($key, $value);
      }
    }

    //Define hooks
    $hooks_main = array(
      'backOfficeHeader',
      'displayBackOfficeHeader',
      'displayHeader',
      'displayCustomerAccount',
      'displayCustomerAccountForm',
      'actionPaymentConfirmation',
      'displayAdminProductsExtra',
      'actionProductUpdate',
      'actionAdminOrdersListingFieldsModifier',
      'actionOrderGridDefinitionModifier',
      'actionOrderGridQueryBuilderModifier',
      'createAccountForm',
      'actionCustomerAccountAdd',
      'displayInvoice',
      'displayBackOfficeCategory',
      'actionCategoryUpdate',
      'actionObjectAddressUpdateAfter',
      'actionObjectAddressAddAfter',
      'actionObjectUpdateAfter',
      'actionObjectCustomerAddAfter',
      'actionObjectCustomerUpdateAfter',
      'displayBackOfficeCategory',
      'displayAdminOrderTop',
      'displayAdminOrderTabLink',
      'displayAdminOrderTabContent',
      'displayAdminOrderTabShip',
      'displayAdminOrderContentShip',
      'displayAdminCustomers'
    );

    //Additional hooks for version 1.7+
    if(_MAIN_PS_VERSION_ == '1.7' || $this->isPrestaShop8Plus()){
      $hooks_main[] = 'additionalCustomerFormFields';
      $hooks_main[] = 'validateCustomerFormFields';
    }

    //Additional hooks for PrestaShop 8+
    if($this->isPrestaShop8Plus()) {
      $hooks_main[] = 'actionFrontControllerSetMedia';
      $hooks_main[] = 'actionAdminControllerSetMedia';
      $hooks_main[] = 'displayAdminOrderMain';
    }

    // Order status post update available since PS 1.7.7+
    if (version_compare($this->getPrestaShopVersion(), '1.7.7.0', '>=')) {
      $hooks_main[] = 'actionOrderStatusPostUpdate';
    }

    //Additional hooks for PrestaShop 9+  
    if($this->isPrestaShop9Plus()) {
      $hooks_main[] = 'displayAdminOrderMainBottom';
      $hooks_main[] = 'actionValidateOrderAfter';
    }

    //Installation failed
    if(!parent::install() || !$this->alterTable('add') || !WebmaniaNFeEmission::ensureTable()){
      return false;
    }

    //Register all hooks
    foreach($hooks_main as $hook){
      if(!$this->registerHook($hook)){
        return false;
      }
    }

    //Does not allow checkout as a guest 
    Configuration::updateValue('PS_GUEST_CHECKOUT_ENABLED', 0);
    return true;

  }

  /**
   * Display new elements in the Back Office, tab AdminCustomers
   * This hook launches modules when the AdminCustomers tab is displayed in the Back Office
   * 
   * @param array $customer
   */
  public function hookDisplayAdminCustomers(array $customer) {

    $customer_id = isset($customer['id_customer']) ? (int) $customer['id_customer'] : 0;

    //Get customer info
    $customer_info = Db::getInstance()->getRow("SELECT nfe_document_type, nfe_document_number, nfe_razao_social, nfe_pj_ie FROM "._DB_PREFIX_."customer WHERE id_customer = ".$customer_id );

    if (!is_array($customer_info) || empty($customer_info['nfe_document_type'])) {
      return '';
    }

    $this->context->smarty->assign(array(
      'customer_info' => array_map('strval', $customer_info),
    ));

    //Customer info template
    return (_MAIN_PS_VERSION_ <= 1.6) ? $this->display(__FILE__, 'customer_document_info.1.6.tpl') : $this->display(__FILE__, 'customer_document_info.1.7.tpl');

  }

  /**
   * Hooks allows to modify Customer grid definition.
   * This hook is a right place to add/remove columns or actions (bulk, grid).
   *
   * @param array $params
   */
  public function hookActionOrderGridDefinitionModifier(array $params)
  {

    $definition = $params['definition'];

    //Set new column 'Status NF-e' to orders list
    $columns = $definition->getColumns();
    $nfe_status = new PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn('nfe_issued');
    $nfe_status->setName('Status NF-e');
    $nfe_status->setOptions([
      'field' => 'nfe_issued'
    ]);
    $columns->addAfter('id_order', $nfe_status);


    $bulkActions = $definition->getBulkActions();

    //Add bulk action to issue invoice
    $bulkActions->add(
      (new PrestaShop\PrestaShop\Core\Grid\Action\Bulk\Type\SubmitBulkAction('emitir_nfe'))
        ->setName('Emitir NF-e')
        ->setOptions([
          'submit_route' => 'webmaniabrnfe_emitirnfe',
          'submit_method' => 'POST'
        ])
      );
    
    //Add bulk action to print DANFE
    $bulkActions->add(
      (new PrestaShop\PrestaShop\Core\Grid\Action\Bulk\Type\SubmitBulkAction('imprimir_danfe'))
        ->setName('Imprimir Danfe')
        ->setOptions([
          'submit_route' => 'webmaniabrnfe_imprimir_danfe',
          'submit_method' => 'POST'
        ])
      );
    
    //Add bulk action to print DANFE Simples
    $bulkActions->add(
      (new PrestaShop\PrestaShop\Core\Grid\Action\Bulk\Type\SubmitBulkAction('imprimir_danfe_simples'))
        ->setName('Imprimir Danfe Simples')
        ->setOptions([
          'submit_route' => 'webmaniabrnfe_imprimir_danfe_simples',
          'submit_method' => 'POST'
        ])
      );
      
    //Add bulk action to print DANFE Etiqueta
    $bulkActions->add(
      (new PrestaShop\PrestaShop\Core\Grid\Action\Bulk\Type\SubmitBulkAction('imprimir_danfe_etiqueta'))
        ->setName('Imprimir Danfe Etiqueta')
        ->setOptions([
          'submit_route' => 'webmaniabrnfe_imprimir_danfe_etiqueta',
          'submit_method' => 'POST'
        ])
      );

  }

  public function hookActionOrderGridQueryBuilderModifier(array $params) {

    //Define search query to column 'Status NF-e' in orders list
    $searchQueryBuilder = $params['search_query_builder'];
    $searchCriteria = $params['search_criteria'];
    $searchQueryBuilder->addSelect(
      'IF(o.`nfe_issued` IS NULL,0,o.`nfe_issued`) AS `nfe_issued`'
    );
 
    if ('nfe_issued' === $searchCriteria->getOrderBy()) {
      $searchQueryBuilder->orderBy('o.`nfe_issued`', strtolower((string) $searchCriteria->getOrderWay()) === 'desc' ? 'DESC' : 'ASC');
    }

  }

  public function hookDisplayAdminOrderTop($params) {
    
    $order_id = (int) $params['id_order'];
    $recovery = $this->displayEmissionRecovery($order_id);
    $order = new Order($order_id);
    $customer = $order->getCustomer();
    //Get order's nfe info
    $nfe_info = WebmaniaNFeSecurity::invoices(Db::getInstance()->getValue("SELECT nfe_info FROM "._DB_PREFIX_."orders WHERE id_order = $order_id" ));

    $url  = 'index.php?controller=AdminOrders&id_order='.$order_id;
    $url .= '&vieworder&token='.Tools::getAdminTokenLite('AdminOrders');

    //Doesn't have invoices issued
    if (!$nfe_info) {
      return $recovery."
        <div class='nfe_info card'>
          <div class='card-header'>
            <h3 class='card-header-title'>Notas emitidas para este pedido</h3>
          </div>
          <div class='card-body'>
            <p>Nenhuma nota emitida.</p>
          </div>
        </div>
      ";
    }
    $customer_docs = $this->getCustomerDocs($customer, $order);
    $document = '';

    if ($customer_docs['cnpj'] && $customer_docs['cnpj'] != $customer_docs['cpf'] || $this->is_cnpj($customer_docs['cpf'])){

      $document = $this->cnpj($customer_docs['cnpj']);
      if( !$document && isset($customer->cnpj) ) $document = $customer->cnpj;

    } else {

      $document = $this->cpf($customer_docs['cpf']);
      if( !$document && isset($customer->cpf) ) $document = $customer->cpf;

    }

    //Set danfe simples/etiqueta url if it doesn't exist
    foreach ($nfe_info as $key => $nfe) {
      if (empty($nfe['url_danfe_simplificada'])) {
        $nfe_info[$key]['url_danfe_simplificada'] = str_replace('/danfe/', '/danfe/simples/', $nfe['url_danfe']);
      }
      if (empty($nfe['url_danfe_etiqueta'])) {
        $nfe_info[$key]['url_danfe_etiqueta'] = str_replace('/danfe/', '/danfe/etiqueta/', $nfe['url_danfe']);
      }
      $password = !empty($nfe['documento']) ? $nfe['documento'] : $document;
      if(!empty($password)){
        $tokens = [
          "danfe" => $this->createSecureTokenDFe($password, $nfe['uuid']),
          "danfe_simples" => $this->createSecureTokenDFe($password, $nfe['uuid']),
          "danfe_etiqueta" => $this->createSecureTokenDFe($password, $nfe['uuid']),
          "xml" => $this->createSecureTokenDFe($password, $nfe['uuid']),
        ];

        $nfe_info[$key]['url_danfe'] .= '?token=' . $tokens['danfe'];
        $nfe_info[$key]['url_danfe_simplificada'] .= '?token=' . $tokens['danfe_simples'];
        $nfe_info[$key]['url_danfe_etiqueta'] .= '?token=' . $tokens['danfe_etiqueta'];
        $nfe_info[$key]['url_xml'] .= '?token=' . $tokens['xml'];
      }

    }

    if($this->canSaveTransportInfo()){
      $this->save_order_transporte_info($order_id);
    }

    $this->context->smarty->assign(array(
      'nfe_info_arr' => $nfe_info,
      'url' => $url,
    ));

    //NF-e info template
    return $recovery.$this->display(__FILE__, 'nfe_info_table.tpl');

  }

  public function uninstall(){
    $tab_id = (int) Tab::getIdFromClassName('AdminWebmaniaNfeAjax');
    if ($tab_id) { $tab = new Tab($tab_id); $tab->delete(); }


    if(!parent::uninstall()){
      return false;
    }

    return true;

  }


  public function getContent(){


    $output = null;
    if(Tools::isSubmit('submit'.$this->name)){
      $fields = $this->getConfigInitValues();
      foreach($fields as $key => $value){

        $raw_value = Tools::getValue($key);
        if (!is_scalar($raw_value)) { continue; }
        $input_value = (string) $raw_value;
        if (in_array($key, array($this->name.'consumer_key', $this->name.'consumer_secret', $this->name.'access_token', $this->name.'access_token_secret'), true) && $input_value === '') { continue; }


        if($input_value){

          if(Validate::isGenericName($input_value)){
            Configuration::updateValue($key, $input_value);
          }elseif($key == 'webmaniabrnfecarriers'){
            Configuration::updateValue($key, $input_value);
          }else{
            $output .= $this->displayError($this->l('Invalid Configuration Value'));
          }
        }else{
          Configuration::updateValue($key, '');
        }
      }
      $output .= $this->displayConfirmation($this->l('Settings updated'));
    }


    return $output.$this->displayForm();
  }

  /**
  * Get all the shipping methods configured in the store
  */
  public function get_shipping_methods_options() {

    $options = array();

    $options[] = array(
      'id_option' => '',
      'name' => 'Selecionar'
    );

    $default_language = (int)Configuration::get('PS_LANG_DEFAULT');
    $methods = Carrier::getCarriers($default_language);

    foreach($methods as $carrier){
      $options[] = array(
        'id_option' => $carrier['id_carrier'],
        'name' => $carrier['name'],
      );
    }

    return $options;

  }

  /**
  * Get all the payment methods configured in the store
  */
  public function get_payment_methods_options() {

    $options = array();

    $default_language = (int)Configuration::get('PS_LANG_DEFAULT');
    $methods = PaymentModule::getInstalledPaymentModules();

    foreach($methods as $payment_method){
      $options[] = array(
        'value' => $payment_method['name'],
        'label' => $payment_method['name'],
      );
    }

    return $options;

  }

  public function displayForm(){
    ob_start();
    require __DIR__ . '/views/templates/settings-page.php';
    return ob_get_clean();
  }

  public function getConfigFieldsValues(){

    return array(
      $this->name.'consumer_key' => '',
      $this->name.'consumer_secret' => '',
      $this->name.'access_token' => '',
      $this->name.'access_token_secret' => '',
      $this->name.'sefaz_env' => Configuration::get($this->name.'sefaz_env'),
      $this->name.'automatic_emit' => Configuration::get($this->name.'automatic_emit'),
      $this->name.'auto_emit_status_ids' => Configuration::get($this->name.'auto_emit_status_ids'),
      $this->name.'operation_type' => Configuration::get($this->name.'operation_type'),
      $this->name.'tax_class' => Configuration::get($this->name.'tax_class'),
      $this->name.'ean_barcode'     => Configuration::get($this->name.'ean_barcode'),
      $this->name.'gtin_tributavel' => Configuration::get($this->name.'gtin_tributavel'),
      $this->name.'ncm_code' => Configuration::get($this->name.'ncm_code'),
      $this->name.'cest_code' => Configuration::get($this->name.'cest_code'),
      $this->name.'cnpj_fabricante' => Configuration::get($this->name.'cnpj_fabricante'),
      $this->name.'ind_escala' => Configuration::get($this->name.'ind_escala'),
      $this->name.'product_source' => Configuration::get($this->name.'product_source'),
      $this->name.'intermediador' => Configuration::get($this->name.'intermediador'),
      $this->name.'intermediador_cnpj' => Configuration::get($this->name.'intermediador_cnpj'),
      $this->name.'intermediador_id' => Configuration::get($this->name.'intermediador_id'),
      $this->name.'person_type_fields' => Configuration::get($this->name.'person_type_fields'),
      $this->name.'mask_fields' => Configuration::get($this->name.'mask_fields'),
      $this->name.'fill_address' => Configuration::get($this->name.'fill_address'),
      $this->name.'fisco_inf' => Configuration::get($this->name.'fisco_inf'),
      $this->name.'cons_inf' => Configuration::get($this->name.'cons_inf'),
      $this->name.'enable_person_type' => Configuration::get($this->name.'enable_person_type'),
      $this->name.'tipo_cliente_field' => Configuration::get($this->name.'tipo_cliente_field'),
      $this->name.'cpf_field' => Configuration::get($this->name.'cpf_field'),
      $this->name.'cnpj_field' => Configuration::get($this->name.'cnpj_field'),
      $this->name.'razao_social_field' => Configuration::get($this->name.'razao_social_field'),
      $this->name.'cnpj_ie_field' => Configuration::get($this->name.'cnpj_ie_field'),
      $this->name.'numero_field' => Configuration::get($this->name.'numero_field'),
      $this->name.'bairro_status' => Configuration::get($this->name.'bairro_status'),
      $this->name.'bairro_field' => Configuration::get($this->name.'bairro_field'),
      $this->name.'complemento_field' => Configuration::get($this->name.'complemento_field'),
      $this->name.'cpf_cnpj_status' => Configuration::get($this->name.'cpf_cnpj_status'),
      $this->name.'numero_compl_status' => Configuration::get($this->name.'numero_compl_status'),
      $this->name.'uniq_key' => Configuration::get($this->name.'uniq_key'),
      $this->name.'envio_email' => Configuration::get($this->name.'envio_email'),
      $this->name.'transp_include'      => Configuration::get($this->name.'transp_include'),
      $this->name.'transp_method'      => Configuration::get($this->name.'transp_method'),
      $this->name.'transp_rs'      => Configuration::get($this->name.'transp_rs'),
      $this->name.'transp_cnpj'    => Configuration::get($this->name.'transp_cnpj'),
      $this->name.'transp_ie'      => Configuration::get($this->name.'transp_ie'),
      $this->name.'transp_address' => Configuration::get($this->name.'transp_address'),
      $this->name.'transp_cep'     => Configuration::get($this->name.'transp_cep'),
      $this->name.'transp_city'    => Configuration::get($this->name.'transp_city'),
      $this->name.'transp_uf'      => Configuration::get($this->name.'transp_uf'),
      $this->name.'carriers'       => Configuration::get($this->name.'carriers'),


      $this->name.'docscolumn_cpf'   => Configuration::get($this->name.'docscolumn_cpf'),
      $this->name.'docscolumn_cnpj'  => Configuration::get($this->name.'docscolumn_cnpj'),
      $this->name.'docscolumn_rs'    => Configuration::get($this->name.'docscolumn_rs'),
      $this->name.'docscolumn_ie'    => Configuration::get($this->name.'docscolumn_ie'),
      $this->name.'docstable_cpf'    => Configuration::get($this->name.'docstable_cpf'),
      $this->name.'docstable_cnpj'   => Configuration::get($this->name.'docstable_cnpj'),
      $this->name.'docstable_rs'     => Configuration::get($this->name.'docstable_rs'),
      $this->name.'docstable_ie'     => Configuration::get($this->name.'docstable_ie'),
    );

  }

  /**
  * Get default configs values
  */
  function getConfigInitValues(){

   $arr =  array(
      $this->name.'consumer_key' => '',
      $this->name.'consumer_secret' => '',
      $this->name.'access_token' => '',
      $this->name.'access_token_secret' => '',
      $this->name.'sefaz_env' => '2',
      $this->name.'automatic_emit' => 'off',
      $this->name.'auto_emit_status_ids' => '3,4,5',
      $this->name.'operation_type' => '',
      $this->name.'tax_class' => '',
      $this->name.'ean_barcode' => '',
      $this->name.'gtin_tributavel' => '',
      $this->name.'ncm_code' => '',
      $this->name.'cest_code' => '',
      $this->name.'cnpj_fabricante' => '',
      $this->name.'ind_escala' => '',
      $this->name.'product_source' => '0',
      $this->name.'intermediador' => '0',
      $this->name.'intermediador_cnpj' => '',
      $this->name.'intermediador_id' => '',
      $this->name.'person_type_fields' => 'on',
      $this->name.'mask_fields' => 'off',
      $this->name.'enable_person_type' => 'off',
      $this->name.'fill_address' => 'off',
      $this->name.'fisco_inf' => '',
      $this->name.'cons_inf' => '',
      $this->name.'tipo_cliente_field' => 'document_type',
      $this->name.'cpf_field' => 'cpf',
      $this->name.'cnpj_field' => 'cnpj',
      $this->name.'razao_social_field' => 'razao_social',
      $this->name.'cnpj_ie_field' => 'cnpj_ie',
      $this->name.'cpf_cnpj_status' => 'off',
      $this->name.'numero_compl_status' => 'off',
      $this->name.'numero_field' => 'address_number',
      $this->name.'bairro_field' => 'bairro',
      $this->name.'complemento_field' => '',
      $this->name.'valor_pessoa_fisica' => '',
      $this->name.'valor_pessoa_juridica' => '',
      $this->name.'bairro_status' => 'off',
      $this->name.'envio_email' => 'on',
      $this->name.'transp_include'      => 'off',
      $this->name.'transp_method'      => '',
      $this->name.'transp_rs'      => '',
      $this->name.'transp_cnpj'    => '',
      $this->name.'transp_ie'      => '',
      $this->name.'transp_address' => '',
      $this->name.'transp_cep'     => '',
      $this->name.'transp_city'    => '',
      $this->name.'transp_uf'      => '',
      $this->name.'carriers'       => '',
      $this->name.'docscolumn_cpf' => '',
      $this->name.'docscolumn_cnpj'  => '',
      $this->name.'docscolumn_rs'    => '',
      $this->name.'docscolumn_ie'    => '',
      $this->name.'docstable_cpf'    => '',
      $this->name.'docstable_cnpj'   => '',
      $this->name.'docstable_rs'     => '',
      $this->name.'docstable_ie'     => ''

    );

    $payment_methods = $this->get_payment_methods_options();
    foreach($payment_methods as $method){
      $arr[$this->name.'payment_'.$method['value']] = '';
      $arr[$this->name.'payment_'.$method['value'].'_desc'] = '';
    }

    return $arr;

  }

  // Retrocompatibility 1.5
  private function initContext(){

    if (class_exists('Context')){
      $this->context = Context::getContext();
    }else{
      global $smarty, $cookie;
      $this->context = new StdClass();
      $this->context->smarty = $smarty;
      $this->context->cookie = $cookie;
    }
  }

  public function hookBackOfficeHeader($params = null){
    $this->ensureAjaxController();


    $this->listen_notification();

    if (_PS_VERSION_ < '1.7.7') {
      $this->processBulkEmitirNfe();
    }

    $this->updateNfe();

    if(_MAIN_PS_VERSION_ == '1.6' || _MAIN_PS_VERSION_ == '1.7'){
      $controller_name = $this->context->controller->controller_name;
      if(method_exists($this->context->controller, 'addJquery')){
        $this->context->controller->addJquery();
      }
      if($controller_name == 'AdminCustomers' || $controller_name == 'AdminModules'){
        $this->context->controller->addJS($this->_path.'/js/jquery.mask.min.js', 'all');
      }

      $cpf_cnpj_status = Configuration::get($this->name.'cpf_cnpj_status');
      $numero_enabled = Configuration::get($this->name.'numero_compl_status');

      // Same ACL as the AJAX resources: customer documents and address data are personal data.
      $edit_id_customer = Tools::getValue('id_customer') ?: Tools::getValue('customerId');
      if($edit_id_customer && $this->employeeCan('AdminCustomers', 'view')){
        Media::addJsDef(array('id_customer_wmbr' => $edit_id_customer));

        $doc = Db::getInstance()->getRow('SELECT nfe_document_number, nfe_document_type, nfe_razao_social, nfe_pj_ie FROM '._DB_PREFIX_.'customer WHERE id_customer = '.(int)$edit_id_customer);
        if($doc && !empty($doc['nfe_document_number'])){
          Media::addJsDef(array('customer_doc_wmbr' => array(
            'document_number' => $doc['nfe_document_number'],
            'document_type'   => $doc['nfe_document_type'],
            'razao_social'    => $doc['nfe_razao_social'],
            'ie'              => $doc['nfe_pj_ie'],
          )));
        }
      }

      $edit_id_address = Tools::getValue('id_address') ?: Tools::getValue('addressId');
      if($edit_id_address && $this->employeeCan('AdminAddresses', 'view')){
        Media::addJsDef(array('id_address_wmbr' => $edit_id_address));

        $addr = Db::getInstance()->getRow('SELECT address_number, bairro FROM '._DB_PREFIX_.'address WHERE id_address = '.(int)$edit_id_address);
        if($addr){
          Media::addJsDef(array('address_doc_wmbr' => array(
            'address_number' => $addr['address_number'],
            'bairro'         => $addr['bairro'],
          )));
        }
      }


      Media::addJsDef(array('tipo_pessoa_enabled' => $cpf_cnpj_status));
      Media::addJsDef(array('numero_enabled' => $numero_enabled));

      Media::addJsDef(array('wmbr_admin_ajax_url' => $this->context->link->getAdminLink('AdminWebmaniaNfeAjax') . '&ajax=1'));
      Media::addJsDef(array('wmbr_module_path' => __PS_BASE_URI__.'modules/'.$this->name.'/'));

      Media::addJsDef(array('sec_token' => Tools::getAdminTokenLite('AdminWebmaniaNfeAjax')));
      $this->context->controller->addJS($this->_path.'/js/emission-recovery.js?v='.$this->version, 'all');

      if (_PS_VERSION_ >= '1.7.7') {
        $this->context->controller->addJS($this->_path.'/js/scripts_bo.1.7.7.js?v='.$this->version, 'all');
      }
      else {
        $this->context->controller->addJS($this->_path.'/js/scripts_bo.1.6-1.7.js?v='.$this->version, 'all');
      }

      $this->context->controller->addCSS($this->_path.'/views/css/style.css?v='.$this->version, 'all');
    }

    //Support to PS 1.5
    if(_MAIN_PS_VERSION_ == '1.5'){
      $this->rearrangeStates();
      $controllerName = $this->context->controller->controller_name;
      if(($controllerName == 'AdminOrders' && !Tools::getValue('id_order')) || $controllerName == 'AdminCustomers' || $controllerName == 'AdminAddresses'){
        $this->context->controller->addJS($this->_path.'/js/scripts_bo.1.5.js', 'all');
        $this->context->controller->addJS($this->_path.'/js/jquery.mask.min.js', 'all');
        $this->context->controller->addCSS($this->_path.'/views/css/style.css', 'all');
      }
    }

    return true;
  }

  //Verificar
  public function hookDisplayBackOfficeCategory(){

    $this->context->controller->addJS($this->_path.'/js/script_categories.1.7.js', 'all');

  }

  public function hookDisplayBackOfficeHeader($params = null){

    $this->hookBackOfficeHeader($params);

    if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'){
      return;
    }

    $this->displayMessageCertificado();

  }

  public function hookDisplayInvoice($params = null) {

    $order_id = (int) $params['id_order'];
    $order = new Order($order_id);

    $this->display_order_nfe_table($order_id);

    if($this->canSaveTransportInfo()){
      $this->save_order_transporte_info($order_id);
    }

  }

  public function hookDisplayAdminOrderTabShip($order = null, $products = null, $customer = null){

    $include_shipping_info = Configuration::get($this->name.'transp_include');
    if($include_shipping_info != 'on') return false;

    return '<li><a href="#nfe-shipping-info">Transporte (NF-e)</a></li>';

  }


  public function hookDisplayAdminOrderContentShip($order = null, $products = null, $customer = null){

    $include_shipping_info = Configuration::get($this->name.'transp_include');
    if($include_shipping_info != 'on') return false;

    ob_start();
    require_once('views/templates/order_nfe_shipping_info.php');

    $html = ob_get_clean();

    return $html;

  }

  public function hookDisplayAdminOrderTabLink($params){

    $include_shipping_info = Configuration::get($this->name.'transp_include');
    if($include_shipping_info != 'on') return false;

    return '<li><a href="#nfe-shipping-info">Transporte (NF-e)</a></li>';

  }


  public function hookDisplayAdminOrderTabContent($params){

    $include_shipping_info = Configuration::get($this->name.'transp_include');
    if($include_shipping_info != 'on') return false;

    ob_start();
    require_once('views/templates/order_nfe_shipping_info.php');

    $html = ob_get_clean();

    return $html;

  }

  


  public function display_order_nfe_table($order_id){
    $order_id = (int) $order_id;
    echo $this->displayEmissionRecovery($order_id);
    $order_id = (int) $order_id;
    $order = new Order($order_id);
    $customer = $order->getCustomer();

    $nfe_info = WebmaniaNFeSecurity::invoices(Db::getInstance()->getValue("SELECT nfe_info FROM "._DB_PREFIX_."orders WHERE id_order = $order_id" ));

    $url  = 'index.php?controller=AdminOrders&id_order='.$order_id;
    $url .= '&vieworder&token='.Tools::getAdminTokenLite('AdminOrders');

    if(!$nfe_info){

      echo '<div class="panel kpi-container" id="nfe_emitidas">
      <div class="row">
      <h3 style="padding-left:15px;">Notas emitidas para este pedido</h3>
      <p>Nenhuma nota emitida</p>
      </div>
      </div>';

    }else{

      if(_MAIN_PS_VERSION_ == '1.5'){
        echo '<script>jQuery(document).ready(function(){$("#nfe_emitidas").parent("div").attr("style", "");})</script>';
        echo '<style>
        #nfe_emitidas table{
          border:1px solid #000;
          border-collapse:collapse;
        }

        #nfe_emitidas tbody tr{
          border:1px solid #000;
        }
        </style>';
      }

      $customer_docs = $this->getCustomerDocs($customer, $order);
      $document = '';

      if ($customer_docs['cnpj'] && $customer_docs['cnpj'] != $customer_docs['cpf'] || $this->is_cnpj($customer_docs['cpf'])){

        $document = $this->cnpj($customer_docs['cnpj']);
        if( !$document && isset($customer->cnpj) ) $document = $customer->cnpj;

      } else {

        $document = $this->cpf($customer_docs['cpf']);
        if( !$document && isset($customer->cpf) ) $document = $customer->cpf;

      }

      //Set danfe simples/etiqueta url if it doesn't exist
      foreach ($nfe_info as $key => $nfe) {
        if (empty($nfe['url_danfe_simplificada'])) {
          $nfe_info[$key]['url_danfe_simplificada'] = str_replace('/danfe/', '/danfe/simples/', $nfe['url_danfe']);
        }
        if (empty($nfe['url_danfe_etiqueta'])) {
          $nfe_info[$key]['url_danfe_etiqueta'] = str_replace('/danfe/', '/danfe/etiqueta/', $nfe['url_danfe']);
        }
        $password = !empty($nfe['documento']) ? $nfe['documento'] : $document;
        if(!empty($password)){
          $tokens = [
            "danfe" => $this->createSecureTokenDFe($password, $nfe['uuid']),
            "danfe_simples" => $this->createSecureTokenDFe($password, $nfe['uuid']),
            "danfe_etiqueta" => $this->createSecureTokenDFe($password, $nfe['uuid']),
            "xml" => $this->createSecureTokenDFe($password, $nfe['uuid']),
          ];

          $nfe_info[$key]['url_danfe'] .= '?token=' . $tokens['danfe'];
          $nfe_info[$key]['url_danfe_simplificada'] .= '?token=' . $tokens['danfe_simples'];
          $nfe_info[$key]['url_danfe_etiqueta'] .= '?token=' . $tokens['danfe_etiqueta'];
          $nfe_info[$key]['url_xml'] .= '?token=' . $tokens['xml'];
        }

      }

      $this->context->smarty->assign(array(
        'nfe_info_arr' => $nfe_info,
        'url' => $url,
      ));

      echo  $this->display(__FILE__, 'nfe_info_table_1.6-1.7.tpl');

      $customer_id = $order->id_customer;
      $delivery_address_id = $order->id_address_delivery;
      $invoice_address_id = $order->id_address_invoice;

      $address_custom = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'address WHERE id_address = ' . (int)$delivery_address_id);
      if (!is_array($address_custom)) { $address_custom = array(); }
      $state = new State(isset($address_custom['id_state']) ? (int) $address_custom['id_state'] : 0);
      $this->context->smarty->assign(array(
        'user_address_info' => array_merge(array_fill_keys(array('firstname', 'lastname', 'address1', 'address_number', 'address2', 'postcode', 'city', 'phone'), ''), array_map('strval', $address_custom)),
        'state' => (string) $state->iso_code,
      ));

      echo $this->display(__FILE__, 'user_address_info.tpl');

    }

  }

  public function display_order_transporte_info($order_id){

    $transporte_info = $this->get_order_transporte_info($order_id);

    $this->context->smarty->assign(array(
      'transporte_info' => is_array($transporte_info) ? array_map('strval', $transporte_info) : array(),
    ));

    echo $this->display(__FILE__, 'nfe_transporte_info.tpl');

  }

  public function save_order_transporte_info($order_id){

    $order_id = (int) $order_id;

    if(!Db::getInstance()->update('orders', array(
      'nfe_modalidade_frete'=> pSQL(Tools::getValue('nfe_modalidade_frete')),
      'nfe_volumes' => pSQL(Tools::getValue('nfe_volumes')),
      'nfe_especie' => pSQL(Tools::getValue('nfe_especie')),
      'nfe_peso_bruto' => pSQL(Tools::getValue('nfe_peso_bruto')),
      'nfe_peso_liquido' => pSQL(Tools::getValue('nfe_peso_liquido')),
      'nfe_valor_seguro' => pSQL(Tools::getValue('nfe_valor_seguro')),
    ),'id_order = ' .$order_id )){
      $this->_errors[] = 'Error: '.Db::getInstance()->getMsgError();
    }

  }

  public function get_order_transporte_info( $order_id ) {

    $order_id = (int) $order_id;

    $transporte_info = Db::getInstance()->getRow("SELECT nfe_modalidade_frete, nfe_volumes, nfe_especie, nfe_peso_bruto, nfe_peso_liquido, nfe_valor_seguro FROM "._DB_PREFIX_."orders WHERE id_order = $order_id");

    return $transporte_info;

  }

  public function isDocumentsEnabled() {

    if(Configuration::get($this->name.'cpf_cnpj_status') == 'on'){
      return true;
    }

    return false;

  }

  public function setJSCustomFieldsStatus() {

    $fields = array('cpf_cnpj_status', 'numero_compl_status', 'bairro_status', 'mask_fields', 'fill_address');

    foreach($fields as $field){
      Media::addJsDef(array($field => Configuration::get($this->name.$field)));
    }

  }

  public function hookDisplayHeader($params = null){

    $this->listen_notification();

    if(_MAIN_PS_VERSION_ == '1.5'){
      $controller = get_class($this->context->controller);
      if($controller == 'AddressController' || $controller == 'OrderController' || $controller == 'AuthController' || $controller == 'WebmaniaBRNFeView15ModuleFrontController'){
        if(Configuration::get($this->name.'mask_fields') == 'on'){
          echo '<script>var mask_fields = "on";</script>';
        }

        if(Configuration::get($this->name.'fill_address') == 'on'){
          echo '<script>var fill_address = "on";</script>';
        }

        $this->context->controller->addJS($this->_path.'/js/scripts_fo.1.5.js', 'all');
      }
    }

    if(_MAIN_PS_VERSION_ == '1.6' || _MAIN_PS_VERSION_ == '1.7' || $this->isPrestaShop8Plus()){

      if(Configuration::get($this->name.'mask_fields') == 'on'){
        Media::addJsDef(array('mask_doc_fields' => true));
      }

      //if(Configuration::get($this->name.'fill_address') == 'on'){
        //Media::addJsDef(array('fill_address' => true));
      //}


      $this->setJSCustomFieldsStatus();
      Media::addJsDef(array('sec_token' => Tools::getToken(false)));

      $this->context->controller->addJS($this->_path.'/js/scripts_fo.'._MAIN_PS_VERSION_.'.js', 'all');


    }

    $this->context->controller->addJS($this->_path.'/js/correios.min.js', 'all');
    if(_MAIN_PS_VERSION_ == '1.5' || _MAIN_PS_VERSION_ == '1.6' || _MAIN_PS_VERSION_ == '1.7'){
      $this->context->controller->addJS($this->_path.'/js/jquery.mask.min.js', 'all');
    }
    $this->context->controller->addCSS($this->_path.'/views/css/style.css', 'all');

  }

  /*
   * Add custom column to admin orders page 1.6-1.7
   */
  public function hookActionAdminOrdersListingFieldsModifier($params = null){

    $params['fields'] = array_slice($params['fields'], 0, 2, true) +
      array('nfe_issued' => array(
        'title' => 'Status NF-e',
        'class' => 'extended-width-nfe',
        'align' => 'text-center',
        'search' => '',
        'callback' => 'isNfeIssued',
        'callback_object' => $this)) + array_slice($params['fields'], 2, count($params['fields']) - 1);

    }

  public function hookActionPaymentConfirmation($params = null){

    if (isset($params['id_order'])) {
      $this->emitirNfeAutomatico($params['id_order']);
    }
    return true;

  }

  public function hookDisplayAdminProductsExtra($params = null){

    $product_id = (int)Tools::getValue('id_product');
    if(!$product_id && isset($params['id_product'])) $product_id = (int) $params['id_product'];

    // Templates escape these values: always assign strings (NULL columns, product not loaded).
    $columns = array('tax_class' => 'nfe_tax_class', 'ean_bar_code' => 'nfe_ean_bar_code', 'gtin_tributavel' => 'nfe_gtin_tributavel',
      'ncm_code' => 'nfe_ncm_code', 'cest_code' => 'nfe_cest_code', 'cnpj_fabricante' => 'nfe_cnpj_fabricante',
      'ind_escala' => 'nfe_ind_escala', 'product_source' => 'nfe_product_source', 'ignorar_nfe' => 'nfe_ignorar_nfe');
    $assign = array_fill_keys(array_keys($columns), '');

    if(Validate::isLoadedObject($product = new Product($product_id))){

      $values = $this->getProductNfeValues( $product_id );
      if (is_array($values) && isset($values[0]) && is_array($values[0])) {
        foreach ($columns as $variable => $column) {
          $assign[$variable] = isset($values[0][$column]) ? (string) $values[0][$column] : '';
        }
      }

    }

    $this->context->smarty->assign($assign);

    if(_MAIN_PS_VERSION_ == '1.5'){
      return $this->display(__FILE__, 'nfe_product_tab.1.5.tpl');
    }else if(_MAIN_PS_VERSION_ == '1.7'){
      return $this->display(__FILE__, 'nfe_product_tab.1.7.tpl');
    }else{
      return $this->display(__FILE__, 'nfe_product_tab.1.6.tpl');
    }

  }

  public function hookActionProductUpdate($params = null){
    $id_product = (int) Tools::getValue('id_product');
    if (!$id_product) { return; }
    $fields = array('nfe_tax_class', 'nfe_ean_bar_code', 'nfe_gtin_tributavel', 'nfe_ncm_code',
      'nfe_cest_code', 'nfe_cnpj_fabricante', 'nfe_ind_escala', 'nfe_product_source', 'nfe_ignorar_nfe');
    $update = array();
    foreach ($fields as $field) {
      if (!Tools::getIsset($field)) { continue; }
      $value = Tools::getValue($field);
      if (is_scalar($value)) { $update[$field] = pSQL((string) $value); }
    }
    if ($update && !Db::getInstance()->update('product', $update, 'id_product = '.$id_product)) {
      $this->_errors[] = 'Error: '.Db::getInstance()->getMsgError();
    }
  }

  public function createCustomerDocument( $customer_id, $customer_data ) {

    // Registrations without the document fields (disabled option, other modules) must not warn.
    $customer_data = is_array($customer_data) ? $customer_data : array();
    foreach (array('document_type', 'cpf', 'cnpj', 'razao_social', 'cnpj_ie') as $field) {
      $customer_data[$field] = isset($customer_data[$field]) && is_scalar($customer_data[$field]) ? (string) $customer_data[$field] : '';
    }
    $DB_data = array();

    if($customer_data['document_type'] == 'cpf'){
      $DB_data = array(
        'nfe_document_type'   => pSQL($customer_data['document_type']),
        'nfe_document_number' => pSQL(preg_replace("/[^0-9]/", "", $customer_data['cpf'])),
      );
    }elseif($customer_data['document_type'] == 'cnpj'){
      $DB_data = array(
        'nfe_document_type'   => pSQL($customer_data['document_type']),
        'nfe_document_number' => pSQL(WebmaniaNFeSecurity::normalizeDocument($customer_data['cnpj'])),
        'nfe_razao_social'    => pSQL($customer_data['razao_social']),
        'nfe_pj_ie'           => pSQL($customer_data['cnpj_ie']),
      );
    }

    if(!$DB_data){
      return;
    }

    if(!Db::getInstance()->update('customer', $DB_data, 'id_customer = ' .(int)$customer_id )){
      $this->_errors[] = 'Error: '.Db::getInstance()->getMsgError();
    }

  }

  public function hookActionCustomerAccountAdd($params = null){

    $customer_data = array();
    $customer_id = isset($params['newCustomer']->id) ? (int)$params['newCustomer']->id : 0;
    if (!$customer_id) { return; }

    if(_MAIN_PS_VERSION_ == '1.6'){
      // createCustomerDocument() normalizes missing or malformed fields.
      $customer_data = isset($params['_POST']) && is_array($params['_POST']) ? $params['_POST'] : array();
    }else if(_MAIN_PS_VERSION_ == '1.7'){
      $customer_data = array(
        'document_type' => Tools::getValue('document_type'),
        'cpf'           => Tools::getValue('cpf'),
        'cnpj'          => Tools::getValue('cnpj'),
        'razao_social'  => Tools::getValue('razao_social'),
        'cnpj_ie'       => Tools::getValue('cnpj_ie'),
      );
    }


    $this->createCustomerDocument($customer_id, $customer_data);

  }


  //ADD CPF/CNPJ IN Registration Form
  public function hookcreateAccountForm(){

    $cpf_cnpj_status = Configuration::get($this->name.'cpf_cnpj_status');


      if(_MAIN_PS_VERSION_ == '1.5'){
        return $this->display(__FILE__, 'document_types.1.5.tpl');

        if(Configuration::get($this->name.'mask_fields') == 'on'){
          $var = 'on';
        }else{
          $var = 'off';
        }

        $this->smarty->assign('custom_var', $var);
      }

      if(_MAIN_PS_VERSION_ == '1.6' || _MAIN_PS_VERSION_ == '1.7' || $this->isPrestaShop8Plus()){

        if(Configuration::get($this->name.'mask_fields') == 'on'){
          $var = 'on';
        }else{
          $var = 'off';
        }

        $this->smarty->assign('custom_var', $var);

        if($cpf_cnpj_status == 'on'){
          return $this->display(__FILE__, 'document_types.1.6.tpl');
        }

      }

  }

  /* 1.7 Only */
  public function hookValidateCustomerFormFields($params = null){

    if(!$this->isDocumentsEnabled()) return $params;

    $fields = $params['fields'];
    $tipo_pessoa = $fields[0]->getValue();
    $array_set = array();

    if($tipo_pessoa == 'cpf'){
      $array_set = array('cpf');
    }else if($tipo_pessoa == 'cnpj'){
      $array_set = array('cnpj', 'razao_social');
    }

    foreach($fields as $key => $field){

      if(in_array($field->getName(), $array_set)){
        $fields[$key]->setRequired(true);
      }

    }


    $params['fields'] = $fields;

    return $params;

  }

  public function getCustomerNfeInfo( $id_customer ){

    $result = Db::getInstance()->getRow('SELECT nfe_document_number, nfe_document_type, nfe_razao_social, nfe_pj_ie FROM '._DB_PREFIX_.'customer WHERE id_customer = ' . (int)$id_customer);

    return $result;

  }


  /* 1.7 Only */
  public function hookAdditionalCustomerFormFields($params = null){
    if(!$this->isDocumentsEnabled()) return array();

    $id_customer = $this->context->customer->id;
    $nfe_info = $this->getCustomerNfeInfo($id_customer);

    $values = array(
      'cpf'      => '',
      'cnpj'     => '',
      'tipo'     => 'cpf',
      'ie'       => '',
      'r_social' => '',
    );

    if($nfe_info){
      $values['tipo'] = $nfe_info['nfe_document_type'];

      if($values['tipo'] == 'cpf'){
        $values['cpf'] = $nfe_info['nfe_document_number'];
      }else if($values['tipo'] == 'cnpj'){
        $values['cnpj'] = $nfe_info['nfe_document_number'];
        $values['r_social'] = $nfe_info['nfe_razao_social'];
        $values['ie'] = $nfe_info['nfe_pj_ie'];
      }


    }

    $fields = array();

    $map = array(

      'tipo_pessoa' => array(
        'name'     => 'document_type',
        'type'     => 'radio-buttons',
        'required' => true,
        'label'    => 'Tipo de Pessoa',
        'value' => $values['tipo'],
        'values'   => array(
          'cpf'  => 'Pessoa Física',
          'cnpj' => 'Pessoa Jurídica',
        )
      ),

      'cpf' => array(
        'name'     => 'cpf',
        'type'     => 'text',
        'required' => false,
        'label'    => 'CPF',
        'value'    => $values['cpf'],
      ),

      'cnpj' => array(
        'name'     => 'cnpj',
        'type'     => 'text',
        'required' => false,
        'label'    => 'CNPJ',
        'value'    => $values['cnpj'],
      ),

      'razao_social' => array(
        'name'     => 'razao_social',
        'type'     => 'text',
        'required' => false,
        'label'    => 'Razão Social',
        'value'    => $values['r_social'],
      ),

      'ie' => array(
        'name'     => 'cnpj_ie',
        'type'     => 'text',
        'required' => false,
        'label'    => 'Inscrição Estadual',
        'value'    => $values['ie'],
      ),

    );

    foreach($map as $field){

      $object = new FormField();
      $object->setName($field['name']);
      $object->setType($field['type']);
      $object->setRequired($field['required']);
      $object->setLabel($field['label']);

      if(isset($field['value'])){
        $object->setValue($field['value']);
      }

      if(isset($field['values'])){
        $object->setAvailableValues($field['values']);
      }
      $fields[] = $object;
    }


    return $fields;
  }

  public function hookDisplayCustomerAccountForm(){

    if(_MAIN_PS_VERSION_ == '1.7') return false;


      if(_MAIN_PS_VERSION_ == '1.6'){

        if(Configuration::get($this->name.'mask_fields') == 'on'){
          $var = 'on';
        }else{
          $var = 'off';
        }

        $this->smarty->assign('custom_var', $var);

        if($this->isDocumentsEnabled()){
          if(_MAIN_PS_VERSION_ == '1.6'){
            return $this->display(__FILE__, 'document_types.1.6.tpl');
          }else if(_MAIN_PS_VERSION_ == '1.7'){
            echo $this->display(__FILE__, 'document_types.1.7.tpl');
          }

        }

      }

  }

  //ADD "EDIT CPF/CNPJ" on My Account Page
  public function hookDisplayCustomerAccount(){

      $cpf_cnpj_status = Configuration::get($this->name.'cpf_cnpj_status');

      if(_MAIN_PS_VERSION_ == '1.5'){
        $redirect_url = $this->context->link->getModuleLink('webmaniabrnfe','view15');
        $this->smarty->assign('redirect_url', $redirect_url);
        return $this->display(__FILE__, 'account_list_item.1.5.tpl');
      }

      if(_MAIN_PS_VERSION_ == '1.6'){

        if($cpf_cnpj_status == 'on'){
          $redirect_url = $this->context->link->getModuleLink('webmaniabrnfe','view16');
          $this->smarty->assign('redirect_url', $redirect_url);
          return $this->display(__FILE__, 'account_list_item.1.6.tpl');
        }

      }

  }

  public function hookCustomerAccount(){

      $redirect_url = __PS_BASE_URI__.'modules/'.$this->name.'/controllers/front/editar_documentos.php';
      $this->context->smarty->assign('redirect_url', $redirect_url);

      return $this->display(__FILE__, '/views/templates/hook/account_list_item.tpl');

  }

  public function hookCreateAccount($params = null){

    $customer_data = $params['_POST'];
    $customer_id = (int)$params['newCustomer']->id;

    if($customer_data['document_type'] == 'cpf'){
      $document_type = pSQL($customer_data['document_type']);
      $document_number = pSQL(preg_replace("/[^0-9]/","",$customer_data['cpf']));

      $query = "UPDATE ". _DB_PREFIX_ ."customer SET nfe_document_type = '".$document_type."', nfe_document_number = '".$document_number."' WHERE id_customer = " . $customer_id;

      $result = Db::getInstance()->executeS($query);

    }elseif($customer_data['document_type'] == 'cnpj'){
      $document_type = pSQL($customer_data['document_type']);
      $document_number = pSQL(WebmaniaNFeSecurity::normalizeDocument($customer_data['cnpj']));
      $razao_social = pSQL($customer_data['razao_social']);
      $cnpj_ie = pSQL($customer_data['cnpj_ie']);

      $query = "UPDATE ". _DB_PREFIX_ ."customer SET nfe_document_type = '".$document_type."', nfe_document_number = '".$document_number."', nfe_razao_social = '".$razao_social."', nfe_pj_ie = '".$cnpj_ie."' WHERE id_customer = " . $customer_id;

      $result = Db::getInstance()->executeS($query);
    }
    return true;

  }

  public function hookActionCategoryUpdate($params = null) {

    $category_id = (int) Tools::getValue('id_category');
    $values = Tools::getAllValues();
    $ncm = null;

    foreach($values as $key => $value){

      if( strpos((string) $key, 'nfe_category_ncm') !== false ){
        $ncm = is_scalar($value) ? pSQL((string) $value) : '';
        break;
      }

    }

    // Category::update() is also called by status toggles and other modules without the NCM field.
    if( $category_id && $ncm !== null ){

      $result = Db::getInstance()->update( $this->categoryNcmTable(), array('nfe_category_ncm' => $ncm), 'id_category = ' .$category_id );

      if(!$result){
        $this->_errors[] = Db::getInstance()->getMsgError();
      }

    }


  }

  public function updateAddressInfo( $address_id ){

    $number_status = Configuration::get($this->name.'numero_compl_status');
    $update_values = array();

    if($number_status == 'on'){
      $number_field = 'address_number';
    }else{
      $number_field = (string) Configuration::get($this->name.'numero_field');
    }

    // Only forms that submit the number may change it (webservice/import/other modules do not).
    if($number_field !== '' && Tools::getIsset($number_field)){
      $number = Tools::getValue($number_field);
      $update_values['address_number'] = is_scalar($number) ? pSQL((string) $number) : '';
    }

    if(_MAIN_PS_VERSION_ == '1.7'){

      $bairro_status = Configuration::get($this->name.'bairro_status');
      if($bairro_status == 'on'){
        $bairro = Tools::getValue('bairro');
      }else{
        $bairro = Tools::getValue(Configuration::get($this->name.'bairro_field'));
      }

      if($bairro && is_scalar($bairro)){
        $update_values['bairro'] = pSQL((string) $bairro);
      }

    }


    if(!empty($update_values)){
      if(!Db::getInstance()->update('address', $update_values, 'id_address = ' .(int)$address_id)){
        $this->_errors[] = Db::getInstance()->getMsgError();
      }
    }

  }

  public function hookActionObjectAddressUpdateAfter($params = null) {

    $address_id = $params['object']->id;
    $this->updateAddressInfo($address_id);

  }

  public function hookActionObjectAddressAddAfter($params = null) {

    $address_id = $params['object']->id;
    $this->updateAddressInfo($address_id);

  }

  public function hookActionObjectUpdateAfter($params = null){

    if( is_a($params['object'], 'Address')){
      $address_id = $params['object']->id;
      $this->updateAddressInfo($address_id);
    }

  }



  public function updateCustomerDocument( $customer_id ) {

    $cpf_cnpj_status = Configuration::get($this->name.'cpf_cnpj_status');
    $update_values = array();

    if ($cpf_cnpj_status == 'on'){
      $fields = array(
        'cpf'          => 'cpf',
        'cnpj'         => 'cnpj',
        'razao_social' => 'razao_social',
        'ie'           => 'cnpj_ie'
      );
    } else {
      $fields = array(
        'cpf'          => Configuration::get($this->name.'cpf_field'),
        'cnpj'         => Configuration::get($this->name.'cnpj_field'),
        'razao_social' => Configuration::get($this->name.'razao_social_field'),
        'ie'           => Configuration::get($this->name.'cnpj_ie_field'),
      );
    }

    // Read each submitted value once; arrays from crafted requests are ignored.
    $values = array();
    foreach ($fields as $key => $name) {
      $value = $name ? Tools::getValue($name) : false;
      $values[$key] = is_scalar($value) ? (string) $value : '';
    }

    // Set user
    $is_cnpj = WebmaniaNFeSecurity::validCnpj($values['cnpj']);

    if ($is_cnpj){

      if ($values['cnpj']){

        $update_values['nfe_document_type'] = 'cnpj';
        $update_values['nfe_document_number'] = pSQL(WebmaniaNFeSecurity::normalizeDocument($values['cnpj']));

        if ($values['razao_social']) $update_values['nfe_razao_social'] = pSQL($values['razao_social']);
        else $update_values['nfe_razao_social'] = '';

        if ($values['ie']) $update_values['nfe_pj_ie'] = pSQL($values['ie']);
        else $update_values['nfe_pj_ie'] = '';

      }

    } else {

      if ($values['cpf']){

        $update_values['nfe_document_type'] = 'cpf';
        $update_values['nfe_document_number'] = preg_replace("/[^0-9]/","", $values['cpf']);
        $update_values['nfe_razao_social'] = '';
        $update_values['nfe_pj_ie'] = '';

      }

    }

    /***********

    One Page Checkout Values

    $fields_opc = json_decode(Tools::getValue('fields_opc')), true);

    $tipo_cliente_field = Configuration::get($this->name.'tipo_cliente_field')
    $cpf_field_name = Configuration::get($this->name.'cpf_field');
    $cnpj_field_name = Configuration::get($this->name.'cnpj_field');
    $razao_social_field = Configuration::get($this->name.'razao_social_field');
    $ie_field = Configuration::get($this->name.'cnpj_ie_field');


    foreach($fields_opc as $opc_field){
      switch($opc_field['name']){
        case $tipo_cliente_field:
        $update_values['nfe_document_type'] = $opc_field['value'];
        break;
        case $cpf_field_name:
        case $cnpj_field:
        $update_values['nfe_document_number'] = $opc_field['value'];
        break;
        case $razao_socal_field:
        $update_values['nfe_razao_social'] = $opc_field['value'];
        break;
        case $ie_field:
        $update_values['nfe_pj_ie'] = $opc_field['value'];
        break;
      }
    }

    *************/

    if(!empty($update_values)){

      if(!Db::getInstance()->update('customer', $update_values, 'id_customer="'.(int)$customer_id.'"')){
        $this->_errors[] = Db::getInstance()->getMsgError();
      }

    }

  }


  public function hookActionObjectCustomerUpdateAfter($params = null){

    $customer_id = $params['object']->id;
    $this->updateCustomerDocument($customer_id);

  }

  public function hookActionObjectCustomerAddAfter($params = null){

    $customer_id = $params['object']->id;
    $this->updateCustomerDocument($customer_id);

  }

  /**
   * Hook for PrestaShop 8+ front controller set media
   */
  public function hookActionFrontControllerSetMedia($params = null) {
    if ($this->isPrestaShop8Plus()) {
      // CSS do módulo no FO (PS 8/9)
      $this->context->controller->addCSS($this->_path.'/views/css/style.css', 'all');
      // Evita duplicidade com hookDisplayHeader em versões antigas
      if(Configuration::get($this->name.'mask_fields') == 'on'){
        $this->context->controller->addJS($this->_path.'/js/jquery.mask.min.js', 'all');
      }
      // Em PS 8/9, reutilizamos o script de 1.7 (compatível)
      $this->context->controller->addJS($this->_path.'/js/scripts_fo.1.7.js', 'all');
    }
  }

  /**
   * Hook for PrestaShop 8+ admin controller set media
   */
  public function hookActionAdminControllerSetMedia($params = null) {
    // scripts_bo, jquery.mask and the CPF/CNPJ JsDefs are loaded by
    // hookBackOfficeHeader, which also runs on PS 8/9; loading them here as
    // well would register the events and inject the fields twice.
  }

  /**
   * Hook for PrestaShop 8+ admin order main display
   */
  public function hookDisplayAdminOrderMain($params) {
    if ($this->isPrestaShop8Plus()) {
      return $this->hookDisplayAdminOrderTop($params);
    }
    return '';
  }

  /**
   * Hook for PrestaShop 9+ admin order main bottom display
   */
  public function hookDisplayAdminOrderMainBottom($params) {
    if ($this->isPrestaShop9Plus()) {
      return $this->hookDisplayAdminOrderTop($params);
    }
    return '';
  }

  /**
   * Hook for PrestaShop 8+ order status update
   */
  public function hookActionOrderStatusPostUpdate($params) {
    // Compatível com 1.7.7+, 8 e 9
    if (!isset($params['id_order']) || !isset($params['newOrderStatus'])) return;
    $order_id = (int)$params['id_order'];
    $new_order_status = $params['newOrderStatus'];
    $automatic = Configuration::get($this->name.'automatic_emit');
    if($automatic == 'on' && isset($new_order_status->id)) {
      $ids_config = trim((string)Configuration::get($this->name.'auto_emit_status_ids'));
      $ids = array();
      if ($ids_config !== '') {
        foreach (explode(',', $ids_config) as $id) {
          $id = (int)trim($id);
          if ($id > 0) { $ids[] = $id; }
        }
      }
      if (empty($ids)) { $ids = array(3,4,5); }
      if (in_array((int)$new_order_status->id, $ids, true)) {
        $this->emitirNfeAutomatico($order_id);
      }
    }
  }

  /**
   * Hook for PrestaShop 9+ validate order after
   */
  public function hookActionValidateOrderAfter($params) {
    // "Emissão automática" is documented as "sempre que um pagamento for confirmado":
    // unpaid orders (boleto, transfer) must not receive an authorized NF-e at checkout.
    if ($this->isPrestaShop9Plus() && isset($params['order']) && is_object($params['order']) &&
        method_exists($params['order'], 'hasBeenPaid') && $params['order']->hasBeenPaid()) {
      $this->emitirNfeAutomatico($params['order']->id);
    }
  }
  /*********************** END HOOKED FUNCTIONS **************************/



  public function checkCURL(){

    if (!function_exists('curl_version')){
        $this->context->controller->warnings[] = '<strong>Prestashop NF-e:</strong> Necessário instalar o comando cURL no servidor, entre em contato com a sua hospedagem ou administrador do servidor.';
    }
  }

  public function checkAuthentication(){

    foreach($this->settings as $setting){
      if(empty($setting)){
        $url  = 'index.php?controller=AdminModules&configure=webmaniabrnfe';
        $url .= '&token='.Tools::getAdminTokenLite('AdminModules');
        $this->context->controller->warnings[] = '<strong>Prestashop NF-e:</strong> Informe as credenciais de acesso da aplicação. <a href="'.$url.'">Configurar</a>';
        break;
      }
    }
  }


  public function isNfeIssued($order_id, $tr){

    $status = array();
    if($tr['nfe_issued'] == 0){
      $status['status'] = 'nao-emitida';
      $status['message'] = 'NF-e não emitida';
    }else{
      $status['status'] = 'emitida';
      $status['message'] = 'NF-e emitida';
    }

    return '<div class="'.$status['status'].' nfe-status">'.$status['message'].'</div>';

  }

  public function getOrderData($orderID){
    $orderID = (int) $orderID;


    $order = new Order($orderID);

    $discounts = method_exists($order, 'getDiscounts') ? $order->getDiscounts(true) : $order->getCartRules();
    $discounts_applied = array(); // Only percentage

    $envio_email = Configuration::get($this->name.'envio_email');

    $uniq_key = Configuration::get($this->name.'uniq_key');
    if(!$uniq_key){
      $uniq_key = WebmaniaNFeSecurity::randomToken();
      Configuration::updateValue($this->name.'uniq_key', $uniq_key);
    }



    foreach($discounts as $discount){
      $cart_rule = new CartRule($discount['id_cart_rule']);
      if($cart_rule->reduction_percent > 0){
        $discounts_applied[] = $cart_rule->reduction_percent;
      }
    }

    // Get customer
    $customer = $order->getCustomer();
    $customer_docs = $this->getCustomerDocs($customer, $order);
    $number_status = Configuration::get($this->name.'numero_compl_status');
    $bairro_status = Configuration::get($this->name.'bairro_status');
    $fields = array(
      'cpf'            => Configuration::get($this->name.'cpf_field'),
      'cnpj'           => Configuration::get($this->name.'cnpj_field'),
      'razao_social'   => Configuration::get($this->name.'razao_social_field'),
      'ie'             => Configuration::get($this->name.'cnpj_ie_field')
    );
    if ($number_status == 'on') $fields['address_number'] = 'address_number';
    else $fields['address_number'] = Configuration::get($this->name.'numero_field');

    if ($bairro_status == 'on') $fields['bairro'] = 'bairro';
    else $fields['bairro'] = Configuration::get($this->name.'bairro_field');
    // Configured names may be empty or absent from the tables; never use them as null offsets.
    $fields = array_map('strval', $fields);

    // Search Database
    $address = new Address($order->id_address_delivery);
    $state = new State($address->id_state);
    $products = $order->getProducts();

    $customer_custom = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'customer WHERE id_customer = ' . (int)$customer->id);
    $address_custom = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'address WHERE id_address = ' . (int)$address->id);
    if (!is_array($customer_custom)) { $customer_custom = array(); }
    if (!is_array($address_custom)) { $address_custom = array(); }
    $numero = isset($address_custom[$fields['address_number']]) ? $address_custom[$fields['address_number']] : null;

    // Set Document Type
    /*$is_cnpj = str_replace( array('/', '.', '-'), '', $customer_custom[$fields['cnpj']]);
    if ($is_cnpj && strlen($is_cnpj) == 14) $is_cnpj = true; else $is_cnpj = false;
    if ($is_cnpj) $tipo_pessoa = 'cnpj'; else $tipo_pessoa = 'cpf';*/

    // Array start
    $data = array(
        'ID' => $order->id, // Número do pedido
        'origem' => 'prestashop_1.x',
        'url_notificacao' => $this->notificationUrl($uniq_key, $order->id),
        'operacao' => 1, // Tipo de Operação da Nota Fiscal
        'natureza_operacao' => Configuration::get($this->name.'operation_type'), // Natureza da Operação
        'modelo' => 1, // Modelo da Nota Fiscal (NF-e ou NFC-e)
        'emissao' => 1, // Tipo de Emissão da NF-e
        'finalidade' => 1, // Finalidade de emissão da Nota Fiscal
        'ambiente' => (int)Configuration::get($this->name.'sefaz_env') // Identificação do Ambiente do Sefaz //1 for production, 2 for development
     );

     $data['pedido'] = array(
         'presenca' => 2, // Indicador de presença do comprador no estabelecimento comercial no momento da operação
         'modalidade_frete' => 0, // Modalidade do frete
         'frete' => number_format($order->total_shipping, 2, '.', ''), // Total do frete
         'desconto' => number_format($order->total_discounts_tax_incl, 2, '.', ''), // Total do desconto
         'total' => number_format($order->total_paid_tax_incl, 2, '.', ''),  // Total do pedido - sem descontos
     );

     // Intermediador da operação
     $intermediador = Configuration::get($this->name.'intermediador');
     $data['pedido']['intermediador'] = ($intermediador == '00') ? '0' : $intermediador;
     $data['pedido']['cnpj_intermediador'] = Configuration::get($this->name.'intermediador_cnpj');
     $data['pedido']['id_intermediador'] = Configuration::get($this->name.'intermediador_id');

     $payment_module = $order->module;
     $payment_method = Configuration::get('webmaniabrnfepayment_'.$payment_module);
     $payment_desc = Configuration::get('webmaniabrnfepayment_'.$payment_module.'_desc');

     if($payment_method){
       $data['pedido']['forma_pagamento'] = $payment_method;
       $data['pedido']['desc_pagamento'] = $payment_desc;
     }

     //Informações COmplementares ao Fisco
     $fiscoinf = Configuration::get($this->name.'fisco_inf');

     if(!empty($fiscoinf) && strlen($fiscoinf) <= 2000){
       $data['pedido']['informacoes_fisco'] = $fiscoinf;
     }

     //Informações Complementares ao Consumidor
     $consumidorinf = Configuration::get($this->name.'cons_inf');

     if(!empty($consumidorinf) && strlen($consumidorinf) <= 2000){
       $data['pedido']['informacoes_complementares'] = $consumidorinf;
     }

    $tipo_pessoa = 'cpf';

    if ($customer_docs['cnpj'] && $customer_docs['cnpj'] != $customer_docs['cpf']){

        $tipo_pessoa = 'cnpj';

    } else if ( $this->is_cnpj($customer_docs['cpf']) ){

        $tipo_pessoa = 'cnpj';

    }

     //Client
     if($tipo_pessoa == 'cnpj'){

       $cnpj = $this->cnpj($customer_docs['cnpj']);
       if( !$cnpj && isset($customer->cnpj) ) $cnpj = $customer->cnpj;

       // The configured field (default "razao_social") is usually not a customer column.
       if (empty($customer_custom[$fields['razao_social']])) $razao_social = $customer->firstname.' '.$customer->lastname;
       else $razao_social = $customer_custom[$fields['razao_social']];

       $data['cliente'] = array(
         'cnpj'         => $customer_docs['cnpj'], // (pessoa jurídica) Número do CNPJ
         'razao_social' => $customer_docs['razao_social'], // (pessoa jurídica) Razão Social
         'ie'           => $customer_docs['ie'], // (pessoa jurídica) Número da Inscrição Estadual
         'endereco'     => $address->address1, // Endereço de entrega dos produtos
         'complemento'  => $address->other, // Complemento do endereço de entrega
         'numero'       => $numero, // Número do endereço de entrega
         'bairro'       => $address->address2, // Bairro do endereço de entrega
         'cidade'       => $address->city, // Cidade do endereço de entrega
         'uf'           => $state->iso_code, // Estado do endereço de entrega
         'cep'          => $address->postcode, // CEP do endereço de entrega
         'telefone'     => $address->phone, // Telefone do cliente
         'email'        => $customer->email // E-mail do cliente para envio da NF-e
       );

     }else{

        $cpf = $this->cpf($customer_docs['cpf']);
        if( !$cpf && isset($customer->cpf) ) $cpf = $customer->cpf;

       $data['cliente'] = array(
         'cpf' => $cpf, // (pessoa fisica) Número do CPF
         'nome_completo' => $customer->firstname.' '.$customer->lastname, // (pessoa fisica) Nome completo
         'endereco' => $address->address1, // Endereço de entrega dos produtos
         'complemento' => $address->other, // Complemento do endereço de entrega
         'numero' => $numero, // Número do endereço de entrega
         'bairro' => $address->address2, // Bairro do endereço de entrega
         'cidade' => $address->city, // Cidade do endereço de entrega
         'uf' => $state->iso_code, // Estado do endereço de entrega
         'cep' => $address->postcode, // CEP do endereço de entrega
         'telefone' => $address->phone, // Telefone do cliente
         'email' => $customer->email // E-mail do cliente para envio da NF-e
       );
     }

     if (_MAIN_PS_VERSION_ == '1.7'){

       $data['cliente']['bairro'] = ($fields['bairro'] && isset($address_custom[$fields['bairro']])) ? $address_custom[$fields['bairro']] : '';

       if (!$data['cliente']['bairro']) {

         $data['cliente']['bairro'] = $address->address2;
         $data['cliente']['complemento'] = $address->other;

       } else {

         $data['cliente']['complemento'] = $address->address2;

       }

     }

     //produtos

     foreach ($products as $key => $item){

       $product_id = $item['product_id'];
       $category_id = $item['id_category_default'];

       $ignorar = Db::getInstance()->getValue('SELECT nfe_ignorar_nfe FROM '._DB_PREFIX_.'product WHERE id_product = ' . (int)$product_id);

       if($ignorar == '1'){
         $data['pedido']['total'] -= number_format($item['total_price_tax_incl'], 2, '.', '');

         foreach($discounts_applied as $percentage){
           $data['pedido']['total'] += ($percentage/100)*$item['total_price_tax_incl'];
           $data['pedido']['desconto'] -= ($percentage/100)*$item['total_price_tax_incl'];
         }

         $data['pedido']['total'] = number_format($data['pedido']['total'], 2, '.', '');
         $data['pedido']['desconto'] = number_format($data['pedido']['desconto'], 2, '.', '');

         continue;
       }

       /*
       * Specific product values
       */
       //Antigo EAN
       $gtin = Db::getInstance()->getValue('SELECT nfe_ean_bar_code FROM '._DB_PREFIX_.'product WHERE id_product = ' . (int)$product_id);

       $gtin_tributavel = Db::getInstance()->getValue('SELECT nfe_gtin_tributavel FROM '._DB_PREFIX_.'product WHERE id_product = ' . (int)$product_id);

       $codigo_ncm = Db::getInstance()->getValue('SELECT nfe_ncm_code FROM '._DB_PREFIX_.'product WHERE id_product = ' . (int)$product_id);
       $codigo_cest = Db::getInstance()->getValue('SELECT nfe_cest_code FROM '._DB_PREFIX_.'product WHERE id_product = ' . (int)$product_id);

       $cnpj_fabricante = Db::getInstance()->getValue('SELECT nfe_cnpj_fabricante FROM '._DB_PREFIX_.'product WHERE id_product = ' . (int)$product_id);

       $ind_escala = Db::getInstance()->getValue('SELECT nfe_ind_escala FROM '._DB_PREFIX_.'product WHERE id_product = ' . (int)$product_id);

       $origem = Db::getInstance()->getValue('SELECT nfe_product_source FROM '._DB_PREFIX_.'product WHERE id_product = ' . (int)$product_id);
       $imposto = Db::getInstance()->getValue('SELECT nfe_tax_class FROM '._DB_PREFIX_.'product WHERE id_product = ' . (int)$product_id);
       $peso = $item['product_weight'];

       $kg = explode('.', $peso);
       if (strlen($kg[0]) >= 3) {

            $peso = $peso / 1000;

       }

       /*
       * Default values
       */
       if ($peso) $peso = number_format($peso, 3, '.', '');
       if (!$gtin) $gtin = Configuration::get($this->name.'ean_barcode');
       if (!$gtin_tributavel) $gtin_tributavel = Configuration::get($this->name.'gtin_tributavel');
       if (!$codigo_ncm){
         $codigo_ncm = $this->get_category_ncm($category_id);
         if(!$codigo_ncm){
           $codigo_ncm = Configuration::get($this->name.'ncm_code');
         }
       }
       if (!$codigo_cest) $codigo_cest = Configuration::get($this->name.'cest_code');
       if (!is_numeric($origem) || $origem == -1) $origem = Configuration::get($this->name.'product_source');
       if (!$imposto) $imposto = Configuration::get($this->name.'tax_class');


       if(!$cnpj_fabricante){
         $cnpj_fabricante = Configuration::get($this->name.'cnpj_fabricante');
       }

       if(!$ind_escala){
         $ind_escala = Configuration::get($this->name.'ind_escala');
       }



        $subtotal = number_format($item['unit_price_tax_incl'], 2, '.', '');
        $total_price = number_format($item['total_price_tax_incl'], 2, '.', '');

       $data['produtos'][] = array(
         'nome' => $item['product_name'], // Nome do produto
         'sku' => $item['product_reference'], // Código identificador - SKU
         'gtin' => $gtin, // Código GTIN, antigo EAN,
         'gtin_tributavel' => $gtin_tributavel,
         'ncm' => $codigo_ncm, // Código NCM
         'cest' => $codigo_cest, // Código CEST
         'cnpj_fabricante' => $cnpj_fabricante,
         'ind_escala' => $ind_escala,
         'quantidade' => $item['product_quantity'], // Quantidade de itens
         'unidade' => 'UN', // Unidade de medida da quantidade de itens
         'peso' => $peso, // Peso em KG. Ex: 800 gramas = 0.800 KG
         'origem' => ($origem == '00' ? 0 : $origem),//Origem do produto
         'subtotal' => number_format($item['unit_price_tax_incl'], 2, '.', ''), // Preço unitário do produto - sem descontos
         'total' => number_format($item['total_price_tax_incl'], 2, '.', ''), // Preço total (quantidade x preço unitário) - sem descontos
         'classe_imposto' => $imposto // Referência do imposto cadastrado
       );
     }

    $include_shipping_info = Configuration::get($this->name.'transp_include');

     if($include_shipping_info == 'on'){

       $carriers = Configuration::get($this->name.'carriers');
       $carriers = base64_decode(str_replace('%', '=', $carriers));
       $carriers = json_decode($carriers, true);

       if(!is_array($carriers)) $carriers = array();

       foreach($carriers as $carrier){

         if($carrier['method'] == $order->id_carrier){

           $data['transporte'] = array(
             'cnpj'         => $carrier['cnpj'],
             'razao_social' => $carrier['razao_social'],
             'ie'           => $carrier['ie'],
             'endereco'     => $carrier['address'],
             'uf'           => $carrier['uf'],
             'cidade'       => $carrier['city'],
             'cep'          => $carrier['cep'],
           );


           $transporte_info = Db::getInstance()->getRow("SELECT nfe_modalidade_frete, nfe_volumes, nfe_especie, nfe_peso_bruto, nfe_peso_liquido, nfe_valor_seguro FROM "._DB_PREFIX_."orders WHERE id_order = $orderID");

           $transporte_keys = array(
             'nfe_volumes'      => 'volume',
             'nfe_especie'      => 'especie',
             'nfe_peso_bruto'   => 'peso_bruto',
             'nfe_peso_liquido' => 'peso_liquido',
             'nfe_valor_seguro' => 'seguro'
           );

           foreach($transporte_keys as $db_key => $api_key){
             if($transporte_info[$db_key]){
               $data['transporte'][$api_key] = $transporte_info[$db_key];
             }
           }

           if($transporte_info['nfe_modalidade_frete']){
             $data['pedido']['modalidade_frete'] = $transporte_info['nfe_modalidade_frete'];
           }

         }

       }

    }

    return $data;

  }

  function get_category_ncm( $id_category ){

    $ncm = false;
    $id_category = (int) $id_category;
    $category_obj = new CategoryCore($id_category);

    // PS 1.6 keeps the column in category_lang (see getColumnsToAdd); a failed query returns false.
    $category_ncm = Db::getInstance()->ExecuteS('SELECT nfe_category_ncm FROM '._DB_PREFIX_.$this->categoryNcmTable().' WHERE id_category = '.$id_category);

    foreach(is_array($category_ncm) ? $category_ncm : array() as $ncm_query){
      if(!empty($ncm_query['nfe_category_ncm'])){
        $ncm = $ncm_query['nfe_category_ncm'];
      }
    }

    if( isset($category_obj->id_parent) && $category_obj->level_depth > 2 && !$ncm ){
      return $this->get_category_ncm( $category_obj->id_parent );
    }

    return $ncm;

  }

  public function get_table_radio_options() {

    $keys = $this->get_customer_columns_listings();
    $values = array();

    foreach($keys as $key){
      $values[] = array(
        'id_option'    => $key,
        'name' => $key,
      );
    }


    return $values;

  }

  public function get_customer_columns_listings() {

    $keys = array();

    $exclude = array(
      'id_customer', 'id_shop_group', 'id_shop', 'id_gender',
      'id_default_group', 'id_lang', 'id_risk', 'firstname',
      'lastname', 'email', 'passwd', 'last_passwd_gen',
      'birthday', 'newsletter', 'ip_registration_newsletter',
      'newsletter_date_add', 'optin', 'website', 'outstanding_allow_amount',
      'show_public_prices', 'max_payment_days', 'secure_key', 'note',
      'active', 'is_guest', 'deleted', 'date_add', 'date_upd', 'siret', 'ape',
    );

    $columns = Db::getInstance()->executeS('DESCRIBE ps_customer');

    foreach($columns as $col){
      if(!in_array($col['Field'], $exclude)){
        $keys[] = $col['Field'];
      }
    }

    return $keys;
  }

  public function get_tables_select_element($name){

    $name = 'webmaniabrnfedocstable_'.$name;
    $active_value = Configuration::get($name);


    // Only the shop database (other schemas on the same server are not readable by the mapping).
    $tables = Db::getInstance()->executeS("SELECT TABLE_NAME AS TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_SCHEMA = DATABASE()");
    $html = '<select name="'.$name.'" class="nfe-docs--column" id="'.$name.'">';
    $html .= '<option value="">Selecionar</option>';

    foreach(is_array($tables) ? $tables : array() as $table){

      if(!isset($table['TABLE_NAME']) || !$this->isSqlIdentifier($table['TABLE_NAME'])) continue;
      $selected = '';
      if($table['TABLE_NAME'] == $active_value){
        $selected = 'selected';
      }

      $html.= '<option value="'.$table['TABLE_NAME'].'" '.$selected.'>'.$table['TABLE_NAME'].'</option>';
    }

    $html .= '</select>';
    return $html;

  }

  public function get_columns_select_element($name){

    $active_table = Configuration::get($this->name.'docstable_'.$name);

    $name = 'webmaniabrnfedocscolumn_'.$name;
    $active_value = Configuration::get($name);

    // The saved table name is admin input (isGenericName allows quotes): validate it before querying.
    if($active_table && $this->isSqlIdentifier($active_table)){
      // MySQL 8 returns information_schema columns in upper case unless aliased.
      $results = Db::getInstance()->executeS("SELECT column_name AS column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '".pSQL($active_table)."'");

      $html = '<select name="'.$name.'">';
      $html .= '<option value="">Selecionar</option>';

      foreach(is_array($results) ? $results : array() as $column){

        if(!isset($column['column_name']) || !$this->isSqlIdentifier($column['column_name'])) continue;
        $selected = '';
        if($column['column_name'] == $active_value){
          $selected = 'selected';
        }

        $html .= '<option value="'.$column['column_name'].'" '.$selected.'>'.$column['column_name'].'</option>';

      }

      $html .= '</select>';


    }else{
      $html = '<select name="'.$name.'"><option value=""></option></select>';
    }


    return $html;

  }

  private function recoveryOrderAccessible($orderId) {
    $shops = array_filter(array_map('intval', Shop::getContextListShopID()));
    return $shops && (bool) Db::getInstance()->getValue('SELECT id_order FROM '._DB_PREFIX_.'orders WHERE id_order = '.(int) $orderId.
      ' AND id_shop IN ('.implode(',', $shops).')', false);
  }

  /** Display recovery on the order itself, including orders whose first response was lost. */
  public function displayEmissionRecovery($orderId) {
    if (!$this->employeeCan('AdminOrders', 'edit') || !$this->recoveryOrderAccessible($orderId)) { return ''; }
    $reservation = WebmaniaNFeEmission::current($orderId);
    if (!$reservation || $reservation['phase'] !== 'dispatching') { return ''; }
    $this->context->smarty->assign(array('wmbr_recovery' => array(
      'order_id' => (int) $orderId,
      'key' => hash('sha256', $reservation['token']),
      'has_uuid' => WebmaniaNFeSecurity::validUuid($reservation['uuid']),
      'url' => $this->context->link->getAdminLink('AdminWebmaniaNfeAjax').'&ajax=1',
      'csrf' => Tools::getAdminTokenLite('AdminWebmaniaNfeAjax')
    )));
    return $this->display(__FILE__, 'emission_recovery.tpl');
  }

  /** Recovery never emits a new invoice. Unknown API results keep the reservation. */
  public function recoverEmission($orderId, $key, $confirmAbsent = false) {
    $failure = array('success' => false, 'message' => 'Não foi possível recuperar esta tentativa. Atualize o pedido e consulte a Webmania.');
    require_once __DIR__.'/sdk/Ajax.php';
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST' ||
        !WebmaniaNFeAjax::authorized($this->context, 'recoverEmission', Tools::getValue('adminToken'), true) ||
        !$this->employeeCan('AdminOrders', 'edit') || !is_scalar($orderId) || !ctype_digit((string) $orderId) || (int) $orderId < 1 ||
        !is_string($key) || !preg_match('/^[a-f0-9]{64}$/D', $key) || !$this->recoveryOrderAccessible($orderId)) { return $failure; }
    $orderId = (int) $orderId;
    $reservation = WebmaniaNFeEmission::current($orderId);
    if (!$reservation || $reservation['phase'] !== 'dispatching' ||
        !WebmaniaNFeSecurity::equals(hash('sha256', $reservation['token']), $key)) { return $failure; }
    $uuid = $reservation['uuid'];
    if ($uuid === '') {
      if (!is_scalar($confirmAbsent) || (string) $confirmAbsent !== '1') { return $failure; }
      // Ten minutes exceed the API's five-minute transport timeout. Match all state in the DELETE.
      if (!WebmaniaNFeEmission::releaseUnidentified($orderId, $reservation['token'])) {
        return array('success' => false, 'message' => 'Aguarde ao menos 10 minutos após o envio e atualize o pedido. A tentativa continua protegida.');
      }
      return array('success' => true, 'message' => 'Tentativa liberada após sua confirmação. Nenhuma nova nota foi emitida.');
    }
    if (!WebmaniaNFeSecurity::validUuid($uuid)) { return $failure; }
    $api = new NFe($this->settings);
    $response = $api->consultaNotaFiscal($uuid);
    if (!is_object($response) || isset($response->error) || !isset($response->uuid) || $response->uuid !== $uuid) { return $failure; }
    $responseId = isset($response->ID) ? $response->ID : (isset($response->data->ID) ? $response->data->ID : null);
    if ($responseId !== null && ((!is_string($responseId) && !is_int($responseId)) || (string) $responseId !== (string) $orderId)) { return $failure; }
    if (isset($response->status) && $response->status === 'reprovado') {
      if (!WebmaniaNFeEmission::release($orderId, $reservation['token'])) { return $failure; }
      return array('success' => true, 'message' => 'Nota rejeitada. A tentativa foi liberada; revise os dados antes de tentar novamente.');
    }
    $document = Db::getInstance()->getValue('SELECT c.nfe_document_number FROM '._DB_PREFIX_.'customer c INNER JOIN '._DB_PREFIX_.
      'orders o ON o.id_customer = c.id_customer WHERE o.id_order = '.$orderId, false);
    $client = isset($response->data->cliente) && is_object($response->data->cliente) ? $response->data->cliente : null;
    if ($client && isset($client->cnpj) && is_scalar($client->cnpj)) { $document = $client->cnpj; }
    elseif ($client && isset($client->cpf) && is_scalar($client->cpf)) { $document = $client->cpf; }
    if (!$this->persistNfeResponse($orderId, $response, is_scalar($document) ? (string) $document : '', true, true, $reservation['requested_at']) ||
        !WebmaniaNFeEmission::release($orderId, $reservation['token'])) { return $failure; }
    return array('success' => true, 'message' => 'Nota confirmada e recuperada. Nenhuma nova nota foi emitida.');
  }

  /** Compare-and-swap preserves other invoice updates without relying on a storage engine lock. */
  private function persistNfeResponse($orderId, $response, $document, $allowInsert, $keepExisting, $issuedAt = null) {
    if (!is_object($response) || !isset($response->uuid, $response->status) ||
        !WebmaniaNFeSecurity::validUuid($response->uuid) || !WebmaniaNFeSecurity::validStatus($response->status)) { return false; }
    $db = Db::getInstance();
    for ($attempt = 0; $attempt < 5; ++$attempt) {
      $row = $db->getRow('SELECT nfe_info FROM '._DB_PREFIX_.'orders WHERE id_order = '.(int) $orderId, false);
      if (!is_array($row) || !array_key_exists('nfe_info', $row)) { return false; }
      $raw = $row['nfe_info'];
      $invoices = WebmaniaNFeSecurity::invoices($raw);
      $found = null;
      foreach ($invoices as $key => $invoice) {
        if (isset($invoice['uuid']) && $invoice['uuid'] === $response->uuid) { $found = $key; break; }
      }
      if ($found !== null && $keepExisting) { return true; }
      $values = array();
      if ($found !== null) {
        $invoices[$found]['status'] = $response->status;
      } else {
        if (!$allowInsert || $response->status === 'reprovado') { return false; }
        $date = is_string($issuedAt) ? strtotime($issuedAt.' UTC') : false;
        $invoices[] = array(
          'uuid' => $response->uuid, 'status' => $response->status, 'chave_acesso' => $response->chave,
          'n_recibo' => (int) $response->recibo, 'n_nfe' => (int) $response->nfe, 'n_serie' => (int) $response->serie,
          'url_xml' => $response->xml, 'url_danfe' => $response->danfe,
          'url_danfe_simplificada' => $response->danfe_simples, 'url_danfe_etiqueta' => $response->danfe_etiqueta,
          'documento' => WebmaniaNFeSecurity::normalizeDocument($document),
          'data' => $date === false ? date('d/m/Y') : date('d/m/Y', $date)
        );
        $values = array('nfe_numero' => pSQL((string) $response->nfe),
          'nfe_chave_de_acesso' => pSQL((string) $response->chave), 'nfe_issued' => 1);
      }
      $encoded = serialize($invoices);
      $values['nfe_info'] = pSQL($encoded, true);
      $condition = 'id_order = '.(int) $orderId.' AND nfe_info <=> '.($raw === null ? 'NULL' : "'".pSQL((string) $raw, true)."'");
      if (!$db->update('orders', $values, $condition)) { return false; }
      if ((string) $db->getValue('SELECT nfe_info FROM '._DB_PREFIX_.'orders WHERE id_order = '.(int) $orderId, false) === $encoded) { return true; }
    }
    return false;
  }

  public function emitirNfe($orderID){
    $orderID = (int) $orderID;
    if ($orderID < 1) { return array(array('type' => 'error', 'msg' => 'Pedido inválido.')); }
    $isempty = false;

    foreach($this->settings as $setting){
      if(empty($setting)){
        $url  = 'index.php?controller=AdminModules&configure=webmaniabrnfe';
        $url .= '&token='.Tools::getAdminTokenLite('AdminModules');
        $this->controllerMessage('errors', 'Informe as credenciais de acesso antes de emitir a NF-e: <a href="'.$url.'">Configurar</a>');
        $isempty = true;
        break;
      }
    }

    if($isempty) return array(array('type' => 'error', 'msg' => 'Configure as credenciais antes de emitir a nota fiscal.'));

    if (isset($this->emissionTokens[$orderID])) {
      return array(array('type' => 'error', 'msg' => 'Já há uma emissão em andamento para este pedido.'));
    }
    $token = WebmaniaNFeEmission::acquire($orderID);
    if (!$token) {
      return array(array('type' => 'error', 'msg' => 'Já há uma emissão em andamento ou aguardando confirmação para este pedido. Consulte a nota na Webmania antes de repetir.'));
    }
    $this->emissionTokens[$orderID] = $token;
    $this->emissionRelease[$orderID] = true;
    try {
      // Another request can save its invoice between the automatic hook's read and acquisition.
      if (isset($this->automaticEmissionOrders[$orderID]) && WebmaniaNFeSecurity::invoices(
          Db::getInstance()->getValue('SELECT nfe_info FROM '._DB_PREFIX_.'orders WHERE id_order = '.$orderID, false))) { return array(); }
      return $this->emitirNfeReservada($orderID, $token);
    } finally {
      if ($this->emissionRelease[$orderID]) { WebmaniaNFeEmission::release($orderID, $token); }
      unset($this->emissionTokens[$orderID], $this->emissionRelease[$orderID]);
    }
  }

  private function emitirNfeReservada($orderID, $token){
    $webmaniabr = new NFe($this->settings);
    $data = $this->getOrderData($orderID);
    if (!is_array($data)) { return array(array('type' => 'error', 'msg' => 'Não foi possível preparar os dados da nota fiscal.')); }

    if (!WebmaniaNFeEmission::dispatch($orderID, $token)) {
      return array(array('type' => 'error', 'msg' => 'Esta tentativa de emissão expirou. Tente novamente.'));
    }
    $data['url_notificacao'] = (isset($data['url_notificacao']) && is_string($data['url_notificacao']) ?
      $data['url_notificacao'] : $this->notificationUrl($this->uniq_key, $orderID));
    $data['url_notificacao'] .= (strpos($data['url_notificacao'], '?') === false ? '?' : '&').'wmbr_emission_token='.$token;
    // Persist the possible dispatch before crossing the network. It has no automatic expiry.
    $this->emissionRelease[$orderID] = false;
    $response = $webmaniabr->emissaoNotaFiscal($data);
    if (is_object($response) && isset($response->error) && empty($response->request_uncertain)) {
      $this->emissionRelease[$orderID] = true;
    } elseif (is_object($response) && isset($response->status) && $response->status === 'reprovado') {
      $this->emissionRelease[$orderID] = true;
    }
    if (is_object($response) && isset($response->uuid)) { WebmaniaNFeEmission::remember($orderID, $token, $response->uuid); }


    $alerts = array();
    if (!$response || isset($response->error) || $response->status == 'reprovado' ){

      // SEFAZ rejection text is API data: only a scalar from the documented array shape, escaped like ->error.
      $motivo = '';
      if ($response && !isset($response->error) && isset($response->log) && is_object($response->log) &&
          isset($response->log->aProt) && is_array($response->log->aProt) && isset($response->log->aProt[0]) &&
          is_object($response->log->aProt[0]) && isset($response->log->aProt[0]->xMotivo) && is_scalar($response->log->aProt[0]->xMotivo)) {
        $motivo = htmlspecialchars((string) $response->log->aProt[0]->xMotivo, ENT_QUOTES, 'UTF-8');
      }

      if (!$response){
        $alerts[] = array('type' => 'error', 'msg' => 'Erro ao emitir a NF-e do Pedido #'.$orderID.' (null)');
        $this->controllerMessage('errors', 'Erro ao emitir a NF-e do Pedido #'.$orderID.' (null)');
        echo 'Erro ao emitir a NF-e do Pedido #'.$orderID.' (null)';
      } elseif (isset($response->error)){
        $alerts[] = array('type' => 'error', 'msg' => 'Erro ao emitir a NF-e do Pedido #'.$orderID.' ( '.$response->error.' )');
        $this->controllerMessage('errors', 'Erro ao emitir a NF-e do Pedido #'.$orderID.' ( '.$response->error.' )');
      } elseif ($motivo !== ''){
        $alerts[] = array('type' => 'error', 'msg' => 'Erro ao emitir a NF-e do Pedido #'.$orderID. '( '.$motivo.' )');
        $this->controllerMessage('errors', 'Erro ao emitir a NF-e do Pedido #'.$orderID. '( '.$motivo.' )');
      } else {
        $alerts[] = array('type' => 'error', 'msg' => 'Erro ao emitir a NF-e do Pedido #'.$orderID);
        $this->controllerMessage('errors', 'Erro ao emitir a NF-e do Pedido #'.$orderID);
      }


    }else{

      $document = isset($data['cliente']['cnpj']) ? $data['cliente']['cnpj'] : (isset($data['cliente']['cpf']) ? $data['cliente']['cpf'] : '');
      if (!$this->persistNfeResponse($orderID, $response, $document, true, true)) {
        $alerts[] = array('type' => 'error', 'msg' => 'A nota foi recebida, mas não foi possível salvar sua confirmação. Consulte a Webmania antes de repetir.');
        return $alerts;
      }
      $this->emissionRelease[$orderID] = true;

        $alerts[] = array('type' => 'success', 'msg' => 'NF-e emitida com sucesso do Pedido #'.$orderID);
        $this->controllerMessage('confirmations', 'NF-e emitida com sucesso do Pedido #'.$orderID);

    }

    return $alerts;

  }

  public function updateNFe(){

    if(Tools::getValue('atualizar') && Tools::getValue('chave') && is_scalar(Tools::getValue('chave'))){
      $chave_acesso = pSql((string) Tools::getValue('chave'));
      $order_id = (int) Tools::getValue('id_order');
      $webmaniabr = new NFe($this->settings);
      $response = $webmaniabr->consultaNotaFiscal($chave_acesso);

      if (isset($response->error)){

          $this->context->controller->errors[] = 'Erro: '.$response->error;
          return false;

      }else{

        $new_status = $response->status;
        $nfe_data = WebmaniaNFeSecurity::invoices(Db::getInstance()->getValue("SELECT nfe_info FROM "._DB_PREFIX_."orders WHERE id_order = $order_id" ));

        foreach($nfe_data as &$order_nfe){
          if($order_nfe['chave_acesso'] == $chave_acesso){
            $order_nfe['status'] = $new_status;
          }
        }

        $nfe_data_str = serialize($nfe_data);

        if(!Db::getInstance()->update('orders', array('nfe_info' => pSQL($nfe_data_str)),"id_order = '" .$order_id."'  " )){
          $this->context->controller->errors[] = 'Erro ao atualizar informações da Nota Fiscal';
        }else{
          $this->context->controller->confirmations[] = 'NF-e atualizada com sucesso';
        }

      }
    }
  }

  public function processBulkEmitirNfe(){

    $bulk_actions = array('submitBulkemitirNfeorder', 'submitBulkimprimirDanfe', 'submitBulkimprimirDanfeSimples', 'submitBulkimprimirDanfeEtiqueta');
    $submitted = false;
    foreach ($bulk_actions as $bulk_action) { if (Tools::isSubmit($bulk_action)) { $submitted = true; } }
    if (!$submitted) { return; }

    // This runs from the back-office header of every page: accept only the AdminOrders list form
    // (whose token PrestaShop validates) and the order permissions the native routes require.
    $controller = isset($this->context->controller) ? $this->context->controller : null;
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST' || !is_object($controller) ||
        !isset($controller->controller_name) || $controller->controller_name !== 'AdminOrders') { return; }

    $order_box = array();
    $submitted_ids = Tools::getValue('orderBox');
    foreach (is_array($submitted_ids) ? $submitted_ids : array() as $id) {
      if (is_scalar($id) && ctype_digit((string) $id) && (int) $id > 0) { $order_box[] = (int) $id; }
    }
    if (!$order_box) { return; }

    if( Tools::isSubmit('submitBulkemitirNfeorder') && $this->employeeCan('AdminOrders', 'edit') ){
      foreach($order_box as $orderID){
        $this->emitirNfe($orderID);
      }
    }

    if (!$this->employeeCan('AdminOrders', 'view')) { return; }

    if( Tools::isSubmit('submitBulkimprimirDanfe')  ){
      $values = $order_box;
      if (!empty($values)) {
        $result = $this->get_nfe_urls($values, 'normal');
        if ($result['result'] && !empty($result['file']) && file_exists($result['file'])) {
          $this->showDanfe($result['file'], $result['filename']);
        }
      }    
    }

    if( Tools::isSubmit('submitBulkimprimirDanfeSimples')  ){
      $values = $order_box;
      if (!empty($values)) {
        $result = $this->get_nfe_urls($values, 'simples');
        if ($result['result'] && !empty($result['file']) && file_exists($result['file'])) {
          $this->showDanfe($result['file'], $result['filename']);
        }
      }    
    }

    if( Tools::isSubmit('submitBulkimprimirDanfeEtiqueta')  ){
      $values = $order_box;
      if (!empty($values)) {
        $result = $this->get_nfe_urls($values, 'etiqueta');
        if ($result['result'] && !empty($result['file']) && file_exists($result['file'])) {
          $this->showDanfe($result['file'], $result['filename']);
        }
      }    
    }

  }

  /*
   * Add custom columns to products, addresses and orders table
   */
  public function alterTable($method){

    $result = true;
    switch($method){
      case 'add':
        $columnsAll = $this->getColumnsToAdd();
        foreach($columnsAll as $columnsInfo){

          ${'sql_'.$columnsInfo['table_name']} = "ALTER TABLE " . _DB_PREFIX_ .$columnsInfo['table_name'];
          foreach($columnsInfo['columns'] as $column){
            $columnExists = Db::getInstance()->ExecuteS("SHOW COLUMNS FROM "._DB_PREFIX_.$columnsInfo['table_name']." LIKE '".$column['name']."'");

            if(!$columnExists){

              ${'sql_'.$columnsInfo['table_name']} .= $column['sql'].',';
              ${'sql_execute_'.$columnsInfo['table_name']} = true;
            }
          }

          if( isset(${'sql_execute_'.$columnsInfo['table_name']}) && ${'sql_execute_'.$columnsInfo['table_name']} === true ){

            if(!Db::getInstance()->Execute(rtrim(${'sql_'.$columnsInfo['table_name']}, ','))){
              $result = false;
            }
          }

        }
        break;
    }


    return $result;

  }

  public function getColumnsToAdd(){

    $ordersColumnsToAdd = array(
      'table_name' => 'orders',
      'columns' => array(
        'nfe_issued' => array(
          'name' => 'nfe_issued',
          'sql' => ' ADD COLUMN nfe_issued TINYINT DEFAULT 0'
        ),

        'nfe_info' => array(
          'name' => 'nfe_info',
          'sql' => ' ADD COLUMN nfe_info TEXT'
        ),

        'nfe_modalidade_frete' => array(
          'name' => 'nfe_modalidade_frete',
          'sql' => ' ADD COLUMN nfe_modalidade_frete VARCHAR(5) DEFAULT 0'
        ),

        'nfe_numero' => array(
          'name' => 'nfe_numero',
          'sql' => ' ADD COLUMN nfe_numero TEXT'
        ),

        'nfe_chave_de_acesso' => array(
          'name' => 'nfe_chave_de_acesso',
          'sql' => ' ADD COLUMN nfe_chave_de_acesso VARCHAR(44) DEFAULT 0'
        ),

        'nfe_volumes'  => array(
          'name' => 'nfe_volumes',
          'sql' => ' ADD COLUMN nfe_volumes VARCHAR(10)'
        ),

        'nfe_especie'  => array(
          'name' => 'nfe_especie',
          'sql' => ' ADD COLUMN nfe_especie VARCHAR(10)'
        ),

        'nfe_peso_bruto'  => array(
          'name' => 'nfe_peso_bruto',
          'sql' => ' ADD COLUMN nfe_peso_bruto VARCHAR(10)'
        ),

        'nfe_peso_liquido'  => array(
          'name' => 'nfe_peso_liquido',
          'sql' => ' ADD COLUMN nfe_peso_liquido VARCHAR(10)'
        ),

        'nfe_valor_seguro'  => array(
          'name' => 'nfe_valor_seguro',
          'sql' => ' ADD COLUMN nfe_valor_seguro VARCHAR(10)'
        ),
      ));

    $productsColumnsToAdd = array(
        'table_name' => 'product',
        'columns' => array(
          'nfe_tax_class' => array(
            'name' => 'nfe_tax_class',
            'sql' => ' ADD COLUMN nfe_tax_class VARCHAR(20)'
          ),

          'nfe_ean_bar_code' => array(
            'name' => 'nfe_ean_bar_code',
            'sql' => ' ADD COLUMN nfe_ean_bar_code VARCHAR(20)'
          ),

          'nfe_gtin_tributavel' => array(
            'name' => 'nfe_gtin_tributavel',
            'sql' => ' ADD COLUMN nfe_gtin_tributavel VARCHAR(20)'
          ),

          'nfe_ncm_code' => array(
            'name' => 'nfe_ncm_code',
            'sql' => ' ADD COLUMN nfe_ncm_code VARCHAR(20)'
          ),
          'nfe_cest_code' => array(
            'name' => 'nfe_cest_code',
            'sql' => ' ADD COLUMN nfe_cest_code VARCHAR(20)'
          ),
          'nfe_cnpj_fabricante' => array(
            'name' => 'nfe_cnpj_fabricante',
            'sql'  => ' ADD COLUMN nfe_cnpj_fabricante VARCHAR(20)'
          ),
          'nfe_ind_escala' => array(
            'name' => 'nfe_ind_escala',
            'sql'  => ' ADD COLUMN nfe_ind_escala VARCHAR(5)',
          ),
          'nfe_product_source' => array(
            'name' => 'nfe_product_source',
            'sql' => ' ADD COLUMN nfe_product_source VARCHAR(3) DEFAULT -1'
          ),
          'nfe_ignorar_nfe' => array(
            'name' => 'nfe_ignorar_nfe',
            'sql' => ' ADD COLUMN nfe_ignorar_nfe VARCHAR(5) DEFAULT 0'
          ),

        ));

        $addressColumnsToAdd = array(
          'table_name' => 'address',
          'columns' => array(
            'address_number' => array(
              'name' => 'address_number',
              'sql' => ' ADD COLUMN address_number VARCHAR(15)'
            ),
            'bairro' => array(
              'name' => 'bairro',
              'sql' => ' ADD COLUMN bairro VARCHAR(15)'
            ),

          ));

          $customerColumnsToAdd = array(
            'table_name' => 'customer',
            'columns' => array(
              'nfe_document_type' => array(
                'name' => 'nfe_document_type',
                'sql' => ' ADD COLUMN nfe_document_type VARCHAR (6)'
              ),
              'nfe_document_number' => array(
                'name' => 'nfe_document_number',
                'sql' => ' ADD COLUMN nfe_document_number VARCHAR (15)'
              ),
              'nfe_razao_social' => array(
                'name' => 'nfe_razao_social',
                'sql' => ' ADD COLUMN nfe_razao_social VARCHAR (50)'
              ),
              'nfe_pj_ie' => array(
                'name' => 'nfe_pj_ie',
                'sql' => ' ADD COLUMN nfe_pj_ie VARCHAR (50)'
              )
            ));

            if(_MAIN_PS_VERSION_ >= '1.7'){
              $categoryColumnsToAdd = array(
                'table_name' => 'category',
                'columns' => array(
                  'nfe_category_ncm' => array(
                    'name' => 'nfe_category_ncm',
                    'sql'  => ' ADD COLUMN nfe_category_ncm VARCHAR(20)'
                  )
                )
              );
            }else{
              $categoryColumnsToAdd = array(
                'table_name' => 'category_lang',
                'columns' => array(
                  'nfe_category_ncm' => array(
                    'name' => 'nfe_category_ncm',
                    'sql'  => ' ADD COLUMN nfe_category_ncm VARCHAR(20)'
                  )
                )
              );
            }


      return array($ordersColumnsToAdd, $productsColumnsToAdd, $addressColumnsToAdd, $customerColumnsToAdd, $categoryColumnsToAdd);

  }



  public function getProductNfeValues($productID){

    $result = Db::getInstance()->ExecuteS('SELECT nfe_tax_class, nfe_ean_bar_code, nfe_gtin_tributavel, nfe_ncm_code, nfe_cest_code, nfe_product_source, nfe_ind_escala, nfe_cnpj_fabricante, nfe_ignorar_nfe FROM '._DB_PREFIX_.'product WHERE id_product = ' . (int)$productID);
    return $result;

  }

  function is_cpf($cpf = null){
        if (is_array($cpf)) { $cpf = isset($cpf[0]) && is_scalar($cpf[0]) ? $cpf[0] : ''; }
        if (!is_scalar($cpf)) { return false; }
        $cpf = (string) $cpf;


      
      $cpf = preg_replace( '/[^0-9]/', '', $cpf );

      if ( 11 != strlen( $cpf ) || preg_match( '/^([0-9])\1+$/', $cpf ) ) {
        return false;
      }

      $digit = substr( $cpf, 0, 9 );

      for ( $j = 10; $j <= 11; $j++ ) {
          $sum = 0;

          for( $i = 0; $i< $j-1; $i++ ) {
              $sum += ( $j - $i ) * ( (int) $digit[ $i ] );
          }

          $summod11 = $sum % 11;
          $digit[ $j - 1 ] = $summod11 < 2 ? 0 : 11 - $summod11;
      }

      return $digit[9] == ( (int) $cpf[9] ) && $digit[10] == ( (int) $cpf[10] );

  }

  function is_cnpj($cnpj = null) {
        if (is_array($cnpj)) { $cnpj = isset($cnpj[0]) && is_scalar($cnpj[0]) ? $cnpj[0] : ''; }
        return WebmaniaNFeSecurity::validCnpj($cnpj);
    }

  function cpf( $string ){

  if (!$string) return;
  $string = $this->clear( $string );
  $string = $this->mask($string,'###.###.###-##');

  return $string;

  }

  function cnpj( $string ){
        $string = WebmaniaNFeSecurity::normalizeDocument($string);
        if (!preg_match('/\A[A-Z0-9]{12}[0-9]{2}\z/', $string)) { return; }
        return $this->mask($string, '##.###.###/####-##');
    }

  function clear( $string ) {
        $string = is_scalar($string) ? (string) $string : '';


        $string = str_replace( array(',', '-', '!', '.', '/', '?', '(', ')', ' ', '$', 'R$', '€'), '', $string );
        return $string;

	}

	function mask($val, $mask) {
        $val = is_scalar($val) ? (string) $val : '';

	   $maskared = '';
	   $k = 0;
	   for($i = 0; $i<=strlen($mask)-1; $i++){
           if($mask[$i] == '#'){
               if(isset($val[$k]))
                   $maskared .= $val[$k++];
           }
           else {
               if(isset($mask[$i])) $maskared .= $mask[$i];
           }
	   }
	   return $maskared;
	}


  public function getValidadeCertificado(){


      $cookie = new Cookie('validate_cookie');


    if(!$cookie->certificadoExpire){
      if(_MAIN_PS_VERSION_ != '1.4'){
        $cookie->setExpire(time() + 60*60*24);
      }
      $webmaniabr = new NFe($this->settings);
      $response = $webmaniabr->validadeCertificado();
      if(is_object($response)) return $response;
      $cookie->certificadoExpire = $response;
    }

    return $cookie->certificadoExpire;

  }

  public function displayMessageCertificado(){


    $validade = $this->getValidadeCertificado();
    if(isset($validade->error)){
      return false;
    }

    if($validade < 45 && $validade >= 1){

        $this->context->controller->warnings[] = 'WebmaniaBR NF-e: Emita um novo Certificado Digital A1 - vencerá em '.$this->getValidadeCertificado().' dias';

    }else if(!$validade){

        $this->context->controller->warnings[] = 'WebmaniaBR NF-e: Certificado Digital A1 vencido. Emita um novo para continuar operando.';

    }

    return true;

  }





  public function rearrangeStates(){

    $states = array(
      'acre' => array(
        'id_state' => 313,
        'id_country' => 58,
        'id_zone' => 6,
        'name' => 'Acre',
        'iso_code' => 'AC',
        'tax_behavior' => 0,
        'active' => 1,
      ),
      'alagoas' => array(
        'id_state' => 314,
        'id_country' => 58,
        'id_zone' => 6,
        'name' => 'Alagoas',
        'iso_code' => 'AL',
        'tax_behavior' => 0,
        'active' => 1,
      ),

      'amapa' => Array(
        'id_state' => 315,
        'id_country' => 58,
        'id_zone' => 6,
        'name' => 'Amapá',
        'iso_code' => 'AP',
        'tax_behavior' => 0,
        'active' => 1,
      ),

    'amazonas' => Array(
      'id_state' => 316,
      'id_country' => 58,
      'id_zone' => 6,
      'name' => 'Amazonas',
      'iso_code' => 'AM',
      'tax_behavior' => 0,
      'active' => 1,
      ),

    'bahia' => Array(
      'id_state' => 317,
      'id_country' => 58,
      'id_zone' => 6,
      'name' => 'Bahia',
      'iso_code' => 'BA',
      'tax_behavior' => 0,
      'active' => 1,
      ),

    'ceara' => Array(
      'id_state' => 318,
      'id_country' => 58,
      'id_zone' => 6,
      'name' => 'Ceará',
      'iso_code' => 'CE',
      'tax_behavior' => 0,
      'active' => 1,
      ),

      'distrito_federal' => Array(
        'id_state' => 319,
        'id_country' => 58,
        'id_zone' => 6,
        'name' => 'Distrito Federal',
        'iso_code' => 'DF',
        'tax_behavior' => 0,
        'active' => 1,
        ),

    'espirito_santo' => Array(
      'id_state' => 320,
      'id_country' => 58,
      'id_zone' => 6,
      'name' => 'Espírito Santo',
      'iso_code' => 'ES',
      'tax_behavior' => 0,
      'active' => 1,
      ),

    'goias' => Array(
      'id_state' => 321,
      'id_country' => 58,
      'id_zone' => 6,
      'name' => 'Goiás',
      'iso_code' => 'GO',
      'tax_behavior' => 0,
      'active' => 1,
      ),

    'maranhao' => Array(
      'id_state' => 322,
      'id_country' => 58,
      'id_zone' => 6,
      'name' => 'Maranhão',
      'iso_code' => 'MA',
      'tax_behavior' => 0,
      'active' => 1,
      ),

    'mato_grosso' => Array(
      'id_state' => 323,
      'id_country' => 58,
      'id_zone' => 6,
      'name' => 'Mato Grosso',
      'iso_code' => 'MT',
      'tax_behavior' => 0,
      'active' => 1,
      ),

    'mato_grosso_sul' => Array(
      'id_state' => 324,
      'id_country' => 58,
      'id_zone' => 6,
      'name' => 'Mato Grosso do Sul',
      'iso_code' => 'MS',
      'tax_behavior' => 0,
      'active' => 1,
      ),

    'minas_gerais' => Array(
      'id_state' => 325,
      'id_country' => 58,
      'id_zone' => 6,
      'name' => 'Minas Gerais',
      'iso_code' => 'MG',
      'tax_behavior' => 0,
      'active' => 1,
      ),

    'para' => Array(
      'id_state' => 326,
      'id_country' => 58,
      'id_zone' => 6,
      'name' => 'Pará',
      'iso_code' => 'PA',
      'tax_behavior' => 0,
      'active' => 1,
      ),

    'paraiba' => Array(
      'id_state' => 327,
      'id_country' => 58,
      'id_zone' => 6,
      'name' => 'Paraíba',
      'iso_code' => 'PB',
      'tax_behavior' => 0,
      'active' => 1,
      ),

    'parana' => Array(
      'id_state' => 328,
      'id_country' => 58,
      'id_zone' => 6,
      'name' => 'Paraná',
      'iso_code' => 'PR',
      'tax_behavior' => 0,
      'active' => 1,
      ),

    'pernambuco' => Array(
      'id_state' => 329,
      'id_country' => 58,
      'id_zone' => 6,
      'name' => 'Pernambuco',
      'iso_code' => 'PE',
      'tax_behavior' => 0,
      'active' => 1,
      ),

    'piaui' => Array(
      'id_state' => 330,
      'id_country' => 58,
      'id_zone' => 6,
      'name' => 'Piauí',
      'iso_code' => 'PI',
      'tax_behavior' => 0,
      'active' => 1,
      ),

    'rio_janeiro' => Array(
      'id_state' => 331,
      'id_country' => 58,
      'id_zone' => 6,
      'name' => 'Rio de Janeiro',
      'iso_code' => 'RJ',
      'tax_behavior' => 0,
      'active' => 1,
      ),

    'rio_grande_norte' => Array(
      'id_state' => 332,
      'id_country' => 58,
      'id_zone' => 6,
      'name' => 'Rio Grande do Norte',
      'iso_code' => 'RN',
      'tax_behavior' => 0,
      'active' => 1,
      ),

    'rio_grande_sul' => Array(
      'id_state' => 333,
      'id_country' => 58,
      'id_zone' => 6,
      'name' => 'Rio Grande do Sul',
      'iso_code' => 'RS',
      'tax_behavior' => 0,
      'active' => 1,
      ),

    'rondonia' => Array(
      'id_state' => 334,
      'id_country' => 58,
      'id_zone' => 6,
      'name' => 'Rondônia',
      'iso_code' => 'RO',
      'tax_behavior' => 0,
      'active' => 1,
      ),

    'roraima' => Array(
      'id_state' => 335,
      'id_country' => 58,
      'id_zone' => 6,
      'name' => 'Roraima',
      'iso_code' => 'RR',
      'tax_behavior' => 0,
      'active' => 1,
      ),

    'santa_catarina' => Array(
      'id_state' => 336,
      'id_country' => 58,
      'id_zone' => 6,
      'name' => 'Santa Catarina',
      'iso_code' => 'SC',
      'tax_behavior' => 0,
      'active' => 1,
      ),

    'sao_paulo' => Array(
      'id_state' => 337,
      'id_country' => 58,
      'id_zone' => 6,
      'name' => 'São Paulo',
      'iso_code' => 'SP',
      'tax_behavior' => 0,
      'active' => 1,
      ),

    'sergipe' => Array(
      'id_state' => 338,
      'id_country' => 58,
      'id_zone' => 6,
      'name' => 'Sergipe',
      'iso_code' => 'SE',
      'tax_behavior' => 0,
      'active' => 1,
      ),

    'tocantins' => Array(
      'id_state' => 339,
      'id_country' => 58,
      'id_zone' => 6,
      'name' => 'Tocantins',
      'iso_code' => 'TO',
      'tax_behavior' => 0,
      'active' => 1,
      ),
    );

    $query = ('SELECT iso_code FROM '._DB_PREFIX_.'state WHERE id_state = 3129');
    $result = Db::getInstance()->getValue($query);
    if($result != 'DF'){
      foreach($states as $state){
        $query = ('SELECT iso_code FROM '._DB_PREFIX_.'state WHERE id_state = '.$state['id_state']);
        $result = Db::getInstance()->getValue($query);
        if($result != $state['iso_code']){
          if($result){
            if(!Db::getInstance()->update('state', array(
              'name' => $state['name'],
              'iso_code' => $state['iso_code'],
            ),"iso_code = '" .$result."'  " )){}
          }else{
            Db::getInstance()->insert('state', array(
            'id_state'     => $state['id_state'],
            'id_country'   => 58,
            'id_zone'      => 6,
            'name'         => $state['name'],
            'iso_code'     => $state['iso_code'],
            'tax_behavior' => 0,
            'active'       => 1,
        ));
          }
        }
      }
    }
  }

  function listen_notification() {
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST' ||
        !isset($_GET['retorno_nfe'], $_GET['order_id'], $_POST['uuid']) ||
        !is_scalar($_GET['order_id']) || !ctype_digit((string) $_GET['order_id']) || (int) $_GET['order_id'] < 1 ||
        !WebmaniaNFeSecurity::validUuid($_POST['uuid']) ||
        !WebmaniaNFeSecurity::equals(Configuration::get($this->name.'uniq_key'), $_GET['retorno_nfe'])) { return; }
    $order_id = (int) $_GET['order_id'];
    $uuid = $_POST['uuid'];
    $invoices = WebmaniaNFeSecurity::invoices(Db::getInstance()->getValue('SELECT nfe_info FROM '._DB_PREFIX_.'orders WHERE id_order = '.$order_id, false));
    $known = false;
    foreach ($invoices as $invoice) { if (isset($invoice['uuid']) && $invoice['uuid'] === $uuid) { $known = true; break; } }
    $token = isset($_GET['wmbr_emission_token']) ? $_GET['wmbr_emission_token'] : '';
    $reservation = WebmaniaNFeEmission::find($order_id, $token);
    if ($reservation && $reservation['phase'] !== 'dispatching') { $reservation = false; }
    if (!$known && !$reservation) { return; }
    if ($reservation && $reservation['uuid'] !== '' && $reservation['uuid'] !== $uuid) { return; }
    $api = new NFe($this->settings);
    $response = $api->consultaNotaFiscal($uuid);
    if (isset($response->error) || !isset($response->uuid) || $response->uuid !== $uuid) { return; }
    $response_id = isset($response->ID) ? $response->ID : (isset($response->data->ID) ? $response->data->ID : null);
    if ($response_id !== null && (!is_scalar($response_id) || (string) $response_id !== (string) $order_id)) { return; }
    // A saved emission UUID also proves ownership when the consultation omits ID.
    $allowInsert = $reservation && (
      ((is_string($response_id) || is_int($response_id)) && (string) $response_id === (string) $order_id) ||
      ($response_id === null && $reservation['uuid'] !== '' && $reservation['uuid'] === $uuid)
    );
    if (!$known && !$allowInsert) { return; }
    // A rejected attempt is not an issued invoice. Keep updates to existing history compatible.
    if (!$known && isset($response->status) && $response->status === 'reprovado') {
      WebmaniaNFeEmission::release($order_id, $token);
      return;
    }
    $document = '';
    if ($allowInsert) {
      $client = isset($response->data->cliente) && is_object($response->data->cliente) ? $response->data->cliente : null;
      if ($client && isset($client->cnpj) && is_scalar($client->cnpj)) { $document = (string) $client->cnpj; }
      elseif ($client && isset($client->cpf) && is_scalar($client->cpf)) { $document = (string) $client->cpf; }
      else {
        $document = Db::getInstance()->getValue('SELECT c.nfe_document_number FROM '._DB_PREFIX_.'customer c INNER JOIN '._DB_PREFIX_.
          'orders o ON o.id_customer = c.id_customer WHERE o.id_order = '.$order_id, false);
      }
    }
    if ($this->persistNfeResponse($order_id, $response, is_scalar($document) ? (string) $document : '', $allowInsert, false,
        $reservation ? $reservation['requested_at'] : null) && $reservation) {
      WebmaniaNFeEmission::release($order_id, $token);
    }
  }

  function get_nfe_urls($ids, $type) {
    $failure = array('result' => false, 'file' => '', 'filename' => '');
    if (!is_array($ids) || !in_array($type, array('normal', 'simples', 'etiqueta'), true)) { return $failure; }
    $api = new NFe($this->settings);
    $directory = WebmaniaNFeSecurity::privateDirectory();
    $pdf = new PDFMerger();
    $sources = array();
    foreach ($ids as $id) {
        if (!is_scalar($id) || !ctype_digit((string) $id)) { continue; }
        $invoices = WebmaniaNFeSecurity::invoices(Db::getInstance()->getValue('SELECT nfe_info FROM '._DB_PREFIX_.'orders WHERE id_order = '.(int) $id));
        $data = $invoices ? end($invoices) : array();
        if (!is_array($data) || !isset($data['status'], $data['url_danfe']) || $data['status'] !== 'aprovado') { continue; }
        $url = $data['url_danfe'];
        if ($type === 'simples') { $url = !empty($data['url_danfe_simplificada']) ? $data['url_danfe_simplificada'] : str_replace('/danfe/', '/danfe/simples/', $url); }
        if ($type === 'etiqueta') { $url = !empty($data['url_danfe_etiqueta']) ? $data['url_danfe_etiqueta'] : str_replace('/danfe/', '/danfe/etiqueta/', $url); }
        $content = $api->curl_get_file_contents($url);
        if ($content === false) { continue; }
        $source = $directory . WebmaniaNFeSecurity::randomToken() . '.pdf';
        if (file_put_contents($source, $content) !== false) { $sources[] = $source; $pdf->addPDF($source, 'all'); }
    }
    $filename = WebmaniaNFeSecurity::randomToken() . '.pdf';
    $path = $directory . $filename;
    $result = false;
    try { if ($sources) { $result = $pdf->merge('file', $path); } }
    catch (Exception $e) { $result = false; }
    catch (Throwable $e) { $result = false; }
    foreach ($sources as $source) { if (is_file($source)) { unlink($source); } }
    if (!$result) { if (is_file($path)) { unlink($path); } rmdir($directory); return $failure; }
    return array('result' => $result, 'file' => $path, 'filename' => $filename);

}

  public function showDanfe($file, $filename, $download = false){

    header('Content-Type: application/pdf');
    if($download) header("Content-Disposition: attachment; filename=$filename");
    else header("Content-Disposition: inline; filename=$filename");
    header('Content-Length: ' . filesize($file));
    header('Pragma: no-cache');
    header('Expires: 0');

    if (ob_get_level()) { ob_clean(); }
    flush();
    $handle = fopen($file, "rb");
    while (!feof($handle)) {
      echo fread($handle, 8192);
      flush();
    }
    fclose($handle);

    unlink($file);
    rmdir(dirname($file));
    die();

  }

  public function createSecureTokenDFe( $password, $uuid ){

    $password = preg_replace("/[^0-9]/", '', $password);
    $key = hash('sha256', $password . ':' . $uuid, true);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
    $encryptedData = openssl_encrypt(time(), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    $tokenData = json_encode(['data' => base64_encode($encryptedData), 'iv' => base64_encode($iv)]);
    return urlencode(base64_encode($tokenData));

  }

  public function getCustomerDocs($customer, $order){

    $customer_docs = array('cpf' => '', 'cnpj' => '', 'razao_social' => '', 'ie' => '');
    // Get Custom Fields
    $cpf_cnpj_status = Configuration::get($this->name.'cpf_cnpj_status');

    if ($cpf_cnpj_status == 'on'){

      $result = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'customer WHERE id_customer = ' . (int)$customer->id);
      if (is_array($result)) {
        $customer_docs = array(
          'cpf'          => $result['nfe_document_number'],
          'cnpj'         => $result['nfe_document_number'],
          'razao_social' => $result['nfe_razao_social'],
          'ie'           => $result['nfe_pj_ie']
        );
      }

    } else {

      $customer_docs = array('cpf' => '', 'cnpj' => '', 'razao_social' => '', 'ie' => '');

      if(!Configuration::get($this->name.'docscolumn_cpf')){

        $fields = array_map('strval', array(
          'cpf'            => Configuration::get($this->name.'cpf_field'),
          'cnpj'           => Configuration::get($this->name.'cnpj_field'),
          'razao_social'   => Configuration::get($this->name.'razao_social_field'),
          'ie'             => Configuration::get($this->name.'cnpj_ie_field')
        ));

        $address = new Address($order->id_address_delivery);
        $customer_custom = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'customer WHERE id_customer = ' . (int)$customer->id);
        $address_custom = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'address WHERE id_address = ' . (int)$address->id);

        if(isset($customer_custom[$fields['cpf']])) $customer_docs['cpf'] = $customer_custom[$fields['cpf']];
        if(isset($customer_custom[$fields['cnpj']])) $customer_docs['cnpj'] = $customer_custom[$fields['cnpj']];
        if(isset($customer_custom[$fields['razao_social']])) $customer_docs['razao_social'] = $customer_custom[$fields['razao_social']];
        if(isset($customer_custom[$fields['ie']])) $customer_docs['ie'] = $customer_custom[$fields['ie']];

        // Identify if customer custom fields is located in _address database
        if (isset($address_custom[$fields['cpf']])) $customer_docs['cpf'] = $address_custom[$fields['cpf']];
        if (isset($address_custom[$fields['cnpj']])) $customer_docs['cnpj'] = $address_custom[$fields['cnpj']];
        if (isset($address_custom[$fields['razao_social']])) $customer_docs['razao_social'] = $address_custom[$fields['razao_social']];
        if (isset($address_custom[$fields['ie']])) $customer_docs['ie'] = $address_custom[$fields['ie']];

      }else{

            $map = array(
                'cpf' => array(
                    'table'  => Configuration::get($this->name.'docstable_cpf'),
                    'column' => Configuration::get($this->name.'docscolumn_cpf')
                ),
                'cnpj' => array(
                    'table'  => Configuration::get($this->name.'docstable_cnpj'),
                    'column' => Configuration::get($this->name.'docscolumn_cnpj')
                ),
                'razao_social' => array(
                    'table'  => Configuration::get($this->name.'docstable_rs'),
                    'column' => Configuration::get($this->name.'docscolumn_rs'),
                ),
                'ie' => array(
                    'table'  => Configuration::get($this->name.'docstable_ie'),
                    'column' => Configuration::get($this->name.'docscolumn_ie')
                )
            );

            foreach($map as $key => $doc){

                $table  = $doc['table'];
                $column = $doc['column'];

                // Mapping values are saved through the settings form (isGenericName accepts quotes and
                // spaces): only plain identifiers are interpolated, so the setting cannot carry SQL.
                if ($this->isSqlIdentifier($table) && $this->isSqlIdentifier($column) && (int) $customer->id > 0){

                    $query = 'SELECT `'.$column.'` FROM `'.$table.'` WHERE id_customer = '.(int) $customer->id;
                    $val = Db::getInstance()->executeS($query);

                    foreach (is_array($val) ? $val : array() as $value) {
                        if (array_key_exists($column, $value)) { $customer_docs[$key] = $value[$column]; }
                    }

                }

            }

        }

    }

    return $customer_docs;
  }

} 
