$(document).ready(function () {
    $('#btn_delete').click(function (e) {
        $('#delete-warning').show();
        $('#btn_delete').attr('disabled', true);
        $('#delete-confirmation').focus();
    });
    $('#btn_delete_cancel').click(function (e) {
        $('#delete-warning').hide();
        $('#btn_delete').attr('disabled', false).focus();
    });

    $('#btn_add').click(function (e) {
        $('#add-participant').show();
        $('#btn_add').attr('disabled', true);
        $('#add-participant-name').focus();
    });

    $('#btn_add_cancel').click(function (e) {
        $('#add-participant').hide();
        $('#btn_add').attr('disabled', false).focus();
    });

    $('#btn_update').click(function (e) {
        $('#update-party-details').show();
        $('#btn_update').attr('disabled', true);
    });

    $('#btn_update_cancel').click(function (e) {
        $('#update-party-details').hide();
        $('#btn_update').attr('disabled', false).focus();
    });

    $('.link_remove_participant').click(function (e) {
        $('#delete-participant').show();
        $('.link_remove_participant').attr('disabled', true);
        $('#delete-participant-confirmation').focus();
        var listUrl = $(this).data('listurl');
        var participantId = $(this).data('participant');
        attachAction(listUrl, participantId);
    });

    $('.btn_remove_participant_cancel').click(function (e) {
        $('#delete-participant').hide();
        $('.link_remove_participant').attr('disabled', false);
    });

    if (Modernizr.inputtypes.date == true) {
        $("#intracto_secretsantabundle_updatepartydetailstype_eventdate").click(function (e) {
            $(this).datepicker({dateFormat: 'dd-mm-yy'});
        });
    }

    $('.js-selector-participant').select2({ width: '100%' });

    $('.participant-edit-icon').on('click', function() {
        editParticipant($(this).data('listurl'), $(this).data('participant-id'));
    });

    $(document).on('click', '.save-edit', function(){
        submitEditForm($(this).data('listurl'), $(this).data('participant-id'));
    });
});

function showExcludeErrors() {
    $('#collapsedMessage').collapse('show');
    $('html, body').animate({
        scrollTop: $("#collapsedMessage").offset().top
    }, 2000);
}

function editParticipant(listUrl, participantId) {
    var email = $('#email_' + participantId).html();
    var name = $('#name_' + participantId).html();
    var url = $('table#mysanta').data('editurl');
    url = url.replace("listUrl", listUrl);
    url = url.replace("participantId", participantId);
    if ($('#email_' + participantId).has('form').length == 0) {
        makeEditForm(participantId, listUrl, name, email);
    }
}

function submitEditForm(listUrl,participantId) {
    var url = $('table#mysanta').data('editurl');
    url = url.replace("listUrl", listUrl);
    url = url.replace("participantId", participantId);
    var name = $('#input_name_' + participantId).val();
    var email = $('#input_email_' + participantId).val();
    $('#input_name_' + participantId).prop('disabled', true);
    $('#input_email_' + participantId).prop('disabled', true);
    $('#submit_btn_' + participantId).prop('disabled', true);
    $('#submit_btn_' + participantId).html('<i class="fa fa-spinner fa-spin"></i>');
    $("#alertspan").html('');

    $.ajax({
        type: 'POST',
        url: url,
        data: {
            name: name,
            email: email
        },
        success: function(data){
            if (data.success) {
                $("#alertspan").html('<div class="alert alert-success" role="alert">' + data.message + '</div>');
                $('#name_' + participantId).html(name);
                $('#email_' + participantId).html(email);
            } else {
                $("#alertspan").html('<div class="alert alert-danger" role="alert">'+ data.message +'</div>');
                makeEditForm(participantId, listUrl, name, email);
            }
        }
    });
}

function makeEditForm(participantId, listUrl, name, email){
    var saveBtnText = $('table#mysanta').data('save-btn-text');
    $('#name_' + participantId).html(
        '<input type="text" id="input_name_' + participantId + '" class="form-control input_edit_name" name="name" value="' + name + '" data-hj-masked>'
    );
    $('#email_' + participantId).html(
        '<input type="text" id="input_email_' + participantId + '" class="form-control input_edit_email" name="email" value="' + email + '" data-hj-masked>&nbsp;' +
        '<button class="btn btn-small btn-primary save-edit" id="submit_btn_' + participantId + '" data-listurl="'+listUrl +'" data-participant-id="' + participantId + '"><i class="fa fa-check"></i> '+saveBtnText+'</button>'
    );
}

function attachAction(listUrl, participantId) {
    var url = $('form#delete-participant-form').data('action');
    url = url.replace('listUrl', listUrl);
    url = url.replace('participantId', participantId);
    $('#delete-participant-form').attr('action', url);
}
