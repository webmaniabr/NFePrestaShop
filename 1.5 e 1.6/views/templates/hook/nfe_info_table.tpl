<div class="panel kpi-container" id="nfe_emitidas">
  <div class="row">
    <h3 style="padding-left:15px;">Notas emitidas para este pedido</h3>
    <table class="wp-list-table widefat" width="100%" cellspacing="0">
      <thead>
        <tr>
          <tr>
            <th id="columnname" class="manage-column column-columnname" scope="col" width="15%">Data</th>
            <th id="columnname" class="manage-column column-columnname" scope="col" width="5%">Série</th>
            <th id="columnname" class="manage-column column-columnname" scope="col" width="5%">Nº</th>
            <th id="columnname" class="manage-column column-columnname" scope="col" width="15%">RPS</th>
            <th id="columnname" class="manage-column column-columnname" scope="col">Código Verificação</th>
            <th id="columnname" class="manage-column column-columnname" scope="col" width="10%">Arquivo XML</th>
            <th id="columnname" class="manage-column column-columnname" scope="col" width="10%">Danfe</th>
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
          <td class="column-columnname"><a target="_blank" href="{$order_nfe['url_xml']}">Download XML</a></td>
          <td class="column-columnname"><a target="_blank" href="{$order_nfe['url_danfe']}">Visualizar Nota</a></td>
          <td class="column-columnname" style="padding: 10px 10px;">
            <span class="nfe-status {$order_nfe['status']}">{$order_nfe['status']}</span>
            <a href="{$url}&atualizar=1&chave={$order_nfe['chave_acesso']}">Atualizar Status</a>
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
