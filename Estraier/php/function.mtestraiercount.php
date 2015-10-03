<?php
function smarty_function_mtestraiercount ( $args, &$ctx ) {
    $args[ 'count' ] = 1;
    $args[ 'limit' ] = 0;
    if ( isset( $args[ 'cache_ttl' ] ) ) {
        $cache_ttl = $args[ 'cache_ttl' ];
    }
    if ( $cache_ttl ) {
        ob_start();
        print_r( $args );
        $out = ob_get_clean();
        $hash = md5( $out );
        $cache_dir = $ctx->mt->config( 'PowerCMSFilesDir' ) . '/cache/';
        $cache = $cache_dir . $hash;
        if ( file_exists( $cache ) ) {
            $mtime = filemtime( $cache );
            $time = time();
            if ( ( $time - $cache_ttl ) < $mtime ) {
                return file_get_contents( $cache );
            }
        }
    }
    require_once( 'block.mtestraiersearch.php' );
    $repeat = TRUE;
    $hit = smarty_block_mtestraiersearch ( $args, NULL, $ctx, $repeat );
    if ( $cache_ttl ) {
        file_put_contents( $cache, $hit );
    }
    return $hit;
}
?>