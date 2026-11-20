function quotumConfirmBulkAction(form) {
            var action = form.querySelector( 'select[name="bulk_action"]' ).value;
            var checkedBoxes = document.querySelectorAll( '.quotum-row-checkbox:checked' );
            var selected = checkedBoxes.length;

            var i18n = {
                chooseAction: window.quotumAdminI18n.chooseAction,
                selectQuotation: window.quotumAdminI18n.selectQuotation,
                deleteQuestion: window.quotumAdminI18n.deleteQuestion,
                activateQuestion: window.quotumAdminI18n.activateQuestion,
                deactivateQuestion: window.quotumAdminI18n.deactivateQuestion
            };

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

            var plural = selected === 1 ? '' : 's';

            if ( action === 'delete' ) {
                return confirm( i18n.deleteQuestion.replace( '%1$d', selected ).replace( '%2$s', plural ) );
            }

            return confirm(
                ( action === 'activate' ? i18n.activateQuestion : i18n.deactivateQuestion )
                    .replace( '%1$d', selected )
                    .replace( '%2$s', plural )
            );
        }

        document.addEventListener( 'DOMContentLoaded', function () {
            var all = document.getElementById( 'quotum-select-all' );
            if ( ! all ) return;

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
