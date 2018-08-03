<?php

$order_id = $order['order']->id;
$transporte_info = Db::getInstance()->getRow("SELECT nfe_modalidade_frete, nfe_volumes, nfe_especie, nfe_peso_bruto, nfe_peso_liquido FROM "._DB_PREFIX_."orders WHERE id_order = $order_id");

?>

<div class="tab-pane" id="nfe-shipping-info">
  
  <p>Informações complementares na emissão de Nota Fiscal<br/> para pedidos enviados via Transportadora.</p>
  <hr/>
  
  <form method="POST" class="bootstrap">
    <div class="form-group">
      <label>Modalidade do frete</label>
      <select name="nfe_modalidade_frete">
        
        <option value="">Contratação do Frete por conta do Remetente (CIF)</option>
        
        <?php 
        $options = array(
          '1' => 'Contratação do Frete por conta do Destinatário',
          '2' => 'Contratação do Frete por conta de Terceiros',
          '3' => 'Transporte próprio por conta do Remetente',
          '4' => 'Transporte próprio por conta do Destinatário',
          '9' => 'Sem ocorrência de Transporte',
        );
        
        foreach($options as $value => $label){
          
          $selected = $transporte_info['nfe_modalidade_frete'] == $value ? 'selected' : '';
          echo '<option value="'.$value.'" '.$selected.'>'.$label.'</option>';
          
        }
        
        ?>
        
      </select>
    </div>
    <hr />
    
    <p style="font-size:14px;line-height:1.5em;font-weight:bold;color:red">Volumes Transportados</p>
    <div class="form-group">
      <label>Volumes</label>
      <input name="nfe_volumes" type="text" value="<?php echo $transporte_info['nfe_volumes']; ?>"/>
    </div>
    
    <div class="form-group">
      <label>Espécie</label>
      <input type="text" name="nfe_especie" value="<?php echo $transporte_info['nfe_especie']; ?>"/>
    </div>
    
    <div class="form-group">
      <label>Peso Bruto (KG)</label>
      <input type="text" name="nfe_peso_bruto" placeholder="EX: 50.210 = 50,210KG" value="<?php echo $transporte_info['nfe_peso_bruto']; ?>"/>
    </div>
    
    <div class="form-group">
      <label>Peso Líquido (KG)</label>
      <input type="text" name="nfe_peso_liquido" placeholder="EX: 50.210 = 50,210KG" value="<?php echo $transporte_info['nfe_peso_liquido']; ?>"/>
    </div>
    
    <input type="hidden" class="btn btn-primary" name="nfe-transporte-info" value="1" />
    <input type="submit" value="Salvar" class="btn btn-primary" />
    
  </form>
</div>