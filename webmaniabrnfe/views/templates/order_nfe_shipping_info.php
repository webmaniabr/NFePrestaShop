<?php
if (!defined('_PS_VERSION_')) {
  exit;
}

// displayAdminOrderContentShip (1.6) passes array('order' => Order); displayAdminOrderTabContent
// (1.7.7+) passes array('id_order' => int) as $params.
if (isset($order['order']) && is_object($order['order']) && isset($order['order']->id)) {
  $order_id = (int) $order['order']->id;
} else {
  $order_id = isset($params['id_order']) ? (int) $params['id_order'] : 0;
}
$transporte_info = Db::getInstance()->getRow("SELECT nfe_modalidade_frete, nfe_volumes, nfe_especie, nfe_peso_bruto, nfe_peso_liquido, nfe_valor_seguro FROM "._DB_PREFIX_."orders WHERE id_order = ".$order_id);
if (!is_array($transporte_info)) { $transporte_info = array(); }
$transporte_value = function ($key) use ($transporte_info) {
  return isset($transporte_info[$key]) ? htmlspecialchars((string) $transporte_info[$key], ENT_QUOTES, 'UTF-8') : '';
};

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

          $selected = isset($transporte_info['nfe_modalidade_frete']) && $transporte_info['nfe_modalidade_frete'] == $value ? 'selected' : '';
          echo '<option value="'.$value.'" '.$selected.'>'.$label.'</option>';

        }

        ?>

      </select>
    </div>
    <hr />

    <p style="font-size:14px;line-height:1.5em;font-weight:bold;color:red">Volumes Transportados</p>
    <div class="form-group">
      <label>Volumes</label>
      <input name="nfe_volumes" type="text" value="<?php echo $transporte_value('nfe_volumes'); ?>"/>
    </div>

    <div class="form-group">
      <label>Espécie</label>
      <input type="text" name="nfe_especie" value="<?php echo $transporte_value('nfe_especie'); ?>"/>
    </div>

    <div class="form-group">
      <label>Peso Bruto (KG)</label>
      <input type="text" name="nfe_peso_bruto" placeholder="EX: 50.210 = 50,210KG" value="<?php echo $transporte_value('nfe_peso_bruto'); ?>"/>
    </div>

    <div class="form-group">
      <label>Peso Líquido (KG)</label>
      <input type="text" name="nfe_peso_liquido" placeholder="EX: 50.210 = 50,210KG" value="<?php echo $transporte_value('nfe_peso_liquido'); ?>"/>
    </div>

    <div class="form-group">
      <label>Valor seguro</label>
      <input type="text" name="nfe_valor_seguro" placeholder="EX: 10.20" value="<?php echo $transporte_value('nfe_valor_seguro'); ?>"/>
    </div>

    <input type="hidden" class="btn btn-primary" name="nfe-transporte-info" value="1" />
    <input type="submit" value="Salvar" class="btn btn-primary" />

  </form>
</div>
