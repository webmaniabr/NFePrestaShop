<?php

class AdminCustomersController extends AdminCustomersControllerCore{

  public function __construct(){
    parent::__construct();
  }

  public function renderForm()
  {
      /** @var Customer $obj */
      if (!($obj = $this->loadObject(true))) {
          return;
      }

      $genders = Gender::getGenders();
      $list_genders = array();
      foreach ($genders as $key => $gender) {
          /** @var Gender $gender */
          $list_genders[$key]['id'] = 'gender_'.$gender->id;
          $list_genders[$key]['value'] = $gender->id;
          $list_genders[$key]['label'] = $gender->name;
      }

      $document_types_list = array(
        array(
          'id' => 'document_cpf',
          'value' => 'cpf',
          'label' => 'Pessoa Física',
        ),
        array(
          'id' => 'document_cnpj',
          'value' => 'cnpj',
          'label' => 'Pessoa Jurídica',
        ),
      );

      $years = Tools::dateYears();
      $months = Tools::dateMonths();
      $days = Tools::dateDays();

      $groups = Group::getGroups($this->default_form_language, true);

      $this->fields_form = array(
          'legend' => array(
              'title' => $this->l('Customer'),
              'icon' => 'icon-user'
          ),
          'input' => array(

              array(
                  'type' => 'radio',
                  'label' => $this->l('Social title'),
                  'name' => 'id_gender',
                  'required' => false,
                  'class' => 't',
                  'values' => $list_genders
              ),
              array(
                  'type' => 'radio',
                  'label' => 'Tipo de Pessoa',
                  'name' => 'document_type',
                  'required' => true,
                  'class' => 't',
                  'values' => $document_types_list
              ),
              array(
                  'type' => 'text',
                  'label' => 'CPF',
                  'name' => 'cpf',
                  'required' => true,
                  'class' => 'cpf-group',
                  'col' => 4,
                  'hint' => 'Insira um CPF válido'
              ),
              array(
                  'type' => 'text',
                  'label' => 'Razão Social',
                  'name' => 'razao_social',
                  'required' => true,
                  'class' => 'cnpj-group',
                  'col' => 4,
                  'hint' => 'Razão Social obrigatória'
              ),
              array(
                  'type' => 'text',
                  'label' => 'CNPJ',
                  'name' => 'cnpj',
                  'required' => true,
                  'class' => 'cnpj-group',
                  'col' => 4,
                  'hint' => 'Insira um CNPJ válido'
              ),
              array(
                  'type' => 'text',
                  'label' => 'Inscrição Estadual',
                  'name' => 'cnpj_ie',
                  'required' => true,
                  'class' => 'cnpj-group',
                  'col' => 4,
                  'hint' => 'Insira uma I.E válida'
              ),
              array(
                  'type' => 'text',
                  'label' => $this->l('First name'),
                  'name' => 'firstname',
                  'required' => true,
                  'col' => '4',
                  'hint' => $this->l('Invalid characters:').' 0-9!&lt;&gt;,;?=+()@#"°{}_$%:'
              ),
              array(
                  'type' => 'text',
                  'label' => $this->l('Last name'),
                  'name' => 'lastname',
                  'required' => true,
                  'col' => '4',
                  'hint' => $this->l('Invalid characters:').' 0-9!&lt;&gt;,;?=+()@#"°{}_$%:'
              ),
              array(
                  'type' => 'text',
                  'prefix' => '<i class="icon-envelope-o"></i>',
                  'label' => $this->l('Email address'),
                  'name' => 'email',
                  'col' => '4',
                  'required' => true,
                  'autocomplete' => false
              ),
              array(
                  'type' => 'password',
                  'label' => $this->l('Password'),
                  'name' => 'passwd',
                  'required' => ($obj->id ? false : true),
                  'col' => '4',
                  'hint' => ($obj->id ? $this->l('Leave this field blank if there\'s no change.') :
                      sprintf($this->l('Password should be at least %s characters long.'), Validate::PASSWORD_LENGTH))
              ),
              array(
                  'type' => 'birthday',
                  'label' => $this->l('Birthday'),
                  'name' => 'birthday',
                  'options' => array(
                      'days' => $days,
                      'months' => $months,
                      'years' => $years
                  )
              ),
              array(
                  'type' => 'switch',
                  'label' => $this->l('Enabled'),
                  'name' => 'active',
                  'required' => false,
                  'class' => 't',
                  'is_bool' => true,
                  'values' => array(
                      array(
                          'id' => 'active_on',
                          'value' => 1,
                          'label' => $this->l('Enabled')
                      ),
                      array(
                          'id' => 'active_off',
                          'value' => 0,
                          'label' => $this->l('Disabled')
                      )
                  ),
                  'hint' => $this->l('Enable or disable customer login.')
              ),
              array(
                  'type' => 'switch',
                  'label' => $this->l('Newsletter'),
                  'name' => 'newsletter',
                  'required' => false,
                  'class' => 't',
                  'is_bool' => true,
                  'values' => array(
                      array(
                          'id' => 'newsletter_on',
                          'value' => 1,
                          'label' => $this->l('Enabled')
                      ),
                      array(
                          'id' => 'newsletter_off',
                          'value' => 0,
                          'label' => $this->l('Disabled')
                      )
                  ),
                  'disabled' =>  (bool)!Configuration::get('PS_CUSTOMER_NWSL'),
                  'hint' => $this->l('This customer will receive your newsletter via email.')
              ),
              array(
                  'type' => 'switch',
                  'label' => $this->l('Opt-in'),
                  'name' => 'optin',
                  'required' => false,
                  'class' => 't',
                  'is_bool' => true,
                  'values' => array(
                      array(
                          'id' => 'optin_on',
                          'value' => 1,
                          'label' => $this->l('Enabled')
                      ),
                      array(
                          'id' => 'optin_off',
                          'value' => 0,
                          'label' => $this->l('Disabled')
                      )
                  ),
                  'disabled' =>  (bool)!Configuration::get('PS_CUSTOMER_OPTIN'),
                  'hint' => $this->l('This customer will receive your ads via email.')
              ),
          )
      );

      // if we add a customer via fancybox (ajax), it's a customer and he doesn't need to be added to the visitor and guest groups
      if (Tools::isSubmit('addcustomer') && Tools::isSubmit('submitFormAjax')) {
          $visitor_group = Configuration::get('PS_UNIDENTIFIED_GROUP');
          $guest_group = Configuration::get('PS_GUEST_GROUP');
          foreach ($groups as $key => $g) {
              if (in_array($g['id_group'], array($visitor_group, $guest_group))) {
                  unset($groups[$key]);
              }
          }
      }

      $this->fields_form['input'] = array_merge(
          $this->fields_form['input'],
          array(
              array(
                  'type' => 'group',
                  'label' => $this->l('Group access'),
                  'name' => 'groupBox',
                  'values' => $groups,
                  'required' => true,
                  'col' => '6',
                  'hint' => $this->l('Select all the groups that you would like to apply to this customer.')
              ),
              array(
                  'type' => 'select',
                  'label' => $this->l('Default customer group'),
                  'name' => 'id_default_group',
                  'options' => array(
                      'query' => $groups,
                      'id' => 'id_group',
                      'name' => 'name'
                  ),
                  'col' => '4',
                  'hint' => array(
                      $this->l('This group will be the user\'s default group.'),
                      $this->l('Only the discount for the selected group will be applied to this customer.')
                  )
              )
          )
      );

      // if customer is a guest customer, password hasn't to be there
      if ($obj->id && ($obj->is_guest && $obj->id_default_group == Configuration::get('PS_GUEST_GROUP'))) {
          foreach ($this->fields_form['input'] as $k => $field) {
              if ($field['type'] == 'password') {
                  array_splice($this->fields_form['input'], $k, 1);
              }
          }
      }

      if (Configuration::get('PS_B2B_ENABLE')) {
          $risks = Risk::getRisks();

          $list_risks = array();
          foreach ($risks as $key => $risk) {
              /** @var Risk $risk */
              $list_risks[$key]['id_risk'] = (int)$risk->id;
              $list_risks[$key]['name'] = $risk->name;
          }

          $this->fields_form['input'][] = array(
              'type' => 'text',
              'label' => $this->l('Company'),
              'name' => 'company'
          );
          $this->fields_form['input'][] = array(
              'type' => 'text',
              'label' => $this->l('SIRET'),
              'name' => 'siret'
          );
          $this->fields_form['input'][] = array(
              'type' => 'text',
              'label' => $this->l('APE'),
              'name' => 'ape'
          );
          $this->fields_form['input'][] = array(
              'type' => 'text',
              'label' => $this->l('Website'),
              'name' => 'website'
          );
          $this->fields_form['input'][] = array(
              'type' => 'text',
              'label' => $this->l('Allowed outstanding amount'),
              'name' => 'outstanding_allow_amount',
              'hint' => $this->l('Valid characters:').' 0-9',
              'suffix' => $this->context->currency->sign
          );
          $this->fields_form['input'][] = array(
              'type' => 'text',
              'label' => $this->l('Maximum number of payment days'),
              'name' => 'max_payment_days',
              'hint' => $this->l('Valid characters:').' 0-9'
          );
          $this->fields_form['input'][] = array(
              'type' => 'select',
              'label' => $this->l('Risk rating'),
              'name' => 'id_risk',
              'required' => false,
              'class' => 't',
              'options' => array(
                  'query' => $list_risks,
                  'id' => 'id_risk',
                  'name' => 'name'
              ),
          );
      }

      $this->fields_form['submit'] = array(
          'title' => $this->l('Save'),
      );

      $birthday = explode('-', $this->getFieldValue($obj, 'birthday'));

      $this->fields_value = array(
          'years' => $this->getFieldValue($obj, 'birthday') ? $birthday[0] : 0,
          'months' => $this->getFieldValue($obj, 'birthday') ? $birthday[1] : 0,
          'days' => $this->getFieldValue($obj, 'birthday') ? $birthday[2] : 0,
      );

      // Added values of object Group
      if (!Validate::isUnsignedId($obj->id)) {
          $customer_groups = array();
      } else {
          $customer_groups = $obj->getGroups();
      }
      $customer_groups_ids = array();
      if (is_array($customer_groups)) {
          foreach ($customer_groups as $customer_group) {
              $customer_groups_ids[] = $customer_group;
          }
      }

      // if empty $carrier_groups_ids : object creation : we set the default groups
      if (empty($customer_groups_ids)) {
          $preselected = array(Configuration::get('PS_UNIDENTIFIED_GROUP'), Configuration::get('PS_GUEST_GROUP'), Configuration::get('PS_CUSTOMER_GROUP'));
          $customer_groups_ids = array_merge($customer_groups_ids, $preselected);
      }

      foreach ($groups as $group) {
          $this->fields_value['groupBox_'.$group['id_group']] =
              Tools::getValue('groupBox_'.$group['id_group'], in_array($group['id_group'], $customer_groups_ids));
      }

      if(Tools::getValue('document_type') == 'cnpj'){
        $this->fields_value['document_type'] = 'cnpj';
      }else{
        $this->fields_value['document_type'] = 'cpf';
      }

      if($obj->id){
         $result = Db::getInstance()->getRow('SELECT nfe_document_type, nfe_document_number, nfe_razao_social, nfe_pj_ie FROM '._DB_PREFIX_.'customer WHERE id_customer = '. (int)$obj->id);
         if($result !== false){
           $this->fields_value['document_type'] = $result['nfe_document_type'];
           $this->fields_value[$result['nfe_document_type']] = $result['nfe_document_number'];
          if($result['nfe_document_type'] == 'cnpj'){
             $this->fields_value['razao_social'] = $result['nfe_razao_social'];
             $this->fields_value['cnpj_ie'] = $result['nfe_pj_ie'];
           }
         }
       }
      return AdminController::renderForm();
  }

  public function processAdd(){
    $document_type = Tools::getValue('document_type');
    $webmaniabrnfe = Module::getInstanceByName('webmaniabrnfe');
    if($document_type != 'cpf' && $document_type != 'cnpj'){
      $this->errors[] = Tools::displayError('Escolha o tipo de pessoa adequado');
    }else{
      if($document_type == 'cpf'){
        if (!$webmaniabrnfe->is_cpf(Tools::getValue('cpf'))) {
            $this->errors[] = Tools::displayError('CPF inválido');
        }
      }

      if($document_type == 'cnpj'){
        if (!$webmaniabrnfe->is_cnpj(Tools::getValue('cnpj'))) {
            $this->errors[] = Tools::displayError('CNPJ inválido');
        }
        if (!Tools::getValue('razao_social')) {
            $this->errors[] = Tools::displayError('Razão Social obrigatória');
        }
        if (!Tools::getValue('cnpj_ie')) {
            $this->errors[] = Tools::displayError('Inscrição estadual obrigatória');
        }
      }
    }

    $customer = parent::processAdd();
    if(isset($customer->id)){
      if($document_type == 'cpf'){
        $document_number = preg_replace("[^0-9]","",Tools::getValue('cpf'));
        $update_values = array(
          'nfe_document_type' => pSQL(Tools::getValue('document_type')),
          'nfe_document_number' => pSQL($document_number)
        );
        if(!Db::getInstance()->update('customer', $update_values, 'id_customer = ' .(int)$customer->id)){
          $this->errors[] = Tools::displayError('Error: ').mysql_error();
        }
      }elseif($document_type == 'cnpj'){
        $document_number = preg_replace("[^0-9]","",Tools::getValue('cnpj'));
        $update_values = array(
          'nfe_document_type' => pSQL(Tools::getValue('document_type')),
          'nfe_document_number' => pSQL($document_number),
          'nfe_razao_social' => pSQL(Tools::getValue('razao_social')),
          'nfe_pj_ie' => pSQL(Tools::getValue('cnpj_ie'))
        );

        if(!Db::getInstance()->update('customer', $update_values, 'id_customer = ' .(int)$customer->id)){
          $this->errors[] = Tools::displayError('Error: ').mysql_error();
        }
      }
      return $customer;
    }else{
      return false;
    }
  }

  public function processUpdate(){
    $document_type = Tools::getValue('document_type');
    $webmaniabrnfe = Module::getInstanceByName('webmaniabrnfe');
    $error = false;
    if($document_type != 'cpf' && $document_type != 'cnpj'){
      $this->errors[] = Tools::displayError('Escolha o tipo de pessoa adequado');
      $error = true;
    }else{
      if($document_type == 'cpf'){
        if (!$webmaniabrnfe->is_cpf(Tools::getValue('cpf'))) {
            $this->errors[] = Tools::displayError('CPF inválido');
            $error = true;
        }
      }

      if($document_type == 'cnpj'){
        if (!$webmaniabrnfe->is_cnpj(Tools::getValue('cnpj'))) {
            $this->errors[] = Tools::displayError('CNPJ inválido');
            $error = true;
        }
        if (!Tools::getValue('razao_social')) {
            $this->errors[] = Tools::displayError('Razão Social obrigatória');
            $error = true;
        }
        if (!Tools::getValue('cnpj_ie')) {
            $this->errors[] = Tools::displayError('Inscrição estadual obrigatória');
            $error = true;
        }
      }
    }



    $customer = parent::processUpdate();
    if(isset($customer->id) && $error === false){
      if($document_type == 'cpf'){
        $document_number = preg_replace("[^0-9]","",Tools::getValue('cpf'));
        $update_values = array(
          'nfe_document_type' => pSQL(Tools::getValue('document_type')),
          'nfe_document_number' => pSQL($document_number)
        );
        if(!Db::getInstance()->update('customer', $update_values, 'id_customer = ' .(int)$customer->id)){
          $this->errors[] = Tools::displayError('Error: ').mysql_error();
        }
      }elseif($document_type == 'cnpj'){
        $document_number = preg_replace("[^0-9]","",Tools::getValue('cnpj'));
        $update_values = array(
          'nfe_document_type' => pSQL(Tools::getValue('document_type')),
          'nfe_document_number' => pSQL($document_number),
          'nfe_razao_social' => pSQL(Tools::getValue('razao_social')),
          'nfe_pj_ie' => pSQL(Tools::getValue('cnpj_ie'))
        );

        if(!Db::getInstance()->update('customer', $update_values, 'id_customer = ' .(int)$customer->id)){
          $this->errors[] = Tools::displayError('Error: ').mysql_error();
        }
      }
      return $customer;
    }
  }
}
