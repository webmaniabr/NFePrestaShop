<div class="nfe_info card" id="nfe_emitidas">
  <div class="card-header">
    <h3 class="card-header-title">Notas emitidas para este pedido</h3>
  </div>
  <div class="card-body">
    <table class="wp-list-table" width="100%" cellspacing="0">
      <thead>
        <tr>
          <tr>
            <th id="columnname" class="manage-column column-columnname" scope="col" width="5%">Data</th>
            <th id="columnname" class="manage-column column-columnname" scope="col" width="5%">Série</th>
            <th id="columnname" class="manage-column column-columnname" scope="col" width="5%">Nº</th>
            <th id="columnname" class="manage-column column-columnname" scope="col" width="10%">RPS</th>
            <th id="columnname" class="manage-column column-columnname" scope="col">Código Verificação</th>
            <th id="columnname" class="manage-column column-columnname" scope="col" width="10%">Arquivo XML</th>
            <th id="columnname" class="manage-column column-columnname" scope="col" width="30%">PDF</th>
            <th id="columnname" class="manage-column column-columnname" scope="col" width="10%">Status</th>
          </tr>
        </tr>
      </thead>
      <tbody>
        {foreach $nfe_info_arr as $order_nfe}
        <tr><td class="column-columnname">{$order_nfe['data']}</td>
          <td class="column-columnname">{$order_nfe['n_serie']}</td>
          <td class="column-columnname">{$order_nfe['n_nfe']}</td>
          <td class="column-columnname">{$order_nfe['n_recibo']}</td>
          <td class="column-columnname">{$order_nfe['chave_acesso']}</td>
          <td class="column-columnname"><a class="" target="_blank" href="{$order_nfe['url_xml']}">Download XML</a></td>
          <td class="column-columnname">
            <a class="" target="_blank" href="{$order_nfe['url_danfe']}">Danfe </a>|
            <a class="" target="_blank" href="{$order_nfe['url_danfe_simplificada']}"> Danfe Simples </a>|
            <a class="" target="_blank" href="{$order_nfe['url_danfe_etiqueta']}"> Danfe Etiqueta</a>
          </td>
          <td class="column-columnname" style="padding: 10px 10px;">
            <span class="nfe-status {$order_nfe['status']}">{$order_nfe['status']}</span>
            <a href="{$url}&atualizar=1&chave={$order_nfe['chave_acesso']}" title="Atualizar status"><i class="material-icons">refresh</i></a>
          </td></tr>
          {/foreach}

        </tbody>
      </table>
    </div>
  </div>

  <style>
  .nfe-status {
    text-transform: capitalize;
    color: #FFF;
    padding: 2px 5px;
  }

  .nfe-status.aprovado {
    background-color: #46894b;
  }

  .nfe-status.reprovado,
  .nfe-status.cancelado {
    background-color: #ce3737;
  }

  .nfe-status.processando,
  .nfe-status.contingencia{
    background-color: #eccb28;
    color: #000;
    font-weight: 600;
  }

  </style>
