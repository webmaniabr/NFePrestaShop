<div id="document-types" style="overflow:hidden;">
  <div class="clearfix">
    <label class="main-label-1_5">Tipo de pessoa:</label>
    <div class="radio-inline radio-1_5">
      <label class="top">
        <span><input type="radio" name="document_type" class="radio-cpf" data-rel="cpf" value="cpf" checked/></span>
        Pessoa Física
      </label>
    </div>
    <div class="radio-inline radio-1_5">
      <label class="top">
        <span><input type="radio" name="document_type" class="radio-cnpj" data-rel="cnpj" value="cnpj"/></span>
        Pessoa Jurídica
      </label>
    </div>
  </div>
  <div id="cpf-field" class="form-group active">
    <label class="main-label-1_5">CPF <sup>*</sup></label>
    <input type="text" class="form-control input-text-1_5" name="cpf" id="cpf-input"/>
  </div>
  <div id="cnpj-field" style="display:none">
    <div class="form-group">
      <label class="main-label-1_5">Razão Social <sup>*</sup></label>
      <input type="text" class="form-control input-text-1_5" name="razao_social" id="razao-social0input" style="margin-bottom:10px"/>
    </div>
    <div class="form-group">
      <label class="main-label-1_5">CNPJ <sup>*</sup></label>
      <input type="text" class="form-control input-text-1_5" name="cnpj" id="cnpj-input" style="margin-bottom:10px"/>
    </div>

    <div class="form-group">
      <label class="main-label-1_5">Inscrição estadual <sup>*</sup></label>
      <input type="text" class="form-control input-text-1_5" name="cnpj_ie" id="ie-input"/>
    </div>
  </div>
</div>

<style>
#document-types .main-label-1_5{
  display: inline-block;
    padding: 6px 15px;
    width: 230px;
    font-size: 14px;
    text-align: right;
}

#document-types .radio-1_5 .top{
      float: none;
}

</style>
<script>
jQuery(document).ready(function($){
  var target = $('.account_creation').find('input[name="customer_firstname"]').parents('p.required');
  $('#document-types').insertBefore(target);

  $('#cpf-input').mask('999.999.999-99');
  $('#cnpj-input').mask('99.999.999/9999-99');
});
</script>
