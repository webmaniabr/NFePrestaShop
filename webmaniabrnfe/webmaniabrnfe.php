<?php

if(!defined('_PS_VERSION_')){
  exit;
}

//Define main version
if(!defined('_MAIN_PS_VERSION_')) {
  define('_MAIN_PS_VERSION_', substr(_PS_VERSION_, 0, 3));
}

//Webmania SDK to issue invoices
require_once('sdk/NFe.php');

//PDF Merger to print NFe DANFEs
require_once('inc/pdf/PDFMerger.php');

class WebmaniaBrNFe extends Module{


  public function __construct(){

    $this->name = 'webmaniabrnfe';
    $this->tab = 'administration';
    $this->version = '2.9.0';
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

  public function install(){

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
      'displayAdminOrderContentShip'
    );

    //Additional hooks to version 1.7
    if(_MAIN_PS_VERSION_ == '1.7'){
      $hooks_main[] = 'additionalCustomerFormFields';
      $hooks_main[] = 'validateCustomerFormFields';
    }

    //Installation failed
    if(!parent::install() || !$this->alterTable('add')){
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
      $searchQueryBuilder->orderBy('o.`nfe_issued`', $searchCriteria->getOrderWay());
    }

  }

  public function hookDisplayAdminOrderTop($params) {
    
    $order_id = $params['id_order'];

    //Get order's nfe info
    $nfe_info = unserialize(Db::getInstance()->getValue("SELECT nfe_info FROM "._DB_PREFIX_."orders WHERE id_order = $order_id" ));

    $url  = 'index.php?controller=AdminOrders&id_order='.$order_id;
    $url .= '&vieworder&token='.Tools::getAdminTokenLite('AdminOrders');

    //Doesn't have invoices issued
    if (!$nfe_info) {
      return "
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

    //Set danfe simples/etiqueta url if it doesn't exist
    foreach ($nfe_info as $key => $nfe) {
      if (!$nfe['url_danfe_simplificada']) {
        $nfe_info[$key]['url_danfe_simplificada'] = str_replace('/danfe/', '/danfe/simples/', $nfe['url_danfe']);
      }
      if (!$nfe['url_danfe_etiqueta']) {
        $nfe_info[$key]['url_danfe_etiqueta'] = str_replace('/danfe/', '/danfe/etiqueta/', $nfe['url_danfe']);
      }
    }

    if(Tools::getValue('nfe-transporte-info')){
      $this->save_order_transporte_info($order_id);
    }

    $this->context->smarty->assign(array(
      'nfe_info_arr' => $nfe_info,
      'url' => $url,
    ));

    //NF-e info template
    return $this->display(__FILE__, 'nfe_info_table.tpl');

  }

  public function uninstall(){

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

        $input_value = strval(Tools::getValue($key));

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

    require_once('views/templates/settings-page.php');
    $html = ob_get_clean();

    return $html;



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
          'desc' => $this->l('Emitir sautomaticamente a NF-e sempre que que um pagamento for confirmado'),
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
          'type' => 'radio',
          'label' => $this->l('Envio automático de email'),
          'name' => $this->name.'envio_email',
          'desc' => $this->l('Atenção: O email será enviado mesmo para notas emitidas em ambiente de homologação!'),
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
        'title' => $this->l('Campos obrigatórios (Banco de Dados)'),
      ),

      'input' => array(
        array(
          'type' => 'radio',
          'label' => $this->l('Ativar campos CPF/CNPJ?'),
          'name' => $this->name.'cpf_cnpj_status',
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
          'label' => $this->l('Nome do campo CPF'),
          'name' => $this->name.'cpf_field',
          'size' => 50,
        ),

        array(
          'type' => 'text',
          'label' => $this->l('Nome do campo CNPJ'),
          'name' => $this->name.'cnpj_field',
          'size' => 50,
        ),

        array(
          'type' => 'text',
          'label' => $this->l('Nome do campo Razão Social'),
          'name' => $this->name.'razao_social_field',
          'size' => 50,
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Nome do campo Inscrição Estadual'),
          'name' => $this->name.'cnpj_ie_field',
          'size' => 50,
        ),

        array(
          'type' => 'radio',
          'label' => $this->l('Ativar campo Número?'),
          'name' => $this->name.'numero_compl_status',
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
          'label' => $this->l('Nome do campo Número (Endereço)'),
          'name' => $this->name.'numero_field',
          'size' => 50,
        ),

      ),

    );

    if(_MAIN_PS_VERSION_ == '1.7'){
      $fields_form[3]['form']['input'][] = array(

          'type' => 'radio',
          'label' => $this->l('Ativar campo Bairro?'),
          'name' => $this->name.'bairro_status',
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

      );

      $fields_form['3']['form']['input'][] = array(
        'type' => 'text',
        'label' => $this->l('Nome do campo Bairro (Endereço)'),
        'name' => $this->name.'bairro_field',
        'size' => 50,
      );
    }

    $fields_form[4]['form'] = array(
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


    $fields_form[5]['form'] = array(
      'legend' => array(
        'title' => $this->l('Informações da Transportadora'),
      ),
      'input' => array(
        array(
          'type' => 'radio',
          'hint' => 'Incluir dados da transportadora em pedidos enviados com o método configurado',
          'label' => $this->l('Incluir dados da transportadora na NF-e'),
          'name' => $this->name.'transp_include',
          'required' => true,
          'values' => array(
            array(
              'id' => 'include_on',
              'value' => 'on',
              'label' => $this->l('Ativado'),
            ),
            array(
              'id' => 'include_off',
              'value' => 'off',
              'label' => $this->l('Desativado'),
            ),
          )
        ),
        array(
          'type' => 'select',
          'label' => $this->l('Método de Entrega'),
          'name' => $this->name.'transp_method',
          'options' => array(
            'query' => $this->get_shipping_methods_options(),
            'id' => 'id_option',
            'name' => 'name'
          )

        ),
        array(
          'type' => 'text',
          'label' => $this->l('Razão Social'),
          'name' => $this->name.'transp_rs',
          'size' => 50,
        ),
        array(
          'type' => 'text',
          'label' => $this->l('CNPJ'),
          'name' => $this->name.'transp_cnpj',
          'size' => 50,
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Inscrição Estadual'),
          'name' => $this->name.'transp_ie',
          'size' => 50,
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Endereço'),
          'name' => $this->name.'transp_address',
          'size' => 50,
        ),
        array(
          'type' => 'text',
          'label' => $this->l('CEP'),
          'name' => $this->name.'transp_cep',
          'size' => 50,
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Cidade'),
          'name' => $this->name.'transp_city',
          'size' => 50,
        ),
        array(
          'type' => 'text',
          'label' => $this->l('UF'),
          'name' => $this->name.'transp_uf',
          'size' => 50,
        ),
      ),

    );

    $fields_form[6]['form'] = array(
      'legend' => array(
        'title' => $this->l('Salvar alteraçoes')
      ),
      'submit' => array(
        'title' => $this->l('Salvar'),
        'class' => 'button'
      )
    );




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

    return $helper->generateForm($fields_form).'<span class="teste"></span>';
  }

  /**
  * Get module configs values
  */
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
      $this->name.'bairro_status' => md5(uniqid(rand(), true)),
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

    $this->listen_notification();

    if (_PS_VERSION_ < '1.7.7') {
      $this->processBulkEmitirNfe();
    }

    $this->updateNfe();

    if(_MAIN_PS_VERSION_ == '1.6' || _MAIN_PS_VERSION_ == '1.7'){
      $controller_name = $this->context->controller->controller_name;
      $this->context->controller->addJquery();
      if($controller_name == 'AdminCustomers' || $controller_name == 'AdminModules'){
        $this->context->controller->addJS($this->_path.'/js/jquery.mask.min.js', 'all');
      }

      $cpf_cnpj_status = Configuration::get($this->name.'cpf_cnpj_status');
      $numero_enabled = Configuration::get($this->name.'numero_compl_status');

      if(Tools::getValue('id_customer')){
        Media::addJsDef(array('id_customer_wmbr' => Tools::getValue('id_customer')));
      }

      if(Tools::getValue('id_address')){
        Media::addJsDef(array('id_address_wmbr' => Tools::getValue('id_address')));
      }


      Media::addJsDef(array('tipo_pessoa_enabled' => $cpf_cnpj_status));
      Media::addJsDef(array('numero_enabled' => $numero_enabled));

      Media::addJsDef(array('sec_token' => Tools::getAdminToken('7Br2ZZwaRD')));

      if (_PS_VERSION_ >= '1.7.7') {
        $this->context->controller->addJS($this->_path.'/js/scripts_bo.1.7.7.js', 'all');
      }
      else {
        $this->context->controller->addJS($this->_path.'/js/scripts_bo.1.6-1.7.js', 'all');
      }
      
      $this->context->controller->addCSS($this->_path.'/views/css/style.css', 'all');
    }

    //Support to PS 1.5
    if(_MAIN_PS_VERSION_ == '1.5'){
      $this->rearrangeStates();
      $controllerName = $this->context->controller->controller_name;
      if(($controllerName == 'AdminOrders' && !Tools::getValue('id_order')) || $controllerName = 'AdminCustomers' || $controllerName = 'AdminAddresses'){
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

    $order_id = $params['id_order'];
    $order = new Order($order_id);

    $this->display_order_nfe_table($order_id);

    if(Tools::getValue('nfe-transporte-info')){
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

      //Set danfe simples/etiqueta url if it doesn't exist
      foreach ($nfe_info as $key => $nfe) {
        if (!$nfe['url_danfe_simplificada']) {
          $nfe_info[$key]['url_danfe_simplificada'] = str_replace('/danfe/', '/danfe/simples/', $nfe['url_danfe']);
        }
        if (!$nfe['url_danfe_etiqueta']) {
          $nfe_info[$key]['url_danfe_etiqueta'] = str_replace('/danfe/', '/danfe/etiqueta/', $nfe['url_danfe']);
        }
      }

      $this->context->smarty->assign(array(
        'nfe_info_arr' => $nfe_info,
        'url' => $url,
      ));

      echo  $this->display(__FILE__, 'nfe_info_table_1.6-1.7.tpl');

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

  public function display_order_transporte_info($order_id){

    $transporte_info = $this->get_order_transporte_info($order_id);

    $this->context->smarty->assign(array(
      'transporte_info' => $transporte_info,
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
      $this->context->controller->_errors[] = 'Error: '.mysql_error();
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

    if(_MAIN_PS_VERSION_ == '1.6' || _MAIN_PS_VERSION_ == '1.7'){

      if(Configuration::get($this->name.'mask_fields') == 'on'){
        Media::addJsDef(array('mask_doc_fields' => true));
      }

      //if(Configuration::get($this->name.'fill_address') == 'on'){
        //Media::addJsDef(array('fill_address' => true));
      //}


      $this->setJSCustomFieldsStatus();
      Media::addJsDef(array('sec_token' => Tools::getAdminToken('7Br2ZZwaRD')));

      $this->context->controller->addJS($this->_path.'/js/scripts_fo.'._MAIN_PS_VERSION_.'.js', 'all');


    }

    $this->context->controller->addJS($this->_path.'/js/correios.min.js', 'all');
    $this->context->controller->addJS($this->_path.'/js/jquery.mask.min.js', 'all');
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

    $orderID = $params['id_order'];
    $automatic = Configuration::get($this->name.'automatic_emit');
    if($automatic == 'on'){
      $this->emitirNfe($orderID);
    }
    return true;

  }

  public function hookDisplayAdminProductsExtra($params = null){

    $product_id = (int)Tools::getValue('id_product');
    if(!$product_id) $product_id = $params['id_product'];

    if(Validate::isLoadedObject($product = new Product($product_id))){

      $values = $this->getProductNfeValues( $product_id );

      $this->context->smarty->assign(array(
        'tax_class' => $values[0]['nfe_tax_class'],
        'ean_bar_code' => $values[0]['nfe_ean_bar_code'],
        'gtin_tributavel' => $values[0]['nfe_gtin_tributavel'],
        'ncm_code' => $values[0]['nfe_ncm_code'],
        'cest_code' => $values[0]['nfe_cest_code'],
        'cnpj_fabricante' => $values[0]['nfe_cnpj_fabricante'],
        'ind_escala' => $values[0]['nfe_ind_escala'],
        'product_source' => $values[0]['nfe_product_source'],
        'ignorar_nfe' => $values[0]['nfe_ignorar_nfe']
      ));

    }

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
    if(!Db::getInstance()->update('product', array(
      'nfe_tax_class'=> pSQL(Tools::getValue('nfe_tax_class')),
      'nfe_ean_bar_code' => pSQL(Tools::getValue('nfe_ean_bar_code')),
      'nfe_gtin_tributavel' => pSQL(Tools::getValue('nfe_gtin_tributavel')),
      'nfe_ncm_code' => pSQL(Tools::getValue('nfe_ncm_code')),
      'nfe_cest_code' => pSQL(Tools::getValue('nfe_cest_code')),
      'nfe_cnpj_fabricante' => pSQL(Tools::getValue('nfe_cnpj_fabricante')),
      'nfe_ind_escala' => pSQL(Tools::getValue('nfe_ind_escala')),
      'nfe_product_source' => pSQL(Tools::getValue('nfe_product_source')),
      'nfe_ignorar_nfe' => pSQL(Tools::getValue('nfe_ignorar_nfe'))
    ),'id_product = ' .$id_product )){
      $this->context->controller->_errors[] = 'Error: '.mysql_error();
    }

  }




  public function createCustomerDocument( $customer_id, $customer_data ) {

    if($customer_data['document_type'] == 'cpf'){
      $DB_data = array(
        'nfe_document_type'   => pSQL($customer_data['document_type']),
        'nfe_document_number' => pSQL(preg_replace("/[^0-9]/", "", $customer_data['cpf'])),
      );
    }elseif($customer_data['document_type'] == 'cnpj'){
      $DB_data = array(
        'nfe_document_type'   => pSQL($customer_data['document_type']),
        'nfe_document_number' => pSQL(preg_replace("/[^0-9]/", "", $customer_data['cnpj'])),
        'nfe_razao_social'    => pSQL($customer_data['razao_social']),
        'nfe_pj_ie'           => pSQL($customer_data['cnpj_ie']),
      );
    }

    if(!Db::getInstance()->update('customer', $DB_data, 'id_customer = ' .$customer_id )){
      $this->context->controller->_errors[] = 'Error: '.mysql_error();
    }

  }

  public function hookActionCustomerAccountAdd($params = null){

    $customer_data = array();
    $customer_id = (int)$params['newCustomer']->id;

    if(_MAIN_PS_VERSION_ == '1.6'){
      $data = $params['_POST'];
      $customer_data = array(
        'document_type' => $data['document_type'],
        'cpf'           => $data['cpf'],
        'cnpj'          => $data['cnpj'],
        'razao_social'  => $data['razao_social'],
        'cnpj_ie'       => $data['cnpj_ie'],
      );
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

      if(_MAIN_PS_VERSION_ == '1.6' || _MAIN_PS_VERSION_ == '1.7'){

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
      $document_number = pSQL(preg_replace("/[^0-9]/","",$customer_data['cnpj']));
      $razao_social = pSQL($customer_data['razao_social']);
      $cnpj_ie = pSQL($customer_data['cnpj_ie']);

      $query = "UPDATE ". _DB_PREFIX_ ."customer SET nfe_document_type = '".$document_type."', nfe_document_number = '".$document_number."', nfe_razao_social = '".$razao_social."', nfe_pj_ie = '".$cnpj_ie."' WHERE id_customer = " . $customer_id;

      $result = Db::getInstance()->executeS($query);
    }
    return true;

  }

  public function hookActionCategoryUpdate($params = null) {

    $category_id = Tools::getValue('id_category');
    $values = Tools::getAllValues();
    $ncm = '';

    foreach($values as $key => $value){

      if( strpos($key, 'nfe_category_ncm') !== false ){
        $ncm = pSQL($value);
        break;
      }

    }

    $update_values = array(
      'nfe_category_ncm' => $ncm
    );


    if( $category_id ){

      $query = "UPDATE ". _DB_PREFIX_ ."category SET nfe_category_ncm = '".$ncm."' WHERE id_category = " . $category_id;
      $result = Db::getInstance()->update( 'category', $update_values, 'id_category = ' .(int)$category_id );

      if(!$result){
        $this->errors[] = Db::getInstance()->getMsgError();
      }

    }


  }

  public function updateAddressInfo( $address_id ){

    $number_status = Configuration::get($this->name.'numero_compl_status');
    $update_values = array();
    $context_type = Context::getContext()->controller->controller_type;

    if($number_status == 'on'){
      $number = Tools::getValue('address_number');
    }else{
      $number = Tools::getValue(Configuration::get($this->name.'numero_field'));
    }

    $update_values = array(
      'address_number'=> pSQL($number),
    );

    if(_MAIN_PS_VERSION_ == '1.7'){

      $bairro_status = Configuration::get($this->name.'bairro_status');
      if($bairro_status == 'on'){
        $bairro = Tools::getValue('bairro');
      }else{
        $bairro = Tools::getValue(Configuration::get($this->name.'bairro_field'));
      }

      if($bairro){
        $update_values['bairro'] = pSQL($bairro);
      }

    }


    if(!empty($update_values)){
      if(!Db::getInstance()->update('address', $update_values, 'id_address = ' .(int)$address_id)){
        $this->errors[] = Db::getInstance()->getMsgError();
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
    $context_type = Context::getContext()->controller->controller_type;

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

    // Set user
    $is_cnpj = str_replace( array('/', '.', '-'), '', Tools::getValue($fields['cnpj']));
    if ($is_cnpj && strlen($is_cnpj) == 14) $is_cnpj = true; else $is_cnpj = false;

    if ($is_cnpj){

      if (Tools::getValue($fields['cnpj'])){

        $update_values['nfe_document_type'] = 'cnpj';
        $update_values['nfe_document_number'] = preg_replace("/[^0-9]/","", Tools::getValue($fields['cnpj']));

        if (Tools::getValue($fields['razao_social'])) $update_values['nfe_razao_social'] = pSQL(Tools::getValue($fields['razao_social']));
        else $update_values['nfe_razao_social'] = '';

        if (Tools::getValue($fields['ie'])) $update_values['nfe_pj_ie'] = pSQL(Tools::getValue($fields['ie']));
        else $update_values['nfe_pj_ie'] = '';

      }

    } else {

      if (Tools::getValue($fields['cpf'])){

        $update_values['nfe_document_type'] = 'cpf';
        $update_values['nfe_document_number'] = preg_replace("/[^0-9]/","", Tools::getValue($fields['cpf']));
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
        $this->errors[] = Db::getInstance()->getMsgError();
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

    $order = new Order($orderID);

    $discounts = $order->getDiscounts(true);
    $discounts_applied = array(); // Only percentage

    $envio_email = Configuration::get($this->name.'envio_email');

    $uniq_key = Configuration::get($this->name.'uniq_key');
    if(!$uniq_key){
      $uniq_key = md5(uniqid(rand(), true));
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
    $customer_docs = array();

    // Get Custom Fields
    $cpf_cnpj_status = Configuration::get($this->name.'cpf_cnpj_status');
    $number_status = Configuration::get($this->name.'numero_compl_status');
    $bairro_status = Configuration::get($this->name.'bairro_status');

    if ($cpf_cnpj_status == 'on'){

      $result = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'customer WHERE id_customer = ' . (int)$customer->id);

      $customer_docs = array(
        'cpf'          => $result['nfe_document_number'],
        'cnpj'         => $result['nfe_document_number'],
        'razao_social' => $result['nfe_razao_social'],
        'ie'           => $result['nfe_pj_ie']
      );

    } else {

      $customer_docs = array();

      if(!Configuration::get($this->name.'docscolumn_cpf')){

        $fields = array(
          'cpf'            => Configuration::get($this->name.'cpf_field'),
          'cnpj'           => Configuration::get($this->name.'cnpj_field'),
          'razao_social'   => Configuration::get($this->name.'razao_social_field'),
          'ie'             => Configuration::get($this->name.'cnpj_ie_field')
        );

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

                if ($table != "" && $column != "" && $customer->id != ""){

                    $query = "SELECT $column FROM $table WHERE id_customer = '$customer->id'";
                    $val = Db::getInstance()->executeS($query);

                    foreach ($val as $value) {
                        $customer_docs[$key] = $value[$column];
                    }

                }

            }

        }

    }


    if ($number_status == 'on') $fields['address_number'] = 'address_number';
    else $fields['address_number'] = Configuration::get($this->name.'numero_field');

    if ($bairro_status == 'on') $fields['bairro'] = 'bairro';
    else $fields['bairro'] = Configuration::get($this->name.'bairro_field');

    // Search Database
    $address = new Address($order->id_address_delivery);
    $state = new State($address->id_state);
    $products = $order->getProducts();


    $address_custom = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'address WHERE id_address = ' . (int)$address->id);

    // Set Document Type
    /*$is_cnpj = str_replace( array('/', '.', '-'), '', $customer_custom[$fields['cnpj']]);
    if ($is_cnpj && strlen($is_cnpj) == 14) $is_cnpj = true; else $is_cnpj = false;
    if ($is_cnpj) $tipo_pessoa = 'cnpj'; else $tipo_pessoa = 'cpf';*/

    // Array start
    $data = array(
        'ID' => $order->id, // Número do pedido
        'origem' => 'prestashop_1.x',
        'url_notificacao' => Tools::getHttpHost(true).__PS_BASE_URI__.'?retorno_nfe='.$uniq_key.'&order_id='.$order->id,
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

       if (!$customer_custom[$fields['razao_social']]) $razao_social = $customer->firstname.' '.$customer->lastname;
       else $razao_social = $customer_custom[$fields['razao_social']];

       $data['cliente'] = array(
         'cnpj'         => $customer_docs['cnpj'], // (pessoa jurídica) Número do CNPJ
         'razao_social' => $customer_docs['razao_social'], // (pessoa jurídica) Razão Social
         'ie'           => $customer_docs['ie'], // (pessoa jurídica) Número da Inscrição Estadual
         'endereco'     => $address->address1, // Endereço de entrega dos produtos
         'complemento'  => $address->other, // Complemento do endereço de entrega
         'numero'       => $address_custom[$fields['address_number']], // Número do endereço de entrega
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
         'numero' => $address_custom[$fields['address_number']], // Número do endereço de entrega
         'bairro' => $address->address2, // Bairro do endereço de entrega
         'cidade' => $address->city, // Cidade do endereço de entrega
         'uf' => $state->iso_code, // Estado do endereço de entrega
         'cep' => $address->postcode, // CEP do endereço de entrega
         'telefone' => $address->phone, // Telefone do cliente
         'email' => $customer->email // E-mail do cliente para envio da NF-e
       );
     }

     if (_MAIN_PS_VERSION_ == '1.7'){

       $data['cliente']['bairro'] = ($fields['bairro']) ? $address_custom[$fields['bairro']] : '';

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
    $category_obj = new CategoryCore($id_category);

    $category_ncm = Db::getInstance()->ExecuteS("SELECT nfe_category_ncm   FROM "._DB_PREFIX_."category WHERE id_category = $id_category" );

    foreach($category_ncm as $ncm_query){
      if($ncm_query['nfe_category_ncm']){
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


    $tables = Db::getInstance()->executeS("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'");
    $html = '<select name="'.$name.'" class="nfe-docs--column" id="'.$name.'">';
    $html .= '<option value="">Selecionar</option>';

    foreach($tables as $table){

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

    if($active_table){
      $results = Db::getInstance()->executeS("SELECT column_name from information_schema.columns where table_name = '$active_table'");

      $html = '<select name="'.$name.'">';
      $html .= '<option value="">Selecionar</option>';

      foreach($results as $column){

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

  public function emitirNfe($orderID){

    $isempty = false;

    foreach($this->settings as $setting){
      if(empty($setting)){
        $url  = 'index.php?controller=AdminModules&configure=webmaniabrnfe';
        $url .= '&token='.Tools::getAdminTokenLite('AdminModules');
        $this->context->controller->errors[] = 'Informe as credenciais de acesso antes de emitir a NF-e: <a href="'.$url.'">Configurar</a>';
        $isempty = true;
        break;
      }
    }

    if($isempty) return true;

    $webmaniabr = new NFe($this->settings);
    $data = $this->getOrderData($orderID);

    $response = $webmaniabr->emissaoNotaFiscal( $data );

    $alerts = array();
    if (!$response || isset($response->error) || $response->status == 'reprovado' ){

      if (!$response){
        $alerts[] = array('type' => 'error', 'msg' => 'Erro ao emitir a NF-e do Pedido #'.$orderID.' (null)');
        $this->context->controller->errors[] = 'Erro ao emitir a NF-e do Pedido #'.$orderID.' (null)';
        echo 'Erro ao emitir a NF-e do Pedido #'.$orderID.' (null)';
      } elseif (isset($response->error)){
        $alerts[] = array('type' => 'error', 'msg' => 'Erro ao emitir a NF-e do Pedido #'.$orderID.' ( '.$response->error.' )');
        $this->context->controller->errors[] = 'Erro ao emitir a NF-e do Pedido #'.$orderID.' ( '.$response->error.' )';
      } elseif (isset($response->log->aProt[0]->xMotivo)){
        $alerts[] = array('type' => 'error', 'msg' => 'Erro ao emitir a NF-e do Pedido #'.$orderID. '( '.$response->log->aProt[0]->xMotivo.' )');
        $this->context->controller->errors[] = 'Erro ao emitir a NF-e do Pedido #'.$orderID. '( '.$response->log->aProt[0]->xMotivo.' )';
      } else {
        $alerts[] = array('type' => 'error', 'msg' => 'Erro ao emitir a NF-e do Pedido #'.$orderID);
        $this->context->controller->errors[] = 'Erro ao emitir a NF-e do Pedido #'.$orderID;
      }


    }else{

      $nfe_info = array(
      'uuid'         => (string) $response->uuid,
      'status'       => (string) $response->status,
      'chave_acesso' => $response->chave,
      'n_recibo'     => (int) $response->recibo,
      'n_nfe'        => (int) $response->nfe,
      'n_serie'      => (int) $response->serie,
      'url_xml'      => (string) $response->xml,
      'url_danfe'    => (string) $response->danfe,
      'url_danfe_simplificada'    => (string) $response->danfe_simples,
      'url_danfe_etiqueta'    => (string) $response->danfe_etiqueta,
      'data'         => date('d/m/Y'),
      );

      $existing_nfe_info = unserialize(Db::getInstance()->getValue("SELECT nfe_info FROM "._DB_PREFIX_."orders WHERE id_order = $orderID" ));
      if(!$existing_nfe_info){
        $existing_nfe_info = array();
      }

      $existing_nfe_info[] = $nfe_info;

      $nfe_info_str = serialize($existing_nfe_info);

      if(!Db::getInstance()->update('orders', array('nfe_info' => $nfe_info_str), 'id_order = ' .$orderID )){
        $alerts[] = array('type' => 'error', 'msg' => 'Erro ao atualizar status da NF-e');
        $this->context->controller->errors[] = 'Erro ao atualizar status da NF-e';
      }

      if(!Db::getInstance()->update('orders', array('nfe_numero' => $response->nfe), 'id_order = ' .$orderID )){
        $alerts[] = array('type' => 'error', 'msg' => 'Erro ao atualizar status da NF-e');
        $this->context->controller->errors[] = 'Erro ao atualizar status da NF-e';
      }

      if(!Db::getInstance()->update('orders', array('nfe_chave_de_acesso' => $response->chave), 'id_order = ' .$orderID )){
        $alerts[] = array('type' => 'error', 'msg' => 'Erro ao atualizar status da NF-e');
        $this->context->controller->errors[] = 'Erro ao atualizar status da NF-e';
      }

      if(!Db::getInstance()->update('orders', array('nfe_issued' => 1), 'id_order = ' .$orderID )){
        $alerts[] = array('type' => 'error', 'msg' => 'Erro ao alterar status da NF-e #'.$orderID);
        $this->context->controller->errors[] = 'Erro ao alterar status da NF-e #'.$orderID;
      }

        $alerts[] = array('type' => 'success', 'msg' => 'NF-e emitida com sucesso do Pedido #'.$orderID);
        $this->context->controller->confirmations[] = 'NF-e emitida com sucesso do Pedido #'.$orderID;

    }

    return $alerts;

  }

  public function updateNFe(){

    if(Tools::getValue('atualizar') && Tools::getValue('chave')){
      $chave_acesso = pSql(Tools::getValue('chave'));
      $order_id = (int) Tools::getValue('id_order');
      $webmaniabr = new NFe($this->settings);
      $response = $webmaniabr->consultaNotaFiscal($chave_acesso);

      if (isset($response->error)){

          $this->context->controller->errors[] = 'Erro: '.$response->error;
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
          $this->context->controller->errors[] = 'Erro ao atualizar informações da Nota Fiscal';
        }else{
          $this->context->controller->confirmations[] = 'NF-e atualizada com sucesso';
        }

      }
    }
  }

  public function processBulkEmitirNfe(){

    if( Tools::isSubmit('submitBulkemitirNfeorder')  ){
      $values = Tools::getValue('orderBox');
      foreach($values as $orderID){
        $this->emitirNfe($orderID);
      }
    }

    if( Tools::isSubmit('submitBulkimprimirDanfe')  ){
      $values = Tools::getValue('orderBox');
      if (!empty($values)) {
        $result = $this->get_nfe_urls($values, 'normal');
        if ($result['result']) {
          Tools::redirectLink($result['file']);
        }
      }    
    }

    if( Tools::isSubmit('submitBulkimprimirDanfeSimples')  ){
      $values = Tools::getValue('orderBox');
      if (!empty($values)) {
        $result = $this->get_nfe_urls($values, 'simples');
        if ($result['result']) {
          Tools::redirectLink($result['file']);
        }
      }    
    }

    if( Tools::isSubmit('submitBulkimprimirDanfeEtiqueta')  ){
      $values = Tools::getValue('orderBox');
      if (!empty($values)) {
        $result = $this->get_nfe_urls($values, 'etiqueta');
        if ($result['result']) {
          Tools::redirectLink($result['file']);
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

    // Verifica se um número foi informado
	if(empty($cnpj)) {
		return false;
	}

	// Elimina possivel mascara
	$cnpj = preg_replace("/[^0-9]/", "", $cnpj);

	// Verifica se o numero de digitos informados é igual a 11
	if (strlen($cnpj) != 14) {
		return false;
	}

	// Verifica se nenhuma das sequências invalidas abaixo
	// foi digitada. Caso afirmativo, retorna falso
	else if ($cnpj == '00000000000000' ||
		$cnpj == '11111111111111' ||
		$cnpj == '22222222222222' ||
		$cnpj == '33333333333333' ||
		$cnpj == '44444444444444' ||
		$cnpj == '55555555555555' ||
		$cnpj == '66666666666666' ||
		$cnpj == '77777777777777' ||
		$cnpj == '88888888888888' ||
		$cnpj == '99999999999999') {
		return false;

	 // Calcula os digitos verificadores para verificar se o
	 // CPF é válido
    } else {

		$j = 5;
		$k = 6;
		$soma1 = "";
		$soma2 = "";

		for ($i = 0; $i < 13; $i++) {

			$j = $j == 1 ? 9 : $j;
			$k = $k == 1 ? 9 : $k;

			$soma2 += ($cnpj{$i} * $k);

			if ($i < 12) {
				$soma1 += ($cnpj{$i} * $j);
			}

			$k--;
			$j--;

		}

		$digito1 = $soma1 % 11 < 2 ? 0 : 11 - $soma1 % 11;
		$digito2 = $soma2 % 11 < 2 ? 0 : 11 - $soma2 % 11;

		return (($cnpj{12} == $digito1) and ($cnpj{13} == $digito2));

    }
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

    if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['retorno_nfe']) && $_GET['order_id']){

      $order_id = (int) $_GET['order_id'];
      $uniq_key = Configuration::get($this->name.'uniq_key');

      if($_GET['retorno_nfe'] == $uniq_key){

        $order_nfe_info = unserialize(Db::getInstance()->getValue("SELECT nfe_info FROM "._DB_PREFIX_."orders WHERE id_order = $order_id" ));

        if(!$order_nfe_info) $order_nfe_info = array();

        foreach($order_nfe_info as $key => $nfe){

          $uuid       = $nfe['uuid'];

          $current_status = $nfe['status'];
          $received_status = $_POST['status'];


          if($uuid == $_POST['uuid'] && $current_status != $received_status){

            $order_nfe_info[$key]['status'] = $received_status;
            $nfe_info_str = serialize($order_nfe_info);

            if(!Db::getInstance()->update('orders', array('nfe_info' => $nfe_info_str), 'id_order = ' .$order_id )){
              $this->context->controller->errors[] = 'Erro ao atualizar status da NF-e';
            }
            break;
          }


        }

      }

    }

  }

  function get_nfe_urls($ids, $type) {

    $directory = _PS_ROOT_DIR_.'/modules/webmaniabrnfe/pdf_files/';
    if (!file_exists($directory)) {
      mkdir($directory);
    } 

    $link_pdf = '';

    $pdf = new PDFMerger();

    $files = 0;
    foreach ($ids as $id) {
      $nfe_info = unserialize(Db::getInstance()->getValue("SELECT nfe_info FROM "._DB_PREFIX_."orders WHERE id_order = $id" ));

      if (!$nfe_info) {
        continue;
      }

      $data = end($nfe_info);
      if ($data['status'] != 'aprovado') {
        continue;
      }

      if ($type == 'normal') {
        $url = $data['url_danfe'];
      }
      else if ($type == 'simples') {
        $url = ($data['url_danfe_simplificada']) ? $data['url_danfe_simplificada'] : str_replace('/danfe/', '/danfe/simples/', $data['url_danfe']);
      }
      else if ($type == 'etiqueta') {
        $url = ($data['url_danfe_etiqueta']) ? $data['url_danfe_etiqueta'] : str_replace('/danfe/', '/danfe/etiqueta/', $data['url_danfe']);
      }

      file_put_contents("{$directory}/{$data['chave_acesso']}.pdf", file_get_contents($url));
			$pdf->addPDF("{$directory}/{$data['chave_acesso']}.pdf", 'all');
      $files++;
    }

    $filename = time()."-".random_int(1, 10000000000);
		$result = ($files > 0) ? $pdf->merge('file', "{$directory}/{$filename}.pdf") : false;

		return array("result" => $result, "file" => _PS_BASE_URL_.__PS_BASE_URI__."modules/webmaniabrnfe/pdf_files/{$filename}.pdf");
    
  }

}
