<div class="panel kpi-container" id="nfe_emitidas">
  <div class="row">
    <h3 style="padding-left:15px;">Informações de Transporte</h3>
    <p style="margin-bottom:30px">Informações complementares na emissão de Nota Fiscal para pedidos enviados via Transportadora</p>

    <form id="nfe-transporte-info" method="POST">
    <div class="form-group nfe-form-group">
      <label class="control-label col-md-2">Modalidade do frete</label>
      <div class="col-md-6">

        <select name="nfe_modalidade_frete">
          {assign var=options value=[
              '0' => 'Por conta do emitente',
              '1' => 'Por conta do destinatário/remetente',
              '2' => 'Por conta de terceiros',
              '9' => 'Sem frete'
              ]
            }
            {foreach from=$options item=item key=key}
            {assign var=select value=''}
            {if $key == $transporte_info["nfe_modalidade_frete"]}
            {$select = 'selected'}
            {else}
            {$select = ''}
            {/if}
            <option value="{$key}" {$select}>{$item}</option>
            {/foreach}
        </select>
      </div>
    </div>

    <h4 style="margin-bottom:15px;">Volumes Transportados</h4>
    <div class="form-group nfe-form-group">
      <label class="control-label col-md-2">Volumes</label>
      <div class="col-md-6"><input type="text" name="nfe_volumes" value="{$transporte_info['nfe_volumes']}"/></div>
    </div>
    <div class="form-group nfe-form-group">
      <label class="control-label col-md-2">Espécie</label>
      <div class="col-md-6"><input type="text" name="nfe_especie" value="{$transporte_info['nfe_especie']}"/></div>
    </div>
    <div class="form-group nfe-form-group">
      <label class="control-label col-md-2">Peso Bruto</label>
      <div class="col-md-6"><input type="text" name="nfe_peso_bruto" value="{$transporte_info['nfe_peso_bruto']}" placeholder="Ex: 50.210 = 50,210KG"/></div>
    </div>
    <div class="form-group nfe-form-group">
      <label class="control-label col-md-2">Peso Líquido</label>
      <div class="col-md-6"><input type="text" name="nfe_peso_liquido" value="{$transporte_info['nfe_peso_liquido']}" placeholder="Ex: 50.210 = 50,210KG"/></div>
    </div>
    <input type="submit" class="btn btn-default" value="Salvar" />
    <input type="hidden" name="nfe-transporte-info" value="save-fields"  />
  </form>
  </div>
</div>

<style>

.nfe-form-group{
  overflow: hidden;
}

</style>
