<div class="panel card wmbr-emission-recovery"
     data-order="{$wmbr_recovery.order_id|escape:'html':'UTF-8'}"
     data-key="{$wmbr_recovery.key|escape:'html':'UTF-8'}"
     data-url="{$wmbr_recovery.url|escape:'html':'UTF-8'}"
     data-csrf="{$wmbr_recovery.csrf|escape:'html':'UTF-8'}">
  <div class="card-body">
    <h3>Emissão aguardando confirmação</h3>
    {if $wmbr_recovery.has_uuid}
      <p>Consulte a nota na Webmania e recupere sua confirmação neste pedido.</p>
      <button type="button" class="btn btn-default wmbr-recover-emission">Consultar e recuperar nota</button>
    {else}
      <p>Verifique no painel da Webmania se esta tentativa gerou uma nota. Libere somente se ela não existir. Aguarde pelo menos 10 minutos após o envio.</p>
      <label><input type="checkbox" class="wmbr-confirm-absent" value="1"> Conferi na Webmania e confirmo que esta tentativa não gerou uma nota.</label>
      <p><button type="button" class="btn btn-default wmbr-recover-emission">Liberar tentativa sem nota</button></p>
    {/if}
    <p class="wmbr-recovery-message" role="status" aria-live="polite"></p>
  </div>
</div>
