jQuery(document).ready(function(){

  console.log('teste');

  var category_id = $('input[name="id_category"]').val();

  var $parent = $('.form-wrapper').find('.form-group').first();

  var $cat_ncm = $('<div class="form-group"></div>');
  $cat_ncm.append('<label class="control-label col-lg-3"></label>');

  $cat_ncm.find('.col-lg-3').append('<span class="label-tooltip" data-toggle="tooltip" data-html="true" title=""data-original-title="Se definir o NCM, todos os produtos desta categoria herdarão seu NCM">NCM</span>');

  $cat_ncm.append('<div class="control-label col-lg-9"></div>');
  $cat_ncm.find('.col-lg-9').append('<input type="text" id="nfe_category_ncm_1" name="nfe_category_ncm_1" class="" value="" onkeyup="if (isArrowKey(event)) return ;updateFriendlyURL();" />');

  $cat_ncm.insertAfter($parent);

  if(category_id){

    $.ajax({
      method: 'POST',
      url:  '../modules/webmaniabrnfe/ajax.php',
      data:{
        method: 'getCategoryNcm',
        id_category : category_id,
        adminToken: sec_token,
      }
    }).done(function(response){
      
      var result = $.parseJSON(response);

      if(result.result == 'success'){
        $cat_ncm.find('input[name="nfe_category_ncm_1"]').val(result.ncm);
      }

    });

  }





});
