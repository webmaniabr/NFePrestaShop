<?php 

$fields_values = $this->getConfigFieldsValues();

$carriers = $fields_values[$this->name.'carriers'];

if($carriers){
  $carriers = base64_decode(str_replace('%', '=', $carriers));
  $carriers = json_decode(stripslashes(html_entity_decode($carriers)), true);
}else{
  $carriers = array();
}




?>


<form id="configuration_form" class="defaultForm form-horizontal webmaniabrnfe" method="post" enctype="multipart/form-data" novalidate="">
   <input type="hidden" name="submitwebmaniabrnfe" value="1">
   <div class="panel" id="fieldset_0">
      <div class="panel-heading">
         Credenciais de Acesso
      </div>
      <div class="form-wrapper">
         <div class="form-group">
            <label class="control-label col-lg-3 required">
            Consumer Key
            </label>
            <div class="col-lg-9">
               <input type="text" name="webmaniabrnfeconsumer_key" id="webmaniabrnfeconsumer_key" value="<?php echo $fields_values[$this->name.'consumer_key']; ?>" class="" size="50" required="required">
            </div>
         </div>
         <div class="form-group">
            <label class="control-label col-lg-3 required">
            Consumer Secret
            </label>
            <div class="col-lg-9">
               <input type="text" name="webmaniabrnfeconsumer_secret" id="webmaniabrnfeconsumer_secret" value="<?php echo $fields_values[$this->name.'consumer_secret']; ?>" class="" size="50" required="required">
            </div>
         </div>
         <div class="form-group">
            <label class="control-label col-lg-3 required">
            Access Token
            </label>
            <div class="col-lg-9">
               <input type="text" name="webmaniabrnfeaccess_token" id="webmaniabrnfeaccess_token" value="<?php echo $fields_values[$this->name.'access_token']; ?>" class="" size="50" required="required">
            </div>
         </div>
         <div class="form-group">
            <label class="control-label col-lg-3 required">
            Access Token Secret
            </label>
            <div class="col-lg-9">
               <input type="text" name="webmaniabrnfeaccess_token_secret" id="webmaniabrnfeaccess_token_secret" value="<?php echo $fields_values[$this->name.'access_token_secret']; ?>" class="" size="50" required="required">
            </div>
         </div>
         <div class="form-group">
            <label class="control-label col-lg-3">
            Ambiente Sefaz
            </label>
            <div class="col-lg-9">
               <div class="radio ">
                  <label><input type="radio" name="webmaniabrnfesefaz_env" id="production" value="1" <?php if($fields_values[$this->name.'sefaz_env'] == 1) echo 'checked="checked"'; ?>>Produção</label>
               </div>
               <div class="radio ">
                  <label><input type="radio" name="webmaniabrnfesefaz_env" id="development" value="2" <?php if($fields_values[$this->name.'sefaz_env'] == 2) echo 'checked="checked"'; ?>>Desenvolvimento</label>
               </div>
            </div>
         </div>
      </div>
      <!-- /.form-wrapper -->
   </div>
   <div class="panel" id="fieldset_1_1">
      <div class="panel-heading">
         Configuração Padrão
      </div>
      <div class="form-wrapper">
         <div class="form-group">
            <label class="control-label col-lg-3">
            Emissão Automática
            </label>
            <div class="col-lg-9">
               <div class="radio ">
                  <label><input type="radio" name="webmaniabrnfeautomatic_emit" id="on" value="on" <?php if($fields_values[$this->name.'automatic_emit'] == 'on') echo 'checked="checked"'; ?>>Ativado</label>
               </div>
               <div class="radio ">
                  <label><input type="radio" name="webmaniabrnfeautomatic_emit" id="off" value="off" <?php if($fields_values[$this->name.'automatic_emit'] == 'off') echo 'checked="checked"'; ?>>Desativado</label>
               </div>
               <p class="help-block">
                  Emitir automaticamente a NF-e sempre que que um pagamento for confirmado
               </p>
            </div>
         </div>
         <div class="form-group">
            <label class="control-label col-lg-3">
            Envio automático de email
            
            </label>
            <div class="col-lg-9">
               <div class="radio ">
                  <label><input type="radio" name="webmaniabrnfeenvio_email" id="on" value="on" <?php if($fields_values[$this->name.'envio_email'] == 'on') echo 'checked="checked"'; ?>>Ativado</label>
               </div>
               <div class="radio ">
                  <label><input type="radio" name="webmaniabrnfeenvio_email" id="off" value="off" <?php if($fields_values[$this->name.'envio_email'] == 'off') echo 'checked="checked"'; ?>>Desativado</label>
               </div>
               <p class="help-block">
                  <em style="color:red">Atenção: O email será enviado mesmo para notas emitidas em ambiente de homologação!</em>
               </p>
            </div>
         </div>
         <div class="form-group">
            <label class="control-label col-lg-3">
            Natureza da Operação
            </label>
            <div class="col-lg-9">
               <input type="text" name="webmaniabrnfeoperation_type" id="webmaniabrnfeoperation_type" value="<?php echo $fields_values[$this->name.'operation_type']; ?>" class="" size="50">
            </div>
         </div>
         <div class="form-group">
            <label class="control-label col-lg-3">
            Classe de Imposto
            </label>
            <div class="col-lg-9">
               <input type="text" name="webmaniabrnfetax_class" id="webmaniabrnfetax_class" value="<?php echo $fields_values[$this->name.'tax_class']; ?>" class="" size="50">
            </div>
         </div>
         <div class="form-group">
            <label class="control-label col-lg-3">
            GTIN (Antigo código EAN)
            </label>
            <div class="col-lg-9">
               <input type="text" name="webmaniabrnfeean_barcode" id="webmaniabrnfeean_barcode" value="<?php echo $fields_values[$this->name.'ean_barcode']; ?>" class="" size="50">
            </div>
         </div>
         <div class="form-group">
            <label class="control-label col-lg-3">
            GTIN tributável
            </label>
            <div class="col-lg-9">
               <input type="text" name="webmaniabrnfegtin_tributavel" id="webmaniabrnfegtin_tributavel" value="<?php echo $fields_values[$this->name.'gtin_tributavel']; ?>" class="" size="50">
            </div>
         </div>
         <div class="form-group">
            <label class="control-label col-lg-3">
            Código NCM
            </label>
            <div class="col-lg-9">
               <input type="text" name="webmaniabrnfencm_code" id="webmaniabrnfencm_code" value="<?php echo $fields_values[$this->name.'ncm_code']; ?>" class="" size="50">
            </div>
         </div>
         <div class="form-group">
            <label class="control-label col-lg-3">
            Código CEST
            </label>
            <div class="col-lg-9">
               <input type="text" name="webmaniabrnfecest_code" id="webmaniabrnfecest_code" value="<?php echo $fields_values[$this->name.'cest_code']; ?>" class="" size="50">
            </div>
         </div>
         
         <div class="form-group">
            <label class="control-label col-lg-3">
            CNPJ do fabricante da mercadoria 
            </label>
            <div class="col-lg-9">
               <input type="text" name="webmaniabrnfecnpj_fabricante" id="webmaniabrnfecnpj_fabricante" value="<?php echo $fields_values[$this->name.'cnpj_fabricante']; ?>" class="" size="50">
            </div>
         </div>
         
         <div class="form-group">
            <label class="control-label col-lg-3">
            Indicador de escala relevante 
            </label>
            <div class="col-lg-9">
               <select name="webmaniabrnfeind_escala" class=" fixed-width-xl" id="webmaniabrnfeind_escala">
                 <option value="">Selecionar</option>
                 <?php 
                 
                 $options = array(
                   'S' => 'S - Produzido em Escala Relevante',
                   'N' => 'N - Produzido em Escala NÃO Relevante',
                  );
                  
                  foreach($options as $value => $label){
                    
                    $selected = $value == $fields_values[$this->name.'ind_escala'] ? 'selected' : '';
                    echo '<option value="'.$value.'" '.$selected.'>'.$label.'</option>';
                    
                  }
                  
                  ?>

               </select>
            </div>
         </div>         
         
         <div class="form-group">
            <label class="control-label col-lg-3">
            Origem dos Produtos
            </label>
            <div class="col-lg-9">
               <select name="webmaniabrnfeproduct_source" class=" fixed-width-xl" id="webmaniabrnfeproduct_source">
                 
                 <?php 
                 
                 $options = array(
                   '-1' => 'Selecionar Origem dos Produtos',
                   '00' => '0 - Nacional, exceto as indicadas nos códigos 3, 4, 5, e 8',
                   '1'  => '1 - Estrangeira - Importação direta, exceto a indicada no código 6',
                   '2'  => '2 - Estrangeira - Adquirida no mercado interno, exceto a indica no código 7',
                   '3'  => '3 - Nacional, mercadoria ou bem com Conteúdo de Importação superior a 40% e inferior ou igual a 70%',
                   '4'  => '4 - Nacional, cuja produção tenha sido feita em conformidade com os processos produtivos básicos de que tratam as legislações citadas nos Ajustes',
                   '5'  => '5 - Nacional, mercadoria ou bem com Conteúdo de Importação inferior ou igual a 40%',
                   '6'  => '6 - Estrangeira - Importação direta, sem similar nacional, constante em lista da CAMEX e gás natural',
                   '7'  => '7 - Estrangeira - Adquirida no mercado interno, sem similar nacional, constante lista CAMEX e gás natural',
                   '8'  => '8 - Nacional, mercadoria ou bem com Conteúdo de Importação superior a 70%',
                  );
                  
                  foreach($options as $value => $label){
                    
                    $selected = $value == $fields_values[$this->name.'product_source'] ? 'selected' : '';
                    echo '<option value="'.$value.'" '.$selected.'>'.$label.'</option>';
                    
                  }
                  
                  ?>

               </select>
            </div>
         </div>
      </div>
      <!-- /.form-wrapper -->
   </div>

   <div class="panel" id="fieldset_2_1">
      <div class="panel-heading">
         Indicativo de intermediador
      </div>
      <div class="form-wrapper">
         <div class="form-group">
            <label class="control-label col-lg-3">
            Intermediador da operação
            </label>
            <div class="col-lg-9">
               <select name="webmaniabrnfeintermediador" class=" fixed-width-xl" id="webmaniabrnfeintermediador">
                  <option value="00" <?php echo $fields_values[$this->name.'intermediador'] == '00' ? 'selected' : ''; ?>>0 - Operação sem intermediador (em site ou plataforma própria)</option>
                  <option value="1" <?php echo $fields_values[$this->name.'intermediador'] == '1' ? 'selected' : ''; ?>>1 - Operação em site ou plataforma de terceiros (intermediadores/marketplace)</option>
               </select>
            </div>
         </div>
         <div class="form-group">
            <label class="control-label col-lg-3">
            CNPJ do Intermediador
            </label>
            <div class="col-lg-9">
               <input type="text" name="webmaniabrnfeintermediador_cnpj" id="webmaniabrnfeintermediador_cnpj" value="<?php echo $fields_values[$this->name.'intermediador_cnpj']; ?>" class="" size="50">
            </div>
         </div>
         <div class="form-group">
            <label class="control-label col-lg-3">
            ID do Intermediador
            </label>
            <div class="col-lg-9">
               <input type="text" name="webmaniabrnfeintermediador_id" id="webmaniabrnfeintermediador_id" value="<?php echo $fields_values[$this->name.'intermediador_id']; ?>" class="" size="50">
            </div>
         </div>
      </div>
      <!-- /.form-wrapper -->
   </div>

   <div class="panel" id="fieldset_2_2">
      <div class="panel-heading">
         Informações Complementares (Opcional)
      </div>
      <div class="form-wrapper">
         <div class="form-group">
            <label class="control-label col-lg-3">
            Informações ao Fisco
            </label>
            <div class="col-lg-9">
               <textarea name="webmaniabrnfefisco_inf" id="webmaniabrnfefisco_inf" cols="50" class="textarea-autosize" style="overflow: hidden; word-wrap: break-word; resize: none; height: 48px;" value="<?php echo $fields_values[$this->name.'fisco_inf']; ?>"><?php echo $fields_values[$this->name.'fisco_inf']; ?></textarea>
            </div>
         </div>
         <div class="form-group">
            <label class="control-label col-lg-3">
            Informações Complementares ao Consumidor
            </label>
            <div class="col-lg-9">
               <textarea name="webmaniabrnfecons_inf" id="webmaniabrnfecons_inf" cols="50" class="textarea-autosize" style="overflow: hidden; word-wrap: break-word; resize: none; height: 48px;" value="<?php echo $fields_values[$this->name.'cons_inf']; ?>"><?php echo $fields_values[$this->name.'cons_inf']; ?></textarea>
            </div>
         </div>
      </div>
      <!-- /.form-wrapper -->
   </div>
   <div class="panel" id="fieldset_3_3">
      <div class="panel-heading">
         Campos obrigatórios (Banco de Dados)
      </div>
      <div class="form-wrapper">
         <div class="form-group">
            <label class="control-label col-lg-3">
            Adicionar campos CPF/CNPJ?
            </label>
            <div class="col-lg-9">
               <div class="radio ">
                  <label><input type="radio" name="webmaniabrnfecpf_cnpj_status" id="on" value="on" <?php if($fields_values[$this->name.'cpf_cnpj_status'] == 'on') echo 'checked="checked"'; ?>>Ativado</label>
               </div>
               <div class="radio ">
                  <label><input type="radio" name="webmaniabrnfecpf_cnpj_status" id="off" value="off" <?php if($fields_values[$this->name.'cpf_cnpj_status'] == 'off') echo 'checked="checked"'; ?>>Desativado</label>
               </div>
            </div>
         </div>
         
         <div class="form-group" style="<?php if($fields_values[$this->name.'cpf_cnpj_status'] == 'on') echo 'display:none'; ?>">
            <label class="control-label col-lg-3">
               Mapeamento
            </label>
            <div class="col-lg-9">
              
              
              
               <table class="table nfe-docs-table">
                  <thead>
                    <th></th>
                    <th>CPF</th>
                    <th>CNPJ</th>
                    <th>Razão Social</th>
                    <th>Inscrição Estadual</th>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Tabela</td>
                      <td><?php echo $this->get_tables_select_element('cpf'); ?></td>
                      <td><?php echo $this->get_tables_select_element('cnpj'); ?></td>
                      <td><?php echo $this->get_tables_select_element('rs'); ?></td>
                      <td><?php echo $this->get_tables_select_element('ie'); ?></td>
                    </tr>
                    <tr>
                      <td>Coluna</td>
                      <td><?php echo $this->get_columns_select_element('cpf'); ?></td>
                      <td><?php echo $this->get_columns_select_element('cnpj'); ?></td>
                      <td><?php echo $this->get_columns_select_element('rs'); ?></td>
                      <td><?php echo $this->get_columns_select_element('ie'); ?></td>
                    </tr>
                  </tbody>
               </table>
            </div>
         </div>
         

         <div class="form-group">
            <label class="control-label col-lg-3">
            Ativar campo Número?
            </label>
            <div class="col-lg-9">
               <div class="radio ">
                  <label><input type="radio" name="webmaniabrnfenumero_compl_status" id="on" value="on" <?php if($fields_values[$this->name.'numero_compl_status'] == 'on') echo 'checked="checked"'; ?>>Ativado</label>
               </div>
               <div class="radio ">
                  <label><input type="radio" name="webmaniabrnfenumero_compl_status" id="off" value="off" <?php if($fields_values[$this->name.'numero_compl_status'] == 'off') echo 'checked="checked"'; ?>>Desativado</label>
               </div>
            </div>
         </div>
         <div class="form-group">
            <label class="control-label col-lg-3">
            Nome do campo Número (Endereço)
            </label>
            <div class="col-lg-9">
               <input type="text" name="webmaniabrnfenumero_field" id="webmaniabrnfenumero_field" value="address_number" class="" size="50">
            </div>
         </div>
      </div>
      <!-- /.form-wrapper -->
   </div>
   <div class="panel" id="fieldset_4_4">
      <div class="panel-heading">
         Opções Adicionais
      </div>
      <div class="form-wrapper">
         <div class="form-group">
            <label class="control-label col-lg-3">
            Habilitar Máscara de Campos
            </label>
            <div class="col-lg-9">
               <div class="radio ">
                  <label><input type="radio" name="webmaniabrnfemask_fields" id="mask_on" value="on" <?php if($fields_values[$this->name.'mask_fields'] == 'on') echo 'checked="checked"'; ?>>Ativado</label>
               </div>
               <div class="radio ">
                  <label><input type="radio" name="webmaniabrnfemask_fields" id="mask_off" value="off" <?php if($fields_values[$this->name.'mask_fields'] == 'off') echo 'checked="checked"'; ?>>Desativado</label>
               </div>
            </div>
         </div>
         <div class="form-group">
            <label class="control-label col-lg-3">
            Habilitar Preenchimento Automático de Endereço
            </label>
            <div class="col-lg-9">
               <div class="radio ">
                  <label><input type="radio" name="webmaniabrnfefill_address" id="fill_address_on" value="on" <?php if($fields_values[$this->name.'fill_address'] == 'on') echo 'checked="checked"'; ?>>Ativado</label>
               </div>
               <div class="radio ">
                  <label><input type="radio" name="webmaniabrnfefill_address" id="fill_address_off" value="off" <?php if($fields_values[$this->name.'fill_address'] == 'off') echo 'checked="checked"'; ?>>Desativado</label>
               </div>
            </div>
         </div>
      </div>
      <!-- /.form-wrapper -->
   </div>
   <div class="panel" id="fieldset_7_7">
      <div class="panel-heading">
         Informações de pagamento
      </div>
      <div class="form-wrapper">
         <div class="form-group">
            <label class="control-label col-lg-3">
            <span class="label-tooltip" data-toggle="tooltip" data-html="true" title="" data-original-title="Relacione os métodos de pagamento à forma de pagamento">
            Métodos de pagamento
            </span>
            </label>
            <div class="col-lg-9">
               <table class="table">
                  <thead>
                     <th>Método</th>
                     <th>Forma de Pagamento</th>
                     <th class="payment-desc-title">Descrição do Pagamento</th>
                  </thead>
                  <tbody>
                     <?php 
                        $payment_methods = $this->get_payment_methods_options();
                        foreach($payment_methods as $method):
                           
                           $saved_value = Configuration::get('webmaniabrnfepayment_'.$method['value']);
                           $saved_desc = Configuration::get('webmaniabrnfepayment_'.$method['value'].'_desc');
                           
                     ?>
                           <tr>
                              <td><?php echo $method['label']; ?></td>
                              <td>
                                 <select style="max-width: 300px" class="webmaniabrnfepayment-methods-sel" name="webmaniabrnfepayment_<?php echo $method['value']; ?>">
                                    <option value="">Selecionar</option>
                                 <?php 
                                    $options = array(
                           				'01' => 'Dinheiro',
                           				'02' => 'Cheque',
                           				'03' => 'Cartão de Crédito',
                           				'04' => 'Cartão de Débito',
                                       '05' => 'Crédito Loja',
                                       '10' => 'Vale Alimentação',
                                       '11' => 'Vale Refeição',
                                       '12' => 'Vale Presente',
                                       '13' => 'Vale Combustível',
                                       '14' => 'Duplicata Mercantil',
                                       '15' => 'Boleto Bancário',
                           				'16' => 'Depósito Bancário',
                                       '17' => 'Pagamento Instantâneo (PIX)',
                                       '18' => 'Transferência bancária, Carteira Digital',
                                       '19' => 'Programa de fidelidade, Cashback, Crédito Virtual',
                           				'90' => 'Sem pagamento',
                           				'99' => 'Outros',
                           			);
                           			
                           			foreach($options as $value => $label){
                           			  
                           			  $selected = $saved_value == $value ? 'selected' : '';
                           			  
                           			  echo '<option value="'.$value.'" '.$selected.'>'.$label.'</option>';
                              		}
                        			
                        	        ?>
                        	        </select>
                              </td>
                              <td>
                                 <input type="text" class="webmaniabrnfepayment-desc" name="webmaniabrnfepayment_<?php echo $method['value']; ?>_desc" value="<?php echo $saved_desc; ?>" style="<?php echo ($saved_value != '99') ? 'display:none;' : ''; ?>" />
                              </td>
                           </tr>
                           
                     <?php endforeach; ?>
                  </tbody>
               </table>
            </div>
         </div>
      
      </div>
      <!-- /.form-wrapper -->
   </div>
   <div class="panel" id="fieldset_5_5">
      <div class="panel-heading">
         Transportadoras
      </div>
      <div class="form-wrapper">
         <div class="form-group">
            <label class="control-label col-lg-3">
            <span class="label-tooltip" data-toggle="tooltip" data-html="true" title="" data-original-title="Incluir dados da transportadora em pedidos enviados com o método configurado
               ">
            Incluir dados da transportadora na NF-e
            </span>
            </label>
            <div class="col-lg-9">
               <div class="radio ">
                  <label><input type="radio" name="webmaniabrnfetransp_include" id="include_on" value="on" <?php if($fields_values[$this->name.'transp_include'] == 'on') echo 'checked="checked"'; ?>>Ativado</label>
               </div>
               <div class="radio ">
                  <label><input type="radio" name="webmaniabrnfetransp_include" id="include_off" value="off" <?php if($fields_values[$this->name.'transp_include'] == 'off') echo 'checked="checked"'; ?>>Desativado</label>
               </div>
            </div>
         </div>
         
         <div class="form-group">
            <label class="control-label col-lg-3">
            Transportadoras
            </label>
            <div class="col-lg-9">
              <div class="carriers-list">
                <?php
                
                  foreach($carriers as $carrier){
                    echo '<div class="carrier-item" data-id="'.$carrier['id'].'">
                            <p>'.$carrier['razao_social'].' <br/>(<span>Editar</span>)</p>
                            <span class="delete">x</span>
                          </div>';
                  }
                  
                ?>
              </div>
              <button type="button" class="btn btn-primary btn--add-carrier" data-toggle="modal" data-target="#add-carrier-modal">Adicionar transportadora</button>
              <input type="hidden"  name="webmaniabrnfecarriers" value="<?php echo str_replace('%', '=', $fields_values[$this->name.'carriers']); ?>" />
            </div>
         </div>
         
      </div>
      <!-- /.form-wrapper -->
   </div>
   <div class="panel" id="fieldset_6_6">
      <div class="panel-heading">
         Salvar alteraçoes
      </div>
      <div class="panel-footer">
         <button type="submit" value="1" id="configuration_form_submit_btn_6" name="submitwebmaniabrnfe" class="button">
         <i class="process-icon-save"></i> Salvar
         </button>
      </div>
   </div>
</form>

<!-- Modal -->
<div class="modal fade" id="add-carrier-modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="myModalLabel">Nova Transportadora</h4>
      </div>
      <div class="modal-body form-horizontal">
        <div class="form-group" style="padding-top:0">
              <label class="control-label col-sm-3">Método de entrega</label>
              <div class="col-sm-9">
                  <select style="margin-top:10px" name="webmaniabrnfe_transp_method">
                    <?php
                    
                    $methods = $this->get_shipping_methods_options();
                    
                    foreach($methods as $method){
                      $selected = '';
                      //if($id == $webmaniabrnfe_transp_method){
                        //$selected = 'selected';
                      //}
                      echo '<option value="'.$method['id_option'].'" '.$selected.'>'.$method['name'].'</option>';
                    }
          
                    ?>
                  </select>
              </div>
          </div>

          <div class="form-group">
              <label class="control-label col-sm-3">Razão Social</label>
              <div class="col-sm-9">
                  <input type="text" class="form-control" name="webmaniabrnfe_transp_rs" value="<?php //echo $webmaniabrnfe_transp_rs; ?>">
              </div>
          </div>

          <div class="form-group">
              <label class="control-label col-sm-3">CNPJ</label>
              <div class="col-sm-9">
                  <input type="text" class="form-control" name="webmaniabrnfe_transp_cnpj" value="<?php //echo $webmaniabrnfe_transp_cnpj; ?>">
              </div>
          </div>

          <div class="form-group">
              <label class="control-label col-sm-3">Inscrição Estadual</label>
              <div class="col-sm-9">
                  <input type="text" class="form-control" name="webmaniabrnfe_transp_ie" value="<?php //echo $webmaniabrnfe_transp_ie; ?>">
              </div>
          </div>

          <div class="form-group">
              <label class="control-label col-sm-3">Endereço</label>
              <div class="col-sm-9">
                  <input type="text" class="form-control" name="webmaniabrnfe_transp_address" value="<?php //echo $webmaniabrnfe_transp_address; ?>">
              </div>
          </div>

          <div class="form-group">
              <label class="control-label col-sm-3">CEP</label>
              <div class="col-sm-9">
                  <input type="text" class="form-control" name="webmaniabrnfe_transp_cep" value="<?php //echo $webmaniabrnfe_transp_cep; ?>">
              </div>
          </div>

          <div class="form-group">
              <label class="control-label col-sm-3">Cidade</label>
              <div class="col-sm-9">
                  <input type="text" class="form-control" name="webmaniabrnfe_transp_city" value="<?php //echo $webmaniabrnfe_transp_city; ?>">
              </div>
          </div>

          <div class="form-group">
              <label class="control-label col-sm-3">UF</label>
              <div class="col-sm-9">
                  <input type="text" class="form-control" name="webmaniabrnfe_transp_uf" value="<?php //echo $webmaniabrnfe_transp_uf; ?>">
              </div>
          </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary btn--confirm-carrier">Adicionar</button>
        <input type="hidden" name = "current-edit" value="" />
      </div>
    </div>
  </div>
</div>