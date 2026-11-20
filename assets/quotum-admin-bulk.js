function quotumConfirmBulkAction(form) {
    var action = form.querySelector( 'select[name="bulk_action"]' ).value;
    var checkedBoxes = document.querySelectorAll( '.quotum-row-checkbox:checked' );
    var selected = checkedBoxes.length;

    var i18n = window.quotumAdminI18n;

    if ( ! action ) {
        alert( i18n.chooseAction );
        return false;
    }

    if ( selected === 0 ) {
        alert( i18n.selectQuotation );
        return false;
    }

    // The quotation checkboxes are outside the bulk form because each
    // row has its own Delete form. Add the selected IDs explicitly.
    form.querySelectorAll( '.quotum-bulk-generated-id' ).forEach( function ( input ) {
        input.remove();
    } );

    checkedBoxes.forEach( function ( box ) {
        var input = document.createElement( 'input' );
        input.type = 'hidden';
        input.name = 'quote_ids[]';
        input.value = box.value;
        input.className = 'quotum-bulk-generated-id';
        form.appendChild( input );
    } );

    // Select the translated singular or plural confirmation message.
    var suffix = selected === 1 ? 'Singular' : 'Plural';
    var question;

    if ( action === 'delete' ) {
        question = i18n[ 'deleteQuestion' + suffix ];
    } else if ( action === 'activate' ) {
        question = i18n[ 'activateQuestion' + suffix ];
    } else if ( action === 'deactivate' ) {
        question = i18n[ 'deactivateQuestion' + suffix ];
    } else {
        return false;
    }

    return confirm(
        question.replace( '%1$d', selected )
    );
}

document.addEventListener( 'DOMContentLoaded', function () {
    var form = document.getElementById( 'quotum-bulk-form' );

    if ( form ) {
        form.addEventListener( 'submit', function ( event ) {
            // Never submit a bulk operation until validation and confirmation succeed.
            if ( ! quotumConfirmBulkAction( form ) ) {
                event.preventDefault();
            }
        } );
    }

    var all = document.getElementById( 'quotum-select-all' );

    if ( ! all ) {
        return;
    }

    all.addEventListener( 'change', function () {
        document.querySelectorAll( '.quotum-row-checkbox' ).forEach( function ( box ) {
            box.checked = all.checked;
        } );
    } );

    document.querySelectorAll( '.quotum-row-checkbox' ).forEach( function ( box ) {
        box.addEventListener( 'change', function () {
            var boxes = Array.from( document.querySelectorAll( '.quotum-row-checkbox' ) );

            all.checked = boxes.length > 0 && boxes.every( function ( item ) {
                return item.checked;
            } );

            all.indeterminate = boxes.some( function ( item ) {
                return item.checked;
            } ) && ! all.checked;
        } );
    } );
} );
