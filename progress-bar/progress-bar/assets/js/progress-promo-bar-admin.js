jQuery( function( $ ) {
    /**
     * Handle adding new rules dynamically.
     */
    $( '#progress-promo-bar-add-rule' ).on( 'click', function() {
        // Find the next available numeric index.  We use the length of current rows.
        var index = $( '#progress-promo-bar-rules tbody tr.progress-promo-bar-rule' ).length;
        // Clone the template row.
        var $template = $( '#progress-promo-bar-rules' ).find( 'tr.progress-promo-bar-rule' ).last().clone();
        // Replace the placeholder index with the actual index in name attributes.
        $template.find( 'input, select' ).each( function() {
            var name = $( this ).attr( 'name' );
            if ( name ) {
                name = name.replace( /__INDEX__/g, index );
                // Replace numeric index placeholders if cloning from an existing row.
                name = name.replace( /rules\[\d+\]/, 'rules[' + index + ']' );
                $( this ).attr( 'name', name );
            }
            // Reset values for new row.
            if ( $( this ).is( 'input[type="text"]' ) || $( this ).is( 'input[type="number"]' ) ) {
                $( this ).val( '' );
            }
            if ( $( this ).is( 'select' ) ) {
                $( this ).val( $( this ).find( 'option' ).first().val() );
            }
            if ( $( this ).is( 'input[type="checkbox"]' ) ) {
                $( this ).prop( 'checked', false );
            }
        } );
        $template.show();
        $( '#progress-promo-bar-rules tbody' ).append( $template );
    } );

    /**
     * Handle deleting a rule row.
     */
    $( document ).on( 'click', '.delete-rule', function() {
        // Remove the row from the table.
        $( this ).closest( 'tr' ).remove();
    } );
} );