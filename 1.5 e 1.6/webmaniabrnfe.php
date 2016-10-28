<?php
if(!defined('_PS_VERSION_')){
  exit;
}

if(!defined('_MAIN_PS_VERSION_'))
define('_MAIN_PS_VERSION_', substr(_PS_VERSION_, 0, 3));
require_once('sdk/NFe.php');

class WebmaniaBrNFe extends Module{


  public function __construct(){

    $this->name = 'webmaniabrnfe';
    $this->tab = 'administration';
    $this->version = '2.0';
    $this->author = 'WebmaniaBR';
    $this->need_instance = 0;
    $this->ps_versions_compliancy = array('min' => '1.4', 'max' => _PS_VERSION_);
    $this->bootstrap = true;

    //Auth values
    $this->settings = array(
        'oauth_access_token'        => Configuration::get($this->name.'access_token'),
        'oauth_access_token_secret' => Configuration::get($this->name.'access_token_secret'),
        'consumer_key'              => Configuration::get($this->name.'consumer_key'),
        'consumer_secret'           => Configuration::get($this->name.'consumer_secret'),
    );


    $this->initContext();
    parent::__construct();


    if(Module::isInstalled('webmaniabrnfe', 'AdminModules') && isset($this->context->controller->controller_type) &&  $this->context->controller->controller_type == 'admin'){
      $this->checkAuthentication();
      $this->checkCURL();
    }

    $this->displayName = $this->l('WebmaniaBR NF-e');
    $this->description = $this->l('Módulo de emissão de Nota Fiscal Eletrônica para PrestaShop através da REST API da WebmaniaBR®.');
    $this->confirmUninstall = $this->l('Tem certeza que deseja desinstalar este módulo?');

  }

  public function install(){

    if(_MAIN_PS_VERSION_ != '1.4'){
      if(Shop::isFeatureActive()){
         Shop::setContext(Shop::CONTEXT_ALL);
       }
    }

    $configValues = $this->getConfigInitValues();
    foreach($configValues as $key => $value){
      if(!Configuration::get($key)){
        Configuration::updateValue($key, $value);
      }
    }

    $hooks_main = array(
      'backOfficeHeader',
      'displayBackOfficeHeader',
      'displayHeader',
      'displayCustomerAccount',
      'actionPaymentConfirmation',
      'displayAdminProductsExtra',
      'actionProductUpdate',
      'actionAdminOrdersListingFieldsModifier',
      'createAccountForm',
      'actionCustomerAccountAdd',
      'displayCustomerAccount',
      'displayInvoice',
    );

    $hooks_1_4 = array(
      'header',
      'backOfficeHeader',
      'createAccountForm',
      'createAccount',
      'customerAccount',
    );


    if(!parent::install() || !$this->alterTable('add')){
      return false;
    }

    if(_MAIN_PS_VERSION_ == '1.4'){

      foreach($hooks_1_4 as $hook){
        echo $hook;
        if(!$this->registerHook($hook)){
          return false;
        }
      }

    }else{

      foreach($hooks_main as $hook){
        if(!$this->registerHook($hook)){
          return false;
        }
      }

    }

    Configuration::updateValue('PS_GUEST_CHECKOUT_ENABLED', 0);
    return true;
  }



  public function uninstall(){

    if(!parent::uninstall() || !$this->alterTable('remove')){
      return false;
    }

    $config_fields = $this->getConfigInitValues();
    foreach($config_fields as $key => $value){
      if(!Configuration::deleteByName($key)){
        return false;
      }
    }
    return true;

  }


  public function getContent(){

    $output = null;
    if(Tools::isSubmit('submit'.$this->name)){
      $fields = $this->getConfigInitValues();
      foreach($fields as $key => $value){
        $input_value = strval(Tools::getValue($key));
        if($input_value){
          if(Validate::isGenericName($input_value)){
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

    if(_MAIN_PS_VERSION_ == '1.4'){
      $fields = $this->getConfigInitValues();
      foreach($fields as $key => $value){
        $smarty_arr[$key] = Configuration::get($key);
      }
      global $smarty;
      $smarty->assign($smarty_arr);
      return $this->display(__FILE__, 'views/templates/configuration.1.4.tpl');
    }
    return $output.$this->displayForm();
  }

  public function displayForm(){
    $default_language = (int)Configuration::get('PS_LANG_DEFAULT');

    $select_options = array(
      array(
        'id_option' => -1,
        'name' => $this->l('Selecionar Origem dos Produtos')
      ),
      array(
        'id_option' => '00',
        'name' => $this->l('0 - Nacional, exceto as indicadas nos códigos 3, 4, 5, e 8')
      ),
      array(
        'id_option' => 1,
        'name' => $this->l('1 - Estrangeira - Importação direta, exceto a indicada no código 6')
      ),
      array(
        'id_option' => 2,
        'name' => $this->l('2 - Estrangeira - Adquirida no mercado interno, exceto a indica no código 7')
      ),
      array(
        'id_option' => 3,
        'name' => $this->l('3 - Nacional, mercadoria ou bem com Conteúdo de Importação superior a 40% e inferior ou igual a 70%')
      ),
      array(
        'id_option' => 4,
        'name' => $this->l('4 - Nacional, cuja produção tenha sido feita em conformidade com os processos produtivos básicos de que tratam as legislações citadas nos Ajustes')
      ),
      array(
        'id_option' => 5,
        'name' => $this->l('5 - Nacional, mercadoria ou bem com Conteúdo de Importação inferior ou igual a 40%')
      ),
      array(
        'id_option' => 6,
        'name' => $this->l('6 - Estrangeira - Importação direta, sem similar nacional, constante em lista da CAMEX e gás natural')
      ),
      array(
        'id_option' => 7,
        'name' => $this->l('7 - Estrangeira - Adquirida no mercado interno, sem similar nacional, constante lista CAMEX e gás natural')
      ),
      array(
        'id_option' => 8,
        'name' => $this->l('8 - Nacional, mercadoria ou bem com Conteúdo de Importação superior a 70%')
      )
    );

    $fields_form[0]['form'] = array(
      'legend' => array(
        'title' => $this->l('Credenciais de Acesso'),
      ),
      'input' => array(
        array(
          'type' => 'text',
          'label' => $this->l('Consumer Key'),
          'name' => $this->name.'consumer_key',
          'size' => 50,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Consumer Secret'),
          'name' => $this->name.'consumer_secret',
          'size' => 50,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Access Token'),
          'name' => $this->name.'access_token',
          'size' => 50,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Access Token Secret'),
          'name' => $this->name.'access_token_secret',
          'size' => 50,
          'required' => true
        ),
        array(
          'type' => 'radio',
          'label' => $this->l('Ambiente Sefaz'),
          'name' => $this->name.'sefaz_env',
          'required' => true,
          'values' => array(
            array(
              'id' => 'production',
              'value' => '1',
              'label' => $this->l('Produção'),
            ),
            array(
              'id' => 'development',
              'value' => '2',
              'label' => $this->l('Desenvolvimento'),
            ),
          )
        ),
      ),

    );

    $fields_form[1]['form'] = array(
      'legend' => array(
        'title' => $this->l('Configuração Padrão')
      ),
      'input' => array(
        array(
          'type' => 'radio',
          'label' => $this->l('Emissão Automática'),
          'name' => $this->name.'automatic_emit',
          'desc' => $this->l('Emitir automaticamente a NF-e sempre que que um pagamento for confirmado'),
          'values' => array(
            array(
              'id' => 'on',
              'value' => 'on',
              'label' => $this->l('Ativado'),
            ),
            array(
              'id' => 'off',
              'value' => 'off',
              'label' => $this->l('Desativado'),
            ),
          )
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Natureza da Operação'),
          'name' => $this->name.'operation_type',
          'size' => 50,
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Classe de Imposto'),
          'name' => $this->name.'tax_class',
          'size' => 50,
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Código de Barras EAN'),
          'name' => $this->name.'ean_barcode',
          'size' => 50,

        ),
        array(
          'type' => 'text',
          'label' => $this->l('Código NCM'),
          'name' => $this->name.'ncm_code',
          'size' => 50,

        ),
        array(
          'type' => 'text',
          'label' => $this->l('Código CEST'),
          'name' => $this->name.'cest_code',
          'size' => 50,

        ),
        array(
          'type' => 'select',
          'label' => $this->l('Origem dos Produtos'),
          'name' => $this->name.'product_source',
          'options' => array(
            'query' => $select_options,
            'id' => 'id_option',
            'name' => 'name'
          )

        ),
      ),

    );

    $fields_form[2]['form'] = array(
      'legend' => array(
        'title' => $this->l('Informações Complementares (Opcional)'),
      ),
      'input' => array(
        array(
          'type' => 'textarea',
          'label' => $this->l('Informações ao Fisco'),
          'name' => $this->name.'fisco_inf',
          'size' => 2000,
          'cols' => 50,
          'required' => false
        ),
        array(
          'type' => 'textarea',
          'label' => $this->l('Informações Complementares ao Consumidor'),
          'name' => $this->name.'cons_inf',
          'size' => 2000,
          'cols' => 50,
          'required' => false
        ),
      ),

    );

    $fields_form[3]['form'] = array(
      'legend' => array(
        'title' => $this->l('Opções Adicionais'),
      ),
      'input' => array(
        array(
          'type' => 'radio',
          'label' => $this->l('Habilitar Máscara de Campos'),
          'name' => $this->name.'mask_fields',
          'required' => true,
          'values' => array(
            array(
              'id' => 'mask_on',
              'value' => 'on',
              'label' => $this->l('Ativado'),
            ),
            array(
              'id' => 'mask_off',
              'value' => 'off',
              'label' => $this->l('Desativado'),
            ),
          )
        ),
        array(
          'type' => 'radio',
          'label' => $this->l('Habilitar Preenchimento Automático de Endereço'),
          'name' => $this->name.'fill_address',
          'required' => true,
          'values' => array(
            array(
              'id' => 'fill_address_on',
              'value' => 'on',
              'label' => $this->l('Ativado'),
            ),
            array(
              'id' => 'fill_address_off',
              'value' => 'off',
              'label' => $this->l('Desativado'),
            ),
          )
        ),
      )
    );



    if(_MAIN_PS_VERSION_ == '1.6' || _MAIN_PS_VERSION_ == '1.5'){
      $fields_form[4]['form'] = array(
        'legend' => array(
          'title' => $this->l('Salvar alteraçoes')
        ),
        'submit' => array(
          'title' => $this->l('Salvar'),
          'class' => 'button'
        )
      );
    }



    $helper = new HelperForm();

    $helper->module = $this;
    $helper->name_controller = $this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');
    $helper->allow_employee_form_lang = $default_language;

    $helper->title = $this->displayName;
    $helper->show_toolbar = true;
    $helper->toolbar_scroll = true;
    $helper->submit_action = 'submit'.$this->name;
    if(_MAIN_PS_VERSION_ == '1.5'){
      $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
    }
    $helper->toolbar_btn = array(
      'save' =>
      array(
        'desc' => $this->l('Salvar'),
        'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'),
      ),
      'back' => array(
        'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
        'desc' => $this->l('Voltar para a listagem')
      )
    );

    $helper->fields_value = $this->getConfigFieldsValues();
    return $helper->generateForm($fields_form);
  }

  public function getConfigFieldsValues(){

    return array(
      $this->name.'consumer_key' => Configuration::get($this->name.'consumer_key'),
      $this->name.'consumer_secret' => Configuration::get($this->name.'consumer_secret'),
      $this->name.'access_token' => Configuration::get($this->name.'access_token'),
      $this->name.'access_token_secret' => Configuration::get($this->name.'access_token_secret'),
      $this->name.'sefaz_env' => Configuration::get($this->name.'sefaz_env'),
      $this->name.'automatic_emit' => Configuration::get($this->name.'automatic_emit'),
      $this->name.'operation_type' => Configuration::get($this->name.'operation_type'),
      $this->name.'tax_class' => Configuration::get($this->name.'tax_class'),
      $this->name.'ean_barcode' => Configuration::get($this->name.'ean_barcode'),
      $this->name.'ncm_code' => Configuration::get($this->name.'ncm_code'),
      $this->name.'cest_code' => Configuration::get($this->name.'cest_code'),
      $this->name.'product_source' => Configuration::get($this->name.'product_source'),
      $this->name.'person_type_fields' => Configuration::get($this->name.'person_type_fields'),
      $this->name.'mask_fields' => Configuration::get($this->name.'mask_fields'),
      $this->name.'fill_address' => Configuration::get($this->name.'fill_address'),
      $this->name.'fisco_inf' => Configuration::get($this->name.'fisco_inf'),
      $this->name.'cons_inf' => Configuration::get($this->name.'cons_inf'),
    );

  }

  function getConfigInitValues(){

    return array(
      $this->name.'consumer_key' => '',
      $this->name.'consumer_secret' => '',
      $this->name.'access_token' => '',
      $this->name.'access_token_secret' => '',
      $this->name.'sefaz_env' => '2',
      $this->name.'automatic_emit' => 'off',
      $this->name.'operation_type' => '',
      $this->name.'tax_class' => '',
      $this->name.'ean_barcode' => '',
      $this->name.'ncm_code' => '',
      $this->name.'cest_code' => '',
      $this->name.'product_source' => '0',
      $this->name.'person_type_fields' => 'on',
      $this->name.'mask_fields' => 'off',
      $this->name.'fill_address' => 'off',
      $this->name.'fisco_inf' => '',
      $this->name.'cons_inf' => '',
    );

  }

  // Retrocompatibility 1.4/1.5
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


  /**********************************************************
  *******************HOOKED FUNCTIONS **********************
  ***********************************************************/


  public function hookBackOfficeHeader($params){

    $this->processBulkEmitirNfe();
    $this->updateNfe();

    if(_MAIN_PS_VERSION_ == '1.6'){
      $controller_name = $this->context->controller->controller_name;
      $this->context->controller->addJquery();
      if($controller_name == 'AdminCustomers'){
        $this->context->controller->addJS($this->_path.'/js/jquery.mask.min.js', 'all');
      }
      $this->context->controller->addJS($this->_path.'/js/scripts_bo.1.6.js', 'all');
      $this->context->controller->addCSS($this->_path.'/views/css/style.css', 'all');
    }

    //Support to PS 1.5
    if(_MAIN_PS_VERSION_ == '1.5'){
      $this->rearrangeStates();
      $this->context->controller->addJquery();
      $controllerName = $this->context->controller->controller_name;
      if(($controllerName == 'AdminOrders' && !Tools::getValue('id_order')) || $controllerName = 'AdminCustomers' || $controllerName = 'AdminAddresses'){
        $this->context->controller->addJS($this->_path.'/js/scripts_bo.1.5.js', 'all');
        $this->context->controller->addJS($this->_path.'/js/jquery.mask.min.js', 'all');
        $this->context->controller->addCSS($this->_path.'/views/css/style.css', 'all');
      }
    }

    //Support PS 1.4
    if(_MAIN_PS_VERSION_ == '1.4'){

      $this->updateProduct14();

      $this->displayMessageCertificado();
      return '<link rel="stylesheet" type="text/css" href="'.$this->_path.'/views/css/style.css" />
                <script src="'.$this->_path.'/js/scripts_bo.1.4.js"></script>';

    }

    return true;
  }

  public function hookDisplayBackOfficeHeader($params){

    $this->hookBackOfficeHeader($params);
    $this->displayMessageCertificado();


  }

  public function hookDisplayInvoice($params) {

    $order_id = $params['id_order'];

    $nfe_info = unserialize(Db::getInstance()->getValue("SELECT nfe_info FROM "._DB_PREFIX_."orders WHERE id_order = $order_id" ));

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

      $this->context->smarty->assign(array(
        'nfe_info_arr' => $nfe_info,
        'url' => $url,
      ));
      echo  $this->display(__FILE__, 'nfe_info_table.tpl');

      $order = new Order($order_id);
      $customer_id = $order->id_customer;
      $delivery_address_id = $order->id_address_delivery;
      $invoice_address_id = $order->id_address_invoice;

      $address_custom = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'address WHERE id_address = ' . (int)$delivery_address_id);
      $state = new State($address_custom['id_state']);
      $this->context->smarty->assign(array(
        'user_address_info' => $address_custom,
        'state' => $state->iso_code,
      ));

      echo $this->display(__FILE__, 'user_address_info.tpl');

    }

  }



  public function hookHeader($params){

      Tools::addCSS($this->_path.'/views/css/style.css', 'all');
      Tools::addJS(($this->_path).'js/scripts_fo.1.4.js');
      Tools::addJS(($this->_path).'js/correios.min.js');
      Tools::addJS(($this->_path).'js/jquery.mask.min.js');
  }

  public function hookDisplayHeader($params){

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

    if(_MAIN_PS_VERSION_ == '1.6'){

      if(Configuration::get($this->name.'mask_fields') == 'on'){
        Media::addJsDef(array('mask_doc_fields' => true));
      }

      if(Configuration::get($this->name.'fill_address') == 'on'){
        Media::addJsDef(array('fill_address' => true));
      }

      $this->context->controller->addJS($this->_path.'/js/scripts_fo.1.6.js', 'all');
    }

    $this->context->controller->addJS($this->_path.'/js/correios.min.js', 'all');
    $this->context->controller->addJS($this->_path.'/js/jquery.mask.min.js', 'all');
    $this->context->controller->addCSS($this->_path.'/views/css/style.css', 'all');

  }

  /*
   * Add custom column to admin orders page
   */

  public function hookActionAdminOrdersListingFieldsModifier($params){

    $params['fields'] = array_slice($params['fields'], 0, 2, true) +
      array('nfe_issued' => array(
        'title' => 'Status NF-e',
        'class' => 'extended-width-nfe',
        'align' => 'text-center',
        'search' => '',
        'callback' => 'isNfeIssued',
        'callback_object' => $this)) + array_slice($params['fields'], 2, count($params['fields']) - 1);

    }

  public function hookActionPaymentConfirmation($params){

    $orderID = $params['id_order'];
    $automatic = Configuration::get($this->name.'automatic_emit');
    if($automatic == 'on'){
      $this->emitirNfe($orderID);
    }
    return true;

  }

  public function hookDisplayAdminProductsExtra($params){

    if(Validate::isLoadedObject($product = new Product((int)Tools::getValue('id_product')))){
      $values = $this->getProductNfeValues((int)Tools::getValue('id_product'));
      $this->context->smarty->assign(array(
        'tax_class' => $values[0]['nfe_tax_class'],
        'ean_bar_code' => $values[0]['nfe_ean_bar_code'],
        'ncm_code' => $values[0]['nfe_ncm_code'],
        'cest_code' => $values[0]['nfe_cest_code'],
        'product_source' => $values[0]['nfe_product_source'],
        'ignorar_nfe' => $values[0]['nfe_ignorar_nfe']
      ));
    }

    if(_MAIN_PS_VERSION_ == '1.5'){
      return $this->display(__FILE__, 'nfe_product_tab.1.5.tpl');
    }else{
      return $this->display(__FILE__, 'nfe_product_tab.1.6.tpl');
    }

  }

  public function hookActionProductUpdate($params){

    $id_product = (int) Tools::getValue('id_product');
    if(!Db::getInstance()->update('product', array(
      'nfe_tax_class'=> pSQL(Tools::getValue('nfe_tax_class')),
      'nfe_ean_bar_code' => pSQL(Tools::getValue('nfe_ean_bar_code')),
      'nfe_ncm_code' => pSQL(Tools::getValue('nfe_ncm_code')),
      'nfe_cest_code' => pSQL(Tools::getValue('nfe_cest_code')),
      'nfe_product_source' => pSQL(Tools::getValue('nfe_product_source')),
      'nfe_ignorar_nfe' => pSQL(Tools::getValue('nfe_ignorar_nfe'))
    ),'id_product = ' .$id_product )){
      $this->context->controller->_errors[] = Tools::displayError('Error: ').mysql_error();
    }

  }

  public function updateProduct14(){

    $product_id = Tools::getValue('id_product');
    if(Tools::isSubmit('submitCustomProduct14')){
      $product_id = Tools::getValue('id_product');
      $update_values = array(
        'nfe_tax_class'=> pSQL(Tools::getValue('nfe_tax_class')),
        'nfe_ean_bar_code' => pSQL(Tools::getValue('nfe_ean_bar_code')),
        'nfe_ncm_code' => pSQL(Tools::getValue('nfe_ncm_code')),
        'nfe_cest_code' => pSQL(Tools::getValue('nfe_cest_code')),
        'nfe_product_source' => pSQL(Tools::getValue('nfe_product_source'))
      );

      if(!Db::getInstance()->autoExecute(_DB_PREFIX_.'product', $update_values, 'UPDATE', 'id_product = ' .(int)$product_id)){
        $this->errors[] = Db::getInstance()->getMsgError();
      }
    }

  }

  public function hookcreateAccountForm(){

      if(_MAIN_PS_VERSION_ == '1.4'){
        return $this->display(__FILE__, '/views/templates/hook/document_types.1.4.tpl');
      }

      if(_MAIN_PS_VERSION_ == '1.5'){
        return $this->display(__FILE__, 'document_types.1.5.tpl');

        if(Configuration::get($this->name.'mask_fields') == 'on'){
          $var = 'on';
        }else{
          $var = 'off';
        }

        $this->smarty->assign('custom_var', $var);
      }

      if(_MAIN_PS_VERSION_ == '1.6'){

        if(Configuration::get($this->name.'mask_fields') == 'on'){
          $var = 'on';
        }else{
          $var = 'off';
        }

        $this->smarty->assign('custom_var', $var);

        return $this->display(__FILE__, 'document_types.1.6.tpl');
      }

  }

  public function hookActionCustomerAccountAdd($params){

    $customer_data = $params['_POST'];
    $customer_id = (int)$params['newCustomer']->id;
    $DB_data = array();

    if($customer_data['document_type'] == 'cpf'){
      $DB_data = array(
        'nfe_document_type'   => pSQL($customer_data['document_type']),
        'nfe_document_number' => pSQL($customer_data['cpf']),
      );
    }elseif($customer_data['document_type'] == 'cnpj'){
      $DB_data = array(
        'nfe_document_type'   => pSQL($customer_data['document_type']),
        'nfe_document_number' => pSQL($customer_data['cnpj']),
        'nfe_razao_social'    => pSQL($customer_data['razao_social']),
        'nfe_pj_ie'           => pSQL($customer_data['cnpj_ie']),
      );
    }

    if(!Db::getInstance()->update('customer', $DB_data, 'id_customer = ' .$customer_id )){
      $this->context->controller->_errors[] = Tools::displayError('Error: ').mysql_error();
    }

  }

  public function hookDisplayCustomerAccount(){

      if(_MAIN_PS_VERSION_ == '1.5'){
        $redirect_url = $this->context->link->getModuleLink('webmaniabrnfe','view15');
        $this->smarty->assign('redirect_url', $redirect_url);
        return $this->display(__FILE__, 'account_list_item.1.5.tpl');
      }

      if(_MAIN_PS_VERSION_ == '1.6'){
        $redirect_url = $this->context->link->getModuleLink('webmaniabrnfe','view16');
        $this->smarty->assign('redirect_url', $redirect_url);
        return $this->display(__FILE__, 'account_list_item.1.6.tpl');
      }

  }

  public function hookCustomerAccount(){

      $redirect_url = __PS_BASE_URI__.'modules/'.$this->name.'/controllers/front/editar_documentos.php';
      $this->context->smarty->assign('redirect_url', $redirect_url);

      return $this->display(__FILE__, '/views/templates/hook/account_list_item.tpl');

  }

  public function hookCreateAccount($params){

    $customer_data = $params['_POST'];
    $customer_id = (int)$params['newCustomer']->id;

    if($customer_data['document_type'] == 'cpf'){
      $document_type = pSQL($customer_data['document_type']);
      $document_number = pSQL(preg_replace("/[^0-9]/","",$customer_data['cpf']));

      $query = "UPDATE ". _DB_PREFIX_ ."customer SET nfe_document_type = '".$document_type."', nfe_document_number = '".$document_number."' WHERE id_customer = " . $customer_id;

      $result = Db::getInstance()->executeS($query);

    }elseif($customer_data['document_type'] == 'cnpj'){
      $document_type = pSQL($customer_data['document_type']);
      $document_number = pSQL(preg_replace("/[^0-9]/","",$customer_data['cnpj']));
      $razao_social = pSQL($customer_data['razao_social']);
      $cnpj_ie = pSQL($customer_data['cnpj_ie']);

      $query = "UPDATE ". _DB_PREFIX_ ."customer SET nfe_document_type = '".$document_type."', nfe_document_number = '".$document_number."', nfe_razao_social = '".$razao_social."', nfe_pj_ie = '".$cnpj_ie."' WHERE id_customer = " . $customer_id;

      $result = Db::getInstance()->executeS($query);
    }
    return true;

  }
  /**********************************************************
  *******************END HOOKED FUNCTIONS *******************
  ***********************************************************/

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

    $order = new Order($orderID);
    $discounts = $order->getDiscounts(true);
    $discounts_applied = array(); // Only percentage

    foreach($discounts as $discount){
      $cart_rule = new CartRule($discount['id_cart_rule']);
      if($cart_rule->reduction_percent > 0){
        $discounts_applied[] = $cart_rule->reduction_percent;
      }
    }

    //Version Compliance
    if(_MAIN_PS_VERSION_ == '1.4'){
      $customer = new Customer($order->id_customer);
    }else{
      $customer = $order->getCustomer();
    }

    $address = new Address($order->id_address_delivery);
    $state = new State($address->id_state);
    $products = $order->getProducts();
    $customer_custom = Db::getInstance()->getRow('SELECT nfe_document_type, nfe_document_number, nfe_razao_social, nfe_pj_ie FROM '._DB_PREFIX_.'customer WHERE id_customer = ' . (int)$customer->id);
    $address_custom = Db::getInstance()->getRow('SELECT address_number FROM '._DB_PREFIX_.'address WHERE id_address = ' . (int)$address->id);
    $tipo_pessoa = $customer_custom['nfe_document_type'];
    $data = array(
        'ID' => $order->id, // Número do pedido
        'operacao' => 1, // Tipo de Operação da Nota Fiscal
        'natureza_operacao' => Configuration::get($this->name.'operation_type'), // Natureza da Operação
        'modelo' => 1, // Modelo da Nota Fiscal (NF-e ou NFC-e)
        'emissao' => 1, // Tipo de Emissão da NF-e
        'finalidade' => 1, // Finalidade de emissão da Nota Fiscal
        'ambiente' => (int)Configuration::get($this->name.'sefaz_env') // Identificação do Ambiente do Sefaz //1 for production, 2 for development
     );

     $data['pedido'] = array(
         'pagamento' => 0, // Indicador da forma de pagamento
         'presenca' => 2, // Indicador de presença do comprador no estabelecimento comercial no momento da operação
         'modalidade_frete' => 0, // Modalidade do frete
         'frete' => number_format($order->total_shipping, 2), // Total do frete
         'desconto' => number_format($order->total_discounts_tax_incl, 2), // Total do desconto
         'total' => number_format($order->total_paid_tax_incl, 2),  // Total do pedido - sem descontos
     );

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

     //Cliente
     if ($tipo_pessoa == 'cpf'){
         $data['cliente'] = array(
             'cpf' => $this->cpf($customer_custom['nfe_document_number']), // (pessoa fisica) Número do CPF
             'nome_completo' => $customer->firstname.' '.$customer->lastname, // (pessoa fisica) Nome completo
             'endereco' => $address->address1, // Endereço de entrega dos produtos
             'complemento' => $address->other, // Complemento do endereço de entrega
             'numero' => $address_custom['address_number'], // Número do endereço de entrega
             'bairro' => $address->address2, // Bairro do endereço de entrega
             'cidade' => $address->city, // Cidade do endereço de entrega
             'uf' => $state->iso_code, // Estado do endereço de entrega
             'cep' => $address->postcode, // CEP do endereço de entrega
             'telefone' => $address->phone, // Telefone do cliente
             'email' => $customer->email // E-mail do cliente para envio da NF-e
         );
     }else if($tipo_pessoa == 'cnpj'){
       $data['cliente'] = array(
         'cnpj' => $this->cnpj($customer_custom['nfe_document_number']), // (pessoa jurídica) Número do CNPJ
         'razao_social' => $customer_custom['nfe_razao_social'], // (pessoa jurídica) Razão Social
         'ie' => $customer_custom['nfe_pj_ie'], // (pessoa jurídica) Número da Inscrição Estadual
         'endereco' => $address->address1, // Endereço de entrega dos produtos
         'complemento' => $address->other, // Complemento do endereço de entrega
         'numero' => $address_custom['address_number'], // Número do endereço de entrega
         'bairro' => $address->address2, // Bairro do endereço de entrega
         'cidade' => $address->city, // Cidade do endereço de entrega
         'uf' => $state->iso_code, // Estado do endereço de entrega
         'cep' => $address->postcode, // CEP do endereço de entrega
         'telefone' => $address->phone, // Telefone do cliente
         'email' => $customer->email // E-mail do cliente para envio da NF-e
       );
     }

     //produtos
     foreach ($products as $key => $item){

       $product_id = $item['product_id'];

       $ignorar = Db::getInstance()->getValue('SELECT nfe_ignorar_nfe FROM '._DB_PREFIX_.'product WHERE id_product = ' . (int)$product_id);

       if($ignorar == '1'){
         $data['pedido']['total'] -= number_format($item['total_price_tax_incl'], 2);

         foreach($discounts_applied as $percentage){
           $data['pedido']['total'] += ($percentage/100)*$item['total_price_tax_incl'];
           $data['pedido']['desconto'] -= ($percentage/100)*$item['total_price_tax_incl'];
         }

         $data['pedido']['total'] = number_format($data['pedido']['total'], 2);
         $data['pedido']['desconto'] = number_format($data['pedido']['desconto'], 2);

         continue;
       }

       /*
       * Specific product values
       */
       $codigo_ean = Db::getInstance()->getValue('SELECT nfe_ean_bar_code FROM '._DB_PREFIX_.'product WHERE id_product = ' . (int)$product_id);
       $codigo_ncm = Db::getInstance()->getValue('SELECT nfe_ncm_code FROM '._DB_PREFIX_.'product WHERE id_product = ' . (int)$product_id);
       $codigo_cest = Db::getInstance()->getValue('SELECT nfe_cest_code FROM '._DB_PREFIX_.'product WHERE id_product = ' . (int)$product_id);
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
       if (!$peso) $peso = '0.100';
       $peso = number_format($peso, 3, '.', '');
       if (!$codigo_ean) $codigo_ean = Configuration::get($this->name.'ean_barcode');
       if (!$codigo_ncm) $codigo_ncm = Configuration::get($this->name.'ncm_code');
       if (!$codigo_cest) $codigo_cest = Configuration::get($this->name.'cest_code');
       if (!is_numeric($origem) || $origem == -1) $origem = Configuration::get($this->name.'product_source');
       if (!$imposto) $imposto = Configuration::get($this->name.'tax_class');

       //Version Compliance
       if(_MAIN_PS_VERSION_ == '1.4'){
         $subtotal = number_format($item['product_price_wt'], 2);
         $total_price = number_format($item['total_wt'], 2);
       }else{
         $subtotal = number_format($item['unit_price_tax_incl'], 2);
         $total_price = number_format($item['total_price_tax_incl'], 2);
       }
       $data['produtos'][] = array(
         'nome' => $item['product_name'], // Nome do produto
         'sku' => $item['product_reference'], // Código identificador - SKU
         'ean' => $codigo_ean, // Código EAN
         'ncm' => $codigo_ncm, // Código NCM
         'cest' => $codigo_cest, // Código CEST
         'quantidade' => $item['product_quantity'], // Quantidade de itens
         'unidade' => 'UN', // Unidade de medida da quantidade de itens
         'peso' => $peso, // Peso em KG. Ex: 800 gramas = 0.800 KG
         'origem' => ($origem == '00' ? 0 : $origem),//Origem do produto
         'subtotal' => number_format($item['unit_price_tax_incl'], 2), // Preço unitário do produto - sem descontos
         'total' => number_format($item['total_price_tax_incl'], 2), // Preço total (quantidade x preço unitário) - sem descontos
         'classe_imposto' => $imposto // Referência do imposto cadastrado
       );
     }

    return $data;
  }

  public function emitirNfe($orderID){

    $webmaniabr = new NFe($this->settings);
    $data = $this->getOrderData($orderID);
    $response = $webmaniabr->emissaoNotaFiscal( $data );

    if (isset($response->error) || $response->status == 'reprovado'){

      if(_MAIN_PS_VERSION_ == '1.4'){
        if(isset($response->error)){
          echo $this->displayError('Erro ao emitir a NF-e do Pedido #'.$orderID.' ( '.$response->error.' )');
        }elseif(isset($response->log->aProt[0]->xMotivo)){
          echo $this->displayError('Erro ao emitir a NF-e do Pedido #'.$orderID. '( '.$response->log->aProt[0]->xMotivo.' )');
        }else{
          echo $this->displayError('Erro ao emitir a NF-e do Pedido #'.$orderID);
        }
      }
      else{
        if(isset($response->error)){
          $this->context->controller->errors[] = Tools::displayError('Erro ao emitir a NF-e do Pedido #'.$orderID.' ( '.$response->error.' )');
        }elseif(isset($response->log->aProt[0]->xMotivo)){
          $this->context->controller->errors[] = Tools::displayError('Erro ao emitir a NF-e do Pedido #'.$orderID. '( '.$response->log->aProt[0]->xMotivo.' )');
        }else{
          $this->context->controller->errors[] = Tools::displayError('Erro ao emitir a NF-e do Pedido #'.$orderID);
        }
      }

    }else{

      $nfe_info = array(
      'status'       => (string) $response->status,
      'chave_acesso' => $response->chave,
      'n_recibo'     => (int) $response->recibo,
      'n_nfe'        => (int) $response->nfe,
      'n_serie'      => (int) $response->serie,
      'url_xml'      => (string) $response->xml,
      'url_danfe'    => (string) $response->danfe,
      'data'         => date('d/m/Y'),
      );

      $existing_nfe_info = unserialize(Db::getInstance()->getValue("SELECT nfe_info FROM "._DB_PREFIX_."orders WHERE id_order = $orderID" ));
      if(!$existing_nfe_info){
        $existing_nfe_info = array();
      }

      $existing_nfe_info[] = $nfe_info;

      $nfe_info_str = serialize($existing_nfe_info);

      if(!Db::getInstance()->update('orders', array('nfe_info' => $nfe_info_str), 'id_order = ' .$orderID )){
        $this->context->controller->errors[] = Tools::displayError('Erro ao atualizar status da NF-e');
      }

      if(!Db::getInstance()->update('orders', array('nfe_issued' => 1), 'id_order = ' .$orderID )){
        $this->context->controller->errors[] = Tools::displayError('Erro ao alterar status da NF-e #'.$orderID);
      }

      if(_MAIN_PS_VERSION_ == '1.4'){
        echo $this->displayConfirmation('NF-e emitida com sucesso do Pedido #'.$orderID);
      }else{
        $this->context->controller->confirmations[] = Tools::displayError('NF-e emitida com sucesso do Pedido #'.$orderID);
      }
    }

    return true;

  }

  public function updateNFe(){
    if(Tools::getValue('atualizar') && Tools::getValue('chave')){
      $chave_acesso = pSql(Tools::getValue('chave'));
      $order_id = (int) Tools::getValue('id_order');
      $webmaniabr = new NFe($this->settings);
      $response = $webmaniabr->consultaNotaFiscal($chave_acesso);

      if (isset($response->error)){

          $this->context->controller->errors[] = Tools::displayError('Erro: '.$response->error);
          return false;

      }else{

        $new_status = $response->status;
        $nfe_data = unserialize(Db::getInstance()->getValue("SELECT nfe_info FROM "._DB_PREFIX_."orders WHERE id_order = $order_id" ));

        foreach($nfe_data as &$order_nfe){
          if($order_nfe['chave_acesso'] == $chave_acesso){
            $order_nfe['status'] = $new_status;
          }
        }

        $nfe_data_str = serialize($nfe_data);

        if(!Db::getInstance()->update('orders', array('nfe_info' => $nfe_data_str),"id_order = '" .$order_id."'  " )){
          $this->context->controller->errors[] = Tools::displayError('Erro ao atualizar informações da nota');
        }else{
          $this->context->controller->confirmations[] = Tools::displayError('NF-e atualizada com sucesso');
        }

      }
    }
  }

  public function processBulkEmitirNfe(){

    if(Tools::isSubmit('bulkEmitirNfe')){
      $values = Tools::getValue('orderBox');
      foreach($values as $orderID){
        $this->emitirNfe($orderID);
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

          if(${'sql_execute_'.$columnsInfo['table_name']} === true){
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
        )
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
          'nfe_ncm_code' => array(
            'name' => 'nfe_ncm_code',
            'sql' => ' ADD COLUMN nfe_ncm_code VARCHAR(20)'
          ),
          'nfe_cest_code' => array(
            'name' => 'nfe_cest_code',
            'sql' => ' ADD COLUMN nfe_cest_code VARCHAR(20)'
          ),
          'nfe_product_source' => array(
            'name' => 'nfe_product_source',
            'sql' => ' ADD COLUMN nfe_product_source VARCHAR(3) DEFAULT -1'
          ),
          'nfe_ignorar_nfe' => array(
            'name' => 'nfe_ignorar_nfe',
            'sql' => ' ADD COLUMN nfe_ignorar_nfe VARCHAR(5) DEFAULT 0'
          )
        ));

        $addressColumnsToAdd = array(
          'table_name' => 'address',
          'columns' => array(
            'address_number' => array(
              'name' => 'address_number',
              'sql' => ' ADD COLUMN address_number VARCHAR(15)'
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

      return array($ordersColumnsToAdd, $productsColumnsToAdd, $addressColumnsToAdd, $customerColumnsToAdd);

  }



  public function getProductNfeValues($productID){

    $result = Db::getInstance()->ExecuteS('SELECT nfe_tax_class, nfe_ean_bar_code, nfe_ncm_code, nfe_cest_code, nfe_product_source, nfe_ignorar_nfe FROM '._DB_PREFIX_.'product WHERE id_product = ' . (int)$productID);
    return $result;

  }

  function is_cpf($cpf = null){

      if(is_array($cpf)) $cpf = $cpf[0];
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

      if(is_array($cnpj)) $cnpj = $cnpj[0];
      $cnpj = sprintf( '%014s', preg_replace( '{\D}', '', $cnpj ) );

      if ( 14 != ( strlen( $cnpj ) ) || ( 0 == intval( substr( $cnpj, -4 ) ) ) ) {
        return false;
      }

      for ( $t = 11; $t < 13; ) {
        for ( $d = 0, $p = 2, $c = $t; $c >= 0; $c--, ( $p < 9 ) ? $p++ : $p = 2 ) {
          $d += $cnpj[ $c ] * $p;
        }

        if ( $cnpj[ ++$t ] != ( $d = ( ( 10 * $d ) % 11 ) % 10 ) ) {
          return false;
        }
      }

      return true;
  }

  function cpf( $string ){

  if (!$string) return;
  $string = $this->clear( $string );
  $string = $this->mask($string,'###.###.###-##');

  return $string;

  }

  function cnpj( $string ){

    if (!$string) return;
    $string = $this->clear( $string );
    $string = $this->mask($string,'##.###.###/####-##');

    return $string;

  }

  function clear( $string ) {

        $string = str_replace( array(',', '-', '!', '.', '/', '?', '(', ')', ' ', '$', 'R$', '€'), '', $string );
        return $string;

	}

	function mask($val, $mask) {
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

    if(_MAIN_PS_VERSION_ == '1.4'){
      $cookie = new Cookie('validate_cookie', time() + 60*60*24);
    }else{
      $cookie = new Cookie('validate_cookie');
    }

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
      if(_MAIN_PS_VERSION_ == '1.4'){
        echo $this->displayError('WebmaniaBR NF-e: Emita um novo Certificado Digital A1 - vencerá em '.$this->getValidadeCertificado().' dias');
      }else{
        $this->context->controller->warnings[] = Tools::displayError('WebmaniaBR NF-e: Emita um novo Certificado Digital A1 - vencerá em '.$this->getValidadeCertificado().' dias');
      }

    }else if(!$validade){
      if(_MAIN_PS_VERSION_ == '1.4'){
        echo $this->displayError('WebmaniaBR NF-e: Certificado Digital A1 vencido. Emita um novo para continuar operando.');
      }else{
        $this->context->controller->errors[] = Tools::displayError('WebmaniaBR NF-e: Certificado Digital A1 vencido. Emita um novo para continuar operando.');
      }
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

}
