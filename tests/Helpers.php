<?php
/**
 * Make a mockery if it exists.
 *
 * @param String $of - What to make a mockery of.
 *
 * @return Object.
 */
function make_mockery( $of ) {
    require sprintf( 'Mockeries/%s.php', $of );
    return new ( 'Mockeries\\' . $of )();
}