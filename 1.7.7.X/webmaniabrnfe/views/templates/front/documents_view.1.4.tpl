<h2 class="page-heading">Editar CPF/CNPJ</h2>
<div id="document-types">
  {if !empty($errors)}
  <div class="error">
      <ul style="list-style:none">
          {foreach from=$errors item=error}
              <li>{$error}</li>
          {/foreach}
      </ul>
  </div>
  {/if}
  {if !empty($confirmations)}
  <div class="success">
      <ul style="list-style:none">
          {foreach from=$confirmations item=confirmation}
              <li>{$confirmation}</li>
          {/foreach}
      </ul>
  </div>
  {/if}
  <form method="POST">
  <div class="clearfix">
    <label>Tipo de pessoa:</label><br/>
    {if $document_type == 'cnpj'}
    <div class="radio-inline">
      <label class="top">
        <span><input type="radio" name="document_type" class="radio-cpf" data-rel="cpf" value="cpf"/></span>
        Pessoa Física
      </label>
    </div>
    <div class="radio-inline">
      <label class="top">
        <span><input type="radio" name="document_type" class="radio-cnpj" data-rel="cnpj" value="cnpj" checked/></span>
        Pessoa Jurídica
      </label>
    </div>
    {else}
    <div class="radio-inline">
      <label class="top">
        <span><input type="radio" name="document_type" class="radio-cpf" data-rel="cpf" value="cpf" checked/></span>
        Pessoa Física
      </label>
    </div>
    <div class="radio-inline">
      <label class="top">
        <span><input type="radio" name="document_type" class="radio-cnpj" data-rel="cnpj" value="cnpj"/></span>
        Pessoa Jurídica
      </label>
    </div>
    {/if}
  </div>
  <div id="cpf-field" class="form-group {if $document_type != 'cnpj'} active{/if}" {if $document_type == 'cnpj'} style="display:none;" {/if}>
    <label>CPF <sup>*</sup></label>
    <input type="text" class="form-control" name="cpf" id="cpf-input" value="{if $document_type == 'cpf'}{$document_number}{/if}"/>
  </div>
  <div id="cnpj-field" {if $document_type != 'cnpj'} style="display:none" {/if}  {if $document_type == 'cnpj'} class="active" style="display:block;" {/if}>
    <div class="form-group">
      <label>Razão Social <sup>*</sup></label>
      <input type="text" class="form-control" name="razao_social" value="{if $document_type == 'cnpj'} {$razao_social}{/if}"/>
    </div>
    <div class="form-group">
      <label>CNPJ <sup>*</sup></label>
      <input type="text" class="form-control" name="cnpj" id="cnpj-input" value="{if $document_type == 'cnpj'} {$document_number}{/if}" />
    </div>
    <div class="form-group">
      <label>Inscrição estadual <sup>*</sup></label>
      <input type="text" class="form-control" name="cnpj_ie" id="ie-input" value="{if $document_type == 'cnpj'} {$pj_ie}{/if}"/>
    </div>
  </div>
  <div class="form-group">
      <button type="submit" name="submitDocs" class="btn btn-default button button-medium" id="submitAccount">
          <span>Salvar<i class="icon-chevron-right right"></i></span>
      </button>
  </div>
  <input type="hidden" name="document_types" value="sent" />
  </form>
</div>
<style>

#document-types{
  margin-bottom:50px;
}

  .form-control{
    max-width: 300px;
    margin-top:5px;
  }

  .radio-inline{
    margin-top:5px;
    display:inline-block;
    margin-right:10px;
  }

  #cnpj-field,
  #cpf-field{
    margin-top:15px;
    margin-bottom:15px;
  }

  #cnpj-field label,
  #cpf-field label{
    display:block;
    font-size:13px;
  }

  #cnpj-field input.form-control,
  #cpf-field input.form-control{
    border: 1px solid #c8cbd0;
    padding: 5px;
}

</style>
