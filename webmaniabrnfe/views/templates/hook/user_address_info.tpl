<div class="panel kpi-container" id="nfe_emitidas">
  <div class="row">

    <h3 style="padding-left:15px;">Endereço do Cliente</h3>
      <p>Nome Completo: {$user_address_info['firstname']|escape:'html':'UTF-8'} {$user_address_info['lastname']|escape:'html':'UTF-8'}</p>
      <p>Endereço: {$user_address_info['address1']|escape:'html':'UTF-8'}</p>
      <p>Número: {$user_address_info['address_number']|escape:'html':'UTF-8'}</p>
      <p>Bairro: {$user_address_info['address2']|escape:'html':'UTF-8'}</p>
      <p>CEP: {$user_address_info['postcode']|escape:'html':'UTF-8'}</p>
      <p>Cidade: {$user_address_info['city']|escape:'html':'UTF-8'}</p>
      <p>Estado: {$state|escape:'html':'UTF-8'}</p>
      <p>Telefone: {$user_address_info['phone']|escape:'html':'UTF-8'}</p>

    </div>
  </div>
