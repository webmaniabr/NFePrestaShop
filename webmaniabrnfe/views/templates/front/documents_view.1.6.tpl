<h2 class="page-heading">Editar CPF/CNPJ</h2>
<div id="document-types">
  {if !empty($errors)}
  <div class="alert alert-danger">
      <ul>
          {foreach from=$errors item=error}
              <li>{$error}</li>
          {/foreach}
      </ul>
  </div>
  {/if}
  {if !empty($confirmations)}
  <div class="alert alert-success">
      <ul>
          {foreach from=$confirmations item=confirmation}
              <li>{$confirmation}</li>
          {/foreach}
      </ul>
  </div>
  {/if}
  <form>
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
  <div id="cpf-field" class="form-group {if $document_type != 'cnpj'} active{/if}" {if $document_type == 'cnpj'} style="display:none;overflow:hidden" {/if}>
    <label>CPF <sup>*</sup></label>
    <input type="text" class="form-control" name="cpf" id="cpf-input" value="{if $document_type == 'cpf'}{$document_number}{/if}"/>
  </div>
  <div id="cnpj-field" {if $document_type != 'cnpj'} style="display:none" {/if}  {if $document_type == 'cnpj'} class="active" style="display:block;overflow:hidden" {/if}>
    <div class="form-group">
      <label>Razão Social <sup>*</sup></label>
      <input type="text" class="form-control" name="razao_social" value="{if $document_type == 'cnpj'} {$razao_social}{/if}"/>
    </div>
    <div class="form-group">
      <label>CNPJ <sup>*</sup></label>
      <input type="text" class="form-control" name="cnpj" id="cnpj-input" value="{if $document_type == 'cnpj'}{$document_number}{/if}" />
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
  #left_column{
    display: none;
  }

  .form-control{
    max-width: 300px;
  }
</style>
