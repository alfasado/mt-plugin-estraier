<?php
require_once( 'MTUtil.php' );
function smarty_block_mtestraiersearch ( $args, $content, $ctx, &$repeat ) {
    $localvars = array( array( '_estraier_search_time', '_estraier_count',
        '_estraier_counter', '_estraier_search_hit', '_estraier_search_meta',
        '_estraier_search_results' ), common_loop_vars() );
    $prefix = '';
    if ( isset( $args[ 'prefix' ] ) ) {
        $prefix = $args[ 'prefix' ];
    }
    if (! isset( $content ) ) {
        $ctx->localize( $localvars );
        if ( isset( $args[ 'cache_ttl' ] ) ) {
            $cache_ttl = $args[ 'cache_ttl' ];
        }
        if ( isset( $args[ 'limit' ] ) ) {
            $limit = $args[ 'limit' ];
            if (! ctype_digit( $limit ) ) {
                $limit = '-1';
            }
        } else {
            $limit = '-1';
        }
        if ( isset( $args[ 'offset' ] ) ) {
            $offset = $args[ 'offset' ];
            if (! ctype_digit( $offset ) ) {
                $offset = NULL;
            }
        }
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
        } else if ( isset( $args[ 'query' ] ) ) {
            $phrase = $args[ 'query' ];
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
                $add_cond = " -attr " . escapeshellarg( "${attr} ${cond} ${value}" );
                $condition .= $add_cond;
                $i++;
            }
        }
        $cmd = $ctx->mt->config( 'EstcmdPath' );
        $cmd .= " search -vx";
        if ( $offset ) {
            $cmd .= " -sk ${offset}";
        }
        $cmd .= " -max ${limit}";
        $cmd .= $condition;
        $cmd .= ' ' . $ctx->mt->config( 'EstcmdIndex' );
        if ( $phrase ) {
            $phrase = escapeshellarg( $phrase );
            $cmd .= " ${phrase}";
        }
        $hash = md5( $cmd );
        if ( $cache_ttl ) {
            $cache_dir = $ctx->mt->config( 'PowerCMSFilesDir' ) . '/cache/';
            $cache = $cache_dir . $hash;
            if ( file_exists( $cache ) ) {
                $mtime = filemtime( $cache );
                $time = time();
                if ( ( $time - $cache_ttl ) < $mtime ) {
                    $xml = file_get_contents( $cache );
                }
            }
        }
        if (! $xml ) {
            $xml = shell_exec( $cmd );
            if ( $cache_ttl ) {
                file_put_contents( $cache, $xml );
            }
        }
        $result = new SimpleXMLElement( $xml );
        $records = $result->document;
        $meta = $result->meta;
        $ctx->stash( '_estraier_search_results', $records );
        $ctx->stash( '_estraier_search_meta', $meta );
        $hit = $meta->hit;
        $hit = $hit->attributes()->number;
        if ( isset( $args[ 'count' ] ) ) {
            if ( $args[ 'count' ] ) {
                $repeat = FALSE;
                return $hit;
            }
        }
        $time = $meta->time;
        $time = $time->attributes()->time;
        $ctx->stash( '_estraier_search_time', $time );
        $ctx->__stash[ 'vars' ][ $prefix . 'totaltime' ] = $time;
        $ctx->stash( '_estraier_search_hit', $hit );
        $ctx->stash( '_estraier_counter', 0 );
        $counter = 0;
        $max = count( $records );
        $ctx->__stash[ 'vars' ][ $prefix . 'resultcount' ] = $max;
        $ctx->stash( '_estraier_count', $max );
        $ctx->__stash[ 'vars' ][ $prefix . 'totalresult' ] = $max;
        if ( $limit > 0 ) {
            $ctx->__stash[ 'vars' ][ $prefix . 'pagertotal' ] = ceil( $limit / $hit );
            if ( ( $offset + $limit ) < $hit ) {
                $ctx->__stash[ 'vars' ][ $prefix . 'nextoffset' ] = $offset + $limit;
            }
            if ( $offset ) {
                $prevoffset = $offset - $limit;
                if ( $prevoffset < 0 ) {
                    $prevoffset = 0;
                }
                $ctx->__stash[ 'vars' ][ $prefix . 'prevoffset' ] = $prevoffset;
            }
        }
    } else {
        $records = $ctx->stash( '_estraier_search_results' );
        $meta = $ctx->stash( '_estraier_search_meta' );
        $counter = $ctx->stash( '_estraier_counter' );
        $hit = $ctx->stash( '_estraier_search_hit' );
        $time = $ctx->stash( '_estraier_search_time' );
        $max = $ctx->stash( '_estraier_count' );
    }
    if ( $counter < $max ) {
        $record = $records[ $counter ];
        $attrs = $record->attribute;
        foreach( $attrs as $attr ) {
            $ctx->__stash[ 'vars' ][ $prefix .
                $attr->attributes()->name ]
                = $attr->attributes()->value;
        }
        $ctx->stash( '_estraier_record', $record );
        $ctx->__stash[ 'vars' ][ $prefix . 'uri' ] = $record->attributes()->uri;
        $ctx->__stash[ 'vars' ][ $prefix . 'id' ] = $record->attributes()->id;
        $ctx->__stash[ 'vars' ][ $prefix . 'snippet' ] = $record->snippet;
        $count = $counter + 1;
        $ctx->stash( '_estraier_counter', $count );
        $ctx->__stash[ 'vars' ][ '__total__' ] = $hit;
        $ctx->__stash[ 'vars' ][ '__counter__' ] = $count;
        $ctx->__stash[ 'vars' ][ '__odd__' ]  = ( $count % 2 ) == 1;
        $ctx->__stash[ 'vars' ][ '__even__' ] = ( $count % 2 ) == 0;
        $ctx->__stash[ 'vars' ][ '__first__' ] = $count == 1;
        $ctx->__stash[ 'vars' ][ '__last__' ] = ( $count == count( $records ) );
        $repeat = TRUE;
    } else {
        $ctx->restore( $localvars );
        $repeat = FALSE;
    }
    return $content;
}
?>