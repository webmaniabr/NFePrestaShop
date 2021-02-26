<div id="nfe-product-config-tab" class="product-tab-content">

  <div class="product-tab">
    <h3 class="tab">Informações Fiscais (Opcional)</h3>
    <div class="checkbox">
      <label>
        <input type="checkbox" name="nfe_ignorar_nfe" value="1" {if $ignorar_nfe == 1} checked {/if} style="margin-top:20px"/>Ignorar produto ao emitir NFe
      </label>
    </div>

    <div class="form-group">
      <label class="form-control-label">Classe de Imposto</label>
      <div class="row">
        <div class="col-md-6">
          <input type="text" name="nfe_tax_class" value="{$tax_class}" class="form-control"/>
        </div>
      </div>
    </div>

    <div class="form-group">
      <label class="form-control-label">GTIN (Antigo código EAN)</label>
      <div class="row">
        <div class="col-md-6">
          <input type="text" name="nfe_ean_bar_code" value="{$ean_bar_code}" class="form-control"/>
        </div>
      </div>
    </div>
    
    <div class="form-group">
      <label class="form-control-label">GTIN Tributável</label>
      <div class="row">
        <div class="col-md-6">
          <input type="text" name="nfe_gtin_tributavel" value="{$gtin_tributavel}" class="form-control"/>
        </div>
      </div>
    </div>

    <div class="form-group">
      <label class="form-control-label">Código NCM</label>
      <div class="row">
        <div class="col-md-6">
          <input type="text" name="nfe_ncm_code" value="{$ncm_code}" class="form-control"/>
        </div>
      </div>
    </div>

    <div class="form-group">
      <label class="form-control-label">Código CEST</label>
      <div class="row">
        <div class="col-md-6">
          <input type="text" name="nfe_cest_code" value="{$cest_code}" class="form-control"/>
        </div>
      </div>
    </div>
    
    <div class="form-group">
      <label class="form-control-label">CNPJ do fabricante da mercadoria</label>
      <div class="row">
        <div class="col-md-6">
          <input type="text" name="nfe_cnpj_fabricante" value="{$cnpj_fabricante}" class="form-control"/>  
        </div>
      </div>
    </div>
    
    <div class="form-group">
      <label class="form-control-label">Indicador de escala relevante</label>
      <div class="row">
        <div class="col-md-6">
          <select class="form-control" name="nfe_ind_escala" value="{$nfe_ind_escala}">
            <option value="">Selecionar</option>
              {assign var=options value=[
                'S' => 'S - Produzido em Escala Relevante',
                'N' => 'N - Produzido em Escala NÃO Relevante']
              }
            {foreach from=$options item=item key=key}
            {assign var=select value=''}
            {if $key == $ind_escala}
            {$select = 'selected'}
            {else}
            {$select = ''}
            {/if}
            <option value="{$key}" {$select}>{$item}</option>
            {/foreach}
          </select>
        </div>
      </div>
    </div>
    
    
    <div class="form-group">
      <label class="form-control-label">Origem</label>
      <div class="row">
        <div class="col-md-6">
          <select class="form-control" name="nfe_product_source" value="{$product_source}">
            {assign var=options value=[
                '-1' => 'Selecionar Origem do Produto',
                '0' => '0 - Nacional, exceto as indicadas nos códigos 3, 4, 5, e 8',
                '1' => '1 - Estrangeira - Importação direta, exceto a indicada no código 6',
                '2' => '2 - Estrangeira - Adquirida no mercado interno, exceto a indica no código 7',
                '3' => '3 - Nacional, mercadoria ou bem com Conteúdo de Importação superior a 40% e inferior ou igual a 70%',
                '4' => '4 - Nacional, cuja produção tenha sido feita em conformidade com os processos produtivos básicos de que tratam as legislações citadas nos Ajustes',
                '5' => '5 - Nacional, mercadoria ou bem com Conteúdo de Importação inferior ou igual a 40%',
                '6' => '6 - Estrangeira - Importação direta, sem similar nacional, constante em lista da CAMEX e gás natural',
                '7' => '7 - Estrangeira - Adquirida no mercado interno, sem similar nacional, constante lista CAMEX e gás natural',
                '8' => '8 - Nacional, mercadoria ou bem com Conteúdo de Importação superior a 70%']
              }
            {foreach from=$options item=item key=key}
            {assign var=select value=''}
            {if $key == $product_source}
            {$select = 'selected'}
            {else}
            {$select = ''}
            {/if}
            <option value="{$key}" {$select}>{$item}</option>
            {/foreach}
          </select>
        </div>
      </div>
    </div>

  </div>
</div>
