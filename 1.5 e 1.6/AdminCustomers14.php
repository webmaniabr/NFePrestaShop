<?php

require_once(PS_ADMIN_DIR.'/tabs/AdminCustomers.php');

class AdminCustomers14 extends AdminCustomers{

	public function displayForm($isMainTab = true){
		global $currentIndex;
		AdminTab::displayForm();

		if (!($obj = $this->loadObject(true)))
			return;

		$birthday = explode('-', $this->getFieldValue($obj, 'birthday'));
		$customer_groups = Tools::getValue('groupBox', $obj->getGroups());
		$groups = Group::getGroups($this->_defaultFormLanguage, true);
		$custom_html = $this->customHTML($obj);
		echo '
		<form action="'.$currentIndex.'&submitAdd'.$this->table.'=1&token='.$this->token.'" method="post" autocomplete="off">
		'.($obj->id ? '<input type="hidden" name="id_'.$this->table.'" value="'.$obj->id.'" />' : '').'
			<fieldset><legend><img src="../img/admin/tab-customers.gif" />'.$this->l('Customer').'</legend>
				<label>'.$this->l('Gender:').' </label>
				<div class="margin-form">
					<input type="radio" size="33" name="id_gender" id="gender_1" value="1" '.($this->getFieldValue($obj, 'id_gender') == 1 ? 'checked="checked" ' : '').'/>
					<label class="t" for="gender_1"> '.$this->l('Male').'</label>
					<input type="radio" size="33" name="id_gender" id="gender_2" value="2" '.($this->getFieldValue($obj, 'id_gender') == 2 ? 'checked="checked" ' : '').'/>
					<label class="t" for="gender_2"> '.$this->l('Female').'</label>
					<input type="radio" size="33" name="id_gender" id="gender_3" value="9" '.(($this->getFieldValue($obj, 'id_gender') == 9 OR !$this->getFieldValue($obj, 'id_gender')) ? 'checked="checked" ' : '').'/>
					<label class="t" for="gender_3"> '.$this->l('Unknown').'</label>
				</div>
				'.$custom_html.'

<label>'.$this->l('First name:').' </label>
				<div class="margin-form">
					<input type="text" size="33" name="firstname" value="'.htmlentities($this->getFieldValue($obj, 'firstname'), ENT_COMPAT, 'UTF-8').'" /> <sup>*</sup>
					<span class="hint" name="help_box">'.$this->l('Forbidden characters:').' 0-9!<>,;?=+()@#"�{}_$%:<span class="hint-pointer">&nbsp;</span></span>
				</div>
				<label>'.$this->l('Last name:').' </label>
				<div class="margin-form">
					<input type="text" size="33" name="lastname" value="'.htmlentities($this->getFieldValue($obj, 'lastname'), ENT_COMPAT, 'UTF-8').'" /> <sup>*</sup>
					<span class="hint" name="help_box">'.$this->l('Invalid characters:').' 0-9!<>,;?=+()@#"�{}_$%:<span class="hint-pointer">&nbsp;</span></span>
				</div>';
		// if the customer is guest, he hasn't any password
		if ($obj->id && !$obj->is_guest || Tools::isSubmit('add').$this->table)
		{
			echo '<label>'.$this->l('Password:').' </label>
					<div class="margin-form">
						<input type="password" size="33" name="passwd" value="" /> '.(!$obj->id ? '<sup>*</sup>' : '').'
						<p>'.($obj->id ? $this->l('Leave blank if there is no change') : $this->l('min 5 characters, only letters and numbers').' -_').'</p>
					</div>';
		}
		echo '
				<label>'.$this->l('E-mail address:').' </label>
				<div class="margin-form">
					<input type="text" size="33" name="email" value="'.htmlentities($this->getFieldValue($obj, 'email'), ENT_COMPAT, 'UTF-8').'" /> <sup>*</sup>
				</div>
				<label>'.$this->l('Birthday:').' </label>';
		$sl_year = ($this->getFieldValue($obj, 'birthday')) ? $birthday[0] : 0;
		$years = Tools::dateYears();
		$sl_month = ($this->getFieldValue($obj, 'birthday')) ? $birthday[1] : 0;
		$months = Tools::dateMonths();
		$sl_day = ($this->getFieldValue($obj, 'birthday')) ? $birthday[2] : 0;
		$days = Tools::dateDays();
		$tab_months = array(
			$this->l('January'),
			$this->l('February'),
			$this->l('March'),
			$this->l('April'),
			$this->l('May'),
			$this->l('June'),
			$this->l('July'),
			$this->l('August'),
			$this->l('September'),
			$this->l('October'),
			$this->l('November'),
			$this->l('December'));
		echo '
					<div class="margin-form">
					<select name="days">
						<option value="">-</option>';
		foreach ($days as $v)
			echo '<option value="'.$v.'" '.($sl_day == $v ? 'selected="selected"' : '').'>'.$v.'</option>';
		echo '
					</select>
					<select name="months">
						<option value="">-</option>';
		foreach ($months as $k => $v)
			echo '<option value="'.$k.'" '.($sl_month == $k ? 'selected="selected"' : '').'>'.$this->l($v).'</option>';
		echo '</select>
					<select name="years">
						<option value="">-</option>';
		foreach ($years as $v)
			echo '<option value="'.$v.'" '.($sl_year == $v ? 'selected="selected"' : '').'>'.$v.'</option>';
		echo '</select>
				</div>';
		echo '<label>'.$this->l('Status:').' </label>
				<div class="margin-form">
					<input type="radio" name="active" id="active_on" value="1" '.($this->getFieldValue($obj, 'active') ? 'checked="checked" ' : '').'/>
					<label class="t" for="active_on"><img src="../img/admin/enabled.gif" alt="'.$this->l('Enabled').'" title="'.$this->l('Enabled').'" /></label>
					<input type="radio" name="active" id="active_off" value="0" '.(!$this->getFieldValue($obj, 'active') ? 'checked="checked" ' : '').'/>
					<label class="t" for="active_off"><img src="../img/admin/disabled.gif" alt="'.$this->l('Disabled').'" title="'.$this->l('Disabled').'" /></label>
					<p>'.$this->l('Enable or disable customer login').'</p>
				</div>
				<label>'.$this->l('Newsletter:').' </label>
				<div class="margin-form">
					<input type="radio" name="newsletter" id="newsletter_on" value="1" '.($this->getFieldValue($obj, 'newsletter') ? 'checked="checked" ' : '').'/>
					<label class="t" for="newsletter_on"><img src="../img/admin/enabled.gif" alt="'.$this->l('Enabled').'" title="'.$this->l('Enabled').'" /></label>
					<input type="radio" name="newsletter" id="newsletter_off" value="0" '.(!$this->getFieldValue($obj, 'newsletter') ? 'checked="checked" ' : '').'/>
					<label class="t" for="newsletter_off"><img src="../img/admin/disabled.gif" alt="'.$this->l('Disabled').'" title="'.$this->l('Disabled').'" /></label>
					<p>'.$this->l('Customer will receive your newsletter via e-mail').'</p>
				</div>
				<label>'.$this->l('Opt-in:').' </label>
				<div class="margin-form">
					<input type="radio" name="optin" id="optin_on" value="1" '.($this->getFieldValue($obj, 'optin') ? 'checked="checked" ' : '').'/>
					<label class="t" for="optin_on"><img src="../img/admin/enabled.gif" alt="'.$this->l('Enabled').'" title="'.$this->l('Enabled').'" /></label>
					<input type="radio" name="optin" id="optin_off" value="0" '.(!$this->getFieldValue($obj, 'optin') ? 'checked="checked" ' : '').'/>
					<label class="t" for="optin_off"><img src="../img/admin/disabled.gif" alt="'.$this->l('Disabled').'" title="'.$this->l('Disabled').'" /></label>
					<p>'.$this->l('Customer will receive your ads via e-mail').'</p>
				</div>
				<label>'.$this->l('Default group:').' </label>
				<div class="margin-form">
					<select name="id_default_group" onchange="checkDefaultGroup(this.value);">';
		foreach ($groups as $group)
			echo '<option value="'.(int)($group['id_group']).'"'.($group['id_group'] == $obj->id_default_group ? ' selected="selected"' : '').'>'.htmlentities($group['name'], ENT_NOQUOTES, 'utf-8').'</option>';
		echo '
					</select>
					<p>'.$this->l('Apply non-cumulative rules (e.g., price, display method, reduction)').'</p>
				</div>
				<label>'.$this->l('Groups:').' </label>
				<div class="margin-form">';
		if (count($groups))
		{
			echo '
					<table cellspacing="0" cellpadding="0" class="table" style="width: 29.5em;">
						<tr>
							<th><input type="checkbox" name="checkme" class="noborder" onclick="checkDelBoxes(this.form, \'groupBox[]\', this.checked)" /></th>
							<th>'.$this->l('ID').'</th>
							<th>'.$this->l('Group name').'</th>
						</tr>';
			$irow = 0;
			foreach ($groups as $group)
			{
				echo '
							<tr class="'.($irow++ % 2 ? 'alt_row' : '').'">
								<td>'.'<input type="checkbox" name="groupBox[]" class="groupBox" id="groupBox_'.$group['id_group'].'" value="'.$group['id_group'].'" '.(in_array($group['id_group'], $customer_groups) ? 'checked="checked" ' : '').'/></td>
								<td>'.$group['id_group'].'</td>
								<td><label for="groupBox_'.$group['id_group'].'" class="t">'.$group['name'].'</label></td>
							</tr>';
			}
			echo '
					</table>
					<p style="padding:0px; margin:10px 0px 10px 0px;">'.$this->l('Check all the box(es) of groups to which the customer is member').'<sup> *</sup></p>
					';
		} else
			echo '<p>'.$this->l('No group created').'</p>';
		echo '
				</div>
				<div class="margin-form">
					<input type="submit" value="'.$this->l('   Save   ').'" name="submitAdd'.$this->table.'" class="button" />
				</div>
				<div class="small"><sup>*</sup> '.$this->l('Required field').'</div>
			</fieldset>
		</form>';
	}



	public function postProcess()
	{
		global $currentIndex;

		if (Tools::isSubmit('submitDel'.$this->table) OR Tools::isSubmit('delete'.$this->table))
		{
			$deleteForm = '
			<form action="'.htmlentities($_SERVER['REQUEST_URI']).'" method="post">
				<fieldset><legend>'.$this->l('How do you want to delete these customer(s)?').'</legend>
					'.$this->l('There are 2 ways of deleting a customer, please choose which you prefer.').'
					<p>
						<input type="radio" name="deleteMode" value="real" id="deleteMode_real" />
						<label for="deleteMode_real" style="float:none">'.$this->l('I want to delete my customer(s) completely; all data will be removed from the database. A customer with the same email address will be able to register again.').'</label>
					</p>
					<p>
						<input type="radio" name="deleteMode" value="deleted" id="deleteMode_deleted" />
						<label for="deleteMode_deleted" style="float:none">'.$this->l('I don\'t want my customer(s) to register again. The customer(s) will be removed from this list but all data will be kept in the database.').'</label>
					</p>';
			foreach ($_POST as $key => $value)
				if (is_array($value))
					foreach ($value as $val)
						$deleteForm .= '<input type="hidden" name="'.htmlentities($key).'[]" value="'.htmlentities($val).'" />';
				else
					$deleteForm .= '<input type="hidden" name="'.htmlentities($key).'" value="'.htmlentities($value).'" />';
			$deleteForm .= '	<br /><input type="submit" class="button" value="'.$this->l('   Delete   ').'" />
				</fieldset>
			</form>
			<div class="clear">&nbsp;</div>';
		}

		if (Tools::getValue('submitAdd'.$this->table))
		{
		 	$groupList = Tools::getValue('groupBox');

		 	/* Checking fields validity */
			$document_type = Tools::getValue('document_type');
			$webmaniabrnfe = new WebmaniabrNfe();
			if($document_type != 'cpf' && $document_type != 'cnpj'){
				$this->_errors[] = Tools::displayError('Escolha o <strong>Tipo de Pessoa</strong> adequado');
			}else{
				if($document_type == 'cpf'){
					if (!$webmaniabrnfe->validaCPF(Tools::getValue('cpf'))) {
							$this->_errors[] = Tools::displayError('<strong>CPF</strong> inválido');
					}
				}

				if($document_type == 'cnpj'){
					if (!$webmaniabrnfe->validaCNPJ(Tools::getValue('cnpj'))) {
							$this->_errors[] = Tools::displayError('<strong>CNPJ</strong> inválido');
					}
					if (!Tools::getValue('razao_social')) {
							$this->_errors[] = Tools::displayError('<strong>Razão Social</strong> obrigatória');
					}
					if (!Tools::getValue('cnpj_ie')) {
							$this->_errors[] = Tools::displayError('<strong>Inscrição Estadual</strong> obrigatória');
					}
				}
			}
			$this->validateRules();
			if (!count($this->_errors))
			{
				$id = (int)(Tools::getValue('id_'.$this->table));
				$customer_email = Tools::getValue('email');
				if (isset($id) && !empty($id))
				{
					if ($this->tabAccess['edit'] !== '1')
						$this->_errors[] = Tools::displayError('You do not have permission to edit here.');
					else
					{
						$object = new $this->className($id);
						if (Validate::isLoadedObject($object))
						{

							// check if e-mail already used
							if ($customer_email != $object->email)
							{
								$customer = new Customer();
								$customer->getByEmail($customer_email);
								if ($customer->id)
									$this->_errors[] = Tools::displayError('An account already exists for this e-mail address:').' '.Tools::safeOutput($customer_email);
							}

							$document_type = Tools::getValue('document_type');
							if($document_type == 'cpf'){
								$document_number = preg_replace("[^0-9]","",Tools::getValue('cpf'));
								$update_values = array(
									'nfe_document_type' => pSQL(Tools::getValue('document_type')),
									'nfe_document_number' => pSQL($document_number)
								);
								if(!Db::getInstance()->autoExecute(_DB_PREFIX_.'customer', $update_values, 'UPDATE', 'id_customer = ' .(int)$id)){
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

								if(Db::getInstance()->autoExecute(_DB_PREFIX_.'customer', $update_values, 'UPDATE', 'id_customer = ' .(int)$id)){
									$this->errors[] = Tools::displayError('Error: ').mysql_error();
								}
							}

							if (!is_array($groupList) || count($groupList) == 0)
								$this->_errors[] = Tools::displayError('Customer must be in at least one group.');
							else
								if (!in_array(Tools::getValue('id_default_group'), $groupList))
									$this->_errors[] = Tools::displayError('Default customer group must be selected in group box.');

							// Updating customer's group
							if (!count($this->_errors))
							{
								$object->cleanGroups();
								if (is_array($groupList) && count($groupList) > 0)
									$object->addGroups($groupList);
							}
						}
						else
							$this->_errors[] = Tools::displayError('An error occurred while loading object.').' <b>'.Tools::safeOutput($this->table).'</b> '.Tools::displayError('(cannot load object)');
					}
				}
				else
				{
					if ($this->tabAccess['add'] === '1')
					{
						$object = new $this->className();
						$object->getByEmail($customer_email);
						if ($object->id)
							$this->_errors[] = Tools::displayError('An account already exists for this e-mail address:').' '.Tools::safeOutput($customer_email);
						else
						{
							$this->copyFromPost($object, $this->table);
							if (!$object->add())
								$this->_errors[] = Tools::displayError('An error occurred while creating object.').' <b>'.$this->table.' ('.mysql_error().')</b>';
							else
							{
								$_POST[$this->identifier] = $object->id; /* voluntary */
								if ($_POST[$this->identifier] && $this->postImage($object->id) && !count($this->_errors) && $this->_redirect)
								{
									// Add Associated groups
									$group_list = Tools::getValue('groupBox');
									if (is_array($group_list) && count($group_list) > 0)
										$object->addGroups($group_list, true);
									$parent_id = (int)(Tools::getValue('id_parent', 1));

									$document_type = Tools::getValue('document_type');
									if($document_type == 'cpf'){
						        $document_number = preg_replace("[^0-9]","",Tools::getValue('cpf'));
						        $update_values = array(
						          'nfe_document_type' => pSQL(Tools::getValue('document_type')),
						          'nfe_document_number' => pSQL($document_number)
						        );
						        if(!Db::getInstance()->autoExecute(_DB_PREFIX_.'customer', $update_values, 'UPDATE', 'id_customer = ' .(int)$object->id)){
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

						        if(Db::getInstance()->autoExecute(_DB_PREFIX_.'customer', $update_values, 'UPDATE', 'id_customer = ' .(int)$object->id)){
						          $this->errors[] = Tools::displayError('Error: ').mysql_error();
						        }
						      }


									// Save and stay on same form
									if (Tools::isSubmit('submitAdd'.$this->table.'AndStay'))
										Tools::redirectAdmin($currentIndex.'&'.$this->identifier.'='.$object->id.'&conf=3&update'.$this->table.'&token='.$this->token);
									// Save and back to parent
									if (Tools::isSubmit('submitAdd'.$this->table.'AndBackToParent'))
										Tools::redirectAdmin($currentIndex.'&'.$this->identifier.'='.$parent_id.'&conf=3&token='.$this->token);
									// Default behavior (save and back)
									Tools::redirectAdmin($currentIndex.($parent_id ? '&'.$this->identifier.'='.$object->id : '').'&conf=3&token='.$this->token);
								}
							}
						}
					}
					else
						$this->_errors[] = Tools::displayError('You do not have permission to add here.');
				}
			}
		}
		elseif (Tools::isSubmit('delete'.$this->table) && $this->tabAccess['delete'] === '1')
		{
			switch (Tools::getValue('deleteMode'))
			{
				case 'real':
					$this->deleted = false;
					Discount::deleteByIdCustomer((int)(Tools::getValue('id_customer')));
					break;
				case 'deleted':
					$this->deleted = true;
					break;
				default:
					echo $deleteForm;
					if (isset($_POST['delete'.$this->table]))
						unset($_POST['delete'.$this->table]);
					if (isset($_GET['delete'.$this->table]))
						unset($_GET['delete'.$this->table]);
					break;
			}
		}
		elseif (Tools::isSubmit('submitDel'.$this->table) AND $this->tabAccess['delete'] === '1')
		{
			switch (Tools::getValue('deleteMode'))
			{
				case 'real':
					$this->deleted = false;
					foreach (Tools::getValue('customerBox') as $id_customer)
						Discount::deleteByIdCustomer((int)($id_customer));
					break;
				case 'deleted':
					$this->deleted = true;
					break;
				default:
					echo $deleteForm;
					if (isset($_POST['submitDel'.$this->table]))
						unset($_POST['submitDel'.$this->table]);
					if (isset($_GET['submitDel'.$this->table]))
						unset($_GET['submitDel'.$this->table]);
					break;
			}
		}
		elseif (Tools::isSubmit('submitGuestToCustomer') AND Tools::getValue('id_customer'))
		{
			if ($this->tabAccess['edit'] === '1')
			{
				$customer = new Customer((int)Tools::getValue('id_customer'));
				if (!Validate::isLoadedObject($customer))
					$this->_errors[] = Tools::displayError('This customer does not exist.');
				if (Customer::customerExists($customer->email, false, true))
					$this->_errors[] = Tools::displayError('This customer already exist as non-guest.');
				elseif ($customer->transformToCustomer(Tools::getValue('id_lang', _PS_LANG_DEFAULT_)))
					Tools::redirectAdmin($currentIndex.'&'.$this->identifier.'='.$customer->id.'&conf=3&token='.$this->token);
				else
					$this->_errors[] = Tools::displayError('An error occurred while updating customer.');
			}
			else
				$this->_errors[] = Tools::displayError('You do not have permission to edit here.');
		}
		elseif (Tools::isSubmit('changeNewsletterVal') && Tools::getValue('id_customer'))
		{
			$id_customer = (int)Tools::getValue('id_customer');
			$customer = new Customer($id_customer);
			if (!Validate::isLoadedObject($customer))
				$this->_errors[] = Tools::displayError('An error occurred while updating customer.');
			$update = Db::getInstance()->Execute('UPDATE `'._DB_PREFIX_.'customer` SET newsletter = '.($customer->newsletter ? 0 : 1).' WHERE `id_customer` = '.(int)($customer->id));
			if (!$update)
				$this->_errors[] = Tools::displayError('An error occurred while updating customer.');
			Tools::redirectAdmin($currentIndex.'&token='.$this->token);

		}elseif (Tools::isSubmit('changeOptinVal') AND Tools::getValue('id_customer'))
			{
				$id_customer = (int)Tools::getValue('id_customer');
				$customer = new Customer($id_customer);
				if (!Validate::isLoadedObject($customer))
					$this->_errors[] = Tools::displayError('An error occurred while updating customer.');
				$update = Db::getInstance()->Execute('UPDATE `'._DB_PREFIX_.'customer` SET optin = '.($customer->optin ? 0 : 1).' WHERE `id_customer` = '.(int)($customer->id));
				if (!$update)
					$this->_errors[] = Tools::displayError('An error occurred while updating customer.');
				Tools::redirectAdmin($currentIndex.'&token='.$this->token);
			}

		return AdminTab::postProcess();
	}


	function customHTML($obj){
		$customer_info = array(
			'nfe_document_type' => '',
			'nfe_document_number' => '',
			'nfe_razao_social' => '',
			'nfe_pj_ie' => '',
		);
		if(isset($obj->id)){
			$customer_info = Db::getInstance()->getRow('SELECT nfe_document_type, nfe_document_number, nfe_razao_social, nfe_pj_ie FROM '._DB_PREFIX_.'customer WHERE id_customer = ' . (int)$obj->id);
		}

		$html = '<div id="document-types"><label>Tipo de Pessoa</label>
							<div class="margin-form">';
		if($customer_info['nfe_document_type'] == 'cnpj'){
			$values = array(
				'cnpj' => $customer_info['nfe_document_number'],
				'cpf' => '',
				'razao_social' => $customer_info['nfe_razao_social'],
				'ie' => $customer_info['nfe_pj_ie'],
			);
			$style_cpf = 'style="display:none;"';
			$style_cnpj = 'class="active"';
			$html .= '<input type="radio" name="document_type" value="cpf" data-rel="cpf" style="margin-right:5px"/> <label class="t" style="margin-right:10px;vertical-align:middle">Pessoa Física</label>
			<input type="radio" name="document_type" value="cnpj" data-rel="cnpj" style="margin-right:5px" checked/> <label class="t" style="margin-right:10px;vertical-align:middle">Pessoa Jurídica</label>';
		}else{
			$style_cnpj = 'style="display:none;"';
			$style_cpf = 'class="active"';
			$values = array(
				'cpf' => $customer_info['nfe_document_number'],
				'cnpj' => '',
				'razao_social' => '',
				'ie' => '',
			);
			$html .= '<input type="radio" name="document_type" value="cpf" data-rel="cpf" style="margin-right:5px" checked/> <label class="t" style="margin-right:10px;vertical-align:middle">Pessoa Física</label>
			<input type="radio" name="document_type" value="cnpj" data-rel="cnpj" style="margin-right:5px"/> <label class="t" style="margin-right:10px;vertical-align:middle">Pessoa Jurídica</label>';
		}
		$html .= '</div>
					<div id="cpf-field" '.$style_cpf.'>
					<label>CPF</label>
					<div class="margin-form">
						<input type="text" name="cpf" id="cpf-input" value="'.$values['cpf'].'" />
					</div>
					</div>
					<div id="cnpj-field" '.$style_cnpj.'>
					<label>Razão Social</label>
					<div class="margin-form" class="cnpj-group">
						<input type="text" name="razao_social" value="'.$values['razao_social'].'"/>
					</div>
					<label>CNPJ</label>
					<div class="margin-form" class="cnpj-group">
						<input type="text" name="cnpj" id="cnpj-input" value="'.$values['cnpj'].'" />
					</div>
					<label>Inscrição Estadual</label>
					<div class="margin-form" class="cnpj-group">
						<input type="text" name="cnpj_ie" value="'.$values['ie'].'"/>
					</div>
					</div></div>';
		return $html;
	}
}
