<?php
function smarty_function_mtestraiercount ( $args, &$ctx ) {
    if ( isset( $args[ 'cache_ttl' ] ) ) {
        $cache_ttl = $args[ 'cache_ttl' ];
    }
    $app = $ctx->stash( 'bootstrapper' );
    if ( isset( $args[ 'ad_attr' ] ) ) {
        $ad_attr = $args[ 'ad_attr' ];
    }
    if ( isset( $args[ 'add_condition' ] ) ) {
        $add_condition = $args[ 'add_condition' ];
    }
    if ( isset( $args[ 'values' ] ) ) {
        $values = $args[ 'values' ];
    }
    if ( isset( $args[ 'phrase' ] ) ) {
        $phrase = $args[ 'phrase' ];
    }
    if ( $ad_attr ) {
        if (! is_array( $ad_attr ) ) {
            $ad_attr = str_getcsv( $ad_attr, ':' );
        }
    }
    if ( $add_condition ) {
        if (! is_array( $add_condition ) ) {
            $add_condition = str_getcsv( $add_condition, ':' );
        }
    }
    if ( $values ) {
        if (! is_array( $values ) ) {
            $values = str_getcsv( $values, ':' );
        }
    }
    $i = 0;
    $condition = '';
    if ( $ad_attr ) {
        foreach( $ad_attr as $attr ) {
            $cond = $add_condition[ $i ];
            $value = $values[ $i ];
            if ( strpos( $attr, 'Array.' ) === 0 ) {
                $attr = str_replace( 'Array.', '', $attr );
                $attr = $ctx->__stash[ 'vars' ][ $attr ];
            }
            if ( strpos( $cond, 'Array.' ) === 0 ) {
                $cond = str_replace( 'Array.', '', $cond );
                $cond = $ctx->__stash[ 'vars' ][ $cond ];
            }
            if ( strpos( $value, 'Array.' ) === 0 ) {
                $value = str_replace( 'Array.', '', $value );
                $value = $ctx->__stash[ 'vars' ][ $value ];
            }
            $attr = escapeshellcmd( $attr );
            $cond = escapeshellcmd( $cond );
            $value = escapeshellcmd( $value );
            $co = " -attr \"${attr} ${cond} ${value}\"";
            $condition .= $co;
            $i++;
        }
    }
    $cmd = $app->config( 'EstcmdPath' );
    $cmd .= ' search -vh ' . $condition;
    $cmd .= ' -max 0 ' . $app->config( 'EstcmdIndex' );
    if ( $phrase ) {
        $phrase = escapeshellcmd( $phrase );
        $cmd .= " \"${phrase}\"";
    }
    # echo $cmd;
    $hash = md5( $cmd );
    if ( $cache_ttl ) {
        $cache_dir = $app->config( 'PowerCMSFilesDir' ) . '/cache/';
        $cache = $cache_dir . $hash;
        if ( file_exists( $cache ) ) {
            $mtime = filemtime( $cache );
            $time = time();
            if ( ( $time - $cache_ttl ) < $mtime ) {
                return file_get_contents( $cache );
            }
        }
    }
    $result = shell_exec( $cmd );
    $result = explode( "\n", $result );
    $r = array();
    foreach ( $result as $line ) {
        $values = explode( "\t", $line );
        $r[ $values[ 0 ] ] = $values[ 1 ];
    }
    $count = $r[ 'HIT' ];
    if ( $cache_ttl ) {
        file_put_contents( $cache, $count );
    }
    return $count;
}
?>