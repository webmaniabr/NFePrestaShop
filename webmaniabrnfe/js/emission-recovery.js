jQuery(function ($) {
    $(document).on('click', '.wmbr-recover-emission', function () {
        var button = $(this);
        var panel = button.closest('.wmbr-emission-recovery');
        var confirmation = panel.find('.wmbr-confirm-absent');
        var message = panel.find('.wmbr-recovery-message');
        if (confirmation.length && !confirmation.prop('checked')) {
            message.text('Confirme primeiro que esta tentativa não gerou uma nota na Webmania.');
            return;
        }
        button.prop('disabled', true);
        message.text('Consultando...');
        $.ajax({
            type: 'POST',
            url: panel.attr('data-url'),
            dataType: 'json',
            data: {
                method: 'recoverEmission',
                order_id: panel.attr('data-order'),
                recovery_key: panel.attr('data-key'),
                adminToken: panel.attr('data-csrf'),
                confirm_absent: confirmation.length && confirmation.prop('checked') ? '1' : '0'
            }
        }).done(function (result) {
            message.text(result.message || 'Não foi possível confirmar a recuperação. Atualize o pedido.');
            if (result.success) {
                window.location.reload();
            } else {
                button.prop('disabled', false);
            }
        }).fail(function () {
            message.text('Não foi possível confirmar a recuperação. Atualize o pedido antes de tentar novamente.');
            button.prop('disabled', false);
        });
    });
});
