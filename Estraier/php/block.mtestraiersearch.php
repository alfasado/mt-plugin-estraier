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
        if ( isset( $args[ 'count' ] ) ) {
            if ( $args[ 'count' ] ) {
                $need_count = 1;
                $limit = 0;
            }
        }
        if ( isset( $args[ 'ad_attr' ] ) ) {
            $ad_attr = $args[ 'ad_attr' ];
        } else if ( isset( $args[ 'ad_attrs' ] ) ) {
            $ad_attr = $args[ 'ad_attrs' ];
        }
        if ( isset( $args[ 'add_condition' ] ) ) {
            $add_condition = $args[ 'add_condition' ];
        } else if ( isset( $args[ 'add_conditions' ] ) ) {
            $ad_attr = $args[ 'add_conditions' ];
        }
        if ( isset( $args[ 'values' ] ) ) {
            $values = $args[ 'values' ];
        } else if ( isset( $args[ 'value' ] ) ) {
            $ad_attr = $args[ 'value' ];
        }
        if ( isset( $args[ 'phrase' ] ) ) {
            $phrase = $args[ 'phrase' ];
        } else if ( isset( $args[ 'query' ] ) ) {
            $phrase = $args[ 'query' ];
        }
        if ( $phrase ) {
            if (! is_array( $phrase ) ) {
                if ( strpos( $phrase, ':' ) !== FALSE ) {
                    $phrase = str_getcsv( $phrase, ':' );
                    $_phrase = array();
                    foreach ( $phrase as $q ) {
                        if ( strpos( $q, 'Array.' ) === 0 ) {
                            $q = str_replace( 'Array.', '', $q );
                            $q = $ctx->__stash[ 'vars' ][ $q ];
                        }
                        array_push( $_phrase, $q );
                    }
                    $phrase = $_phrase;
                }
            }
        }
        if ( $ad_attr ) {
            if (! is_array( $ad_attr ) ) {
                if ( strpos( $ad_attr, ':' ) !== FALSE ) {
                    $ad_attr = str_getcsv( $ad_attr, ':' );
                } else {
                    $ad_attr = array( $ad_attr );
                }
            }
        }
        if ( $add_condition ) {
            if (! is_array( $add_condition ) ) {
                if ( strpos( $add_condition, ':' ) !== FALSE ) {
                    $add_condition = str_getcsv( $add_condition, ':' );
                } else {
                    $add_condition = array( $add_condition );
                }
            }
        }
        if ( $values ) {
            if (! is_array( $values ) ) {
                if ( strpos( $values, ':' ) !== FALSE ) {
                    $values = str_getcsv( $values, ':' );
                } else {
                    $values = array( $values );
                }
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
        if (! $need_count ) {
            if ( isset( $args[ 'sort_by' ] ) ) {
                $sort_by = $args[ 'sort_by' ];
            }
            if ( isset( $args[ 'sort_order' ] ) ) {
                $sort_order = $args[ 'sort_order' ];
            }
            if ( $sort_by && $sort_order ) {
                if ( $sort_order == 'ascend' ) {
                    $sort_order = 'NUMA';
                } else if ( $sort_order == 'decend' ) {
                    $sort_order = 'NUMD';
                }
                $cmd .= " -ord " . escapeshellarg( "${sort_by} ${sort_order}" );
            }
        }
        $cmd .= ' ' . $ctx->mt->config( 'EstcmdIndex' );
        if ( $phrase ) {
            if ( isset( $args[ 'and_or' ] ) ) $and_or = $args[ 'and_or' ];
            if (! $and_or ) $and_or = 'OR';
            $and_or = strtoupper( $and_or );
            $and_or = " ${and_or} ";
            if ( is_array( $phrase ) ) {
                $phrase = escapeshellarg( implode( $and_or, $phrase ) );
            } else {
                $phrase = escapeshellarg( $phrase );
                if ( isset( $args[ 'raw_query' ] ) ) $raw_query = $args[ 'raw_query' ];
                if (! $raw_query ) {
                    if ( isset( $args[ 'separator' ] ) ) $separator = $args[ 'separator' ];
                    if (! $separator ) $separator = ' ';
                    if ( strpos( $phrase, $separator ) !== FALSE ) {
                        $phrase = explode( $separator, $phrase );
                        $phrase = join( $and_or, $phrase );
                    }
                }
            }
            $cmd .= " ${phrase}";
        }
        $ctx->__stash[ 'vars' ][ 'estcmd_cmd' ] = $cmd;
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
        $ctx->stash( '_estraier_search_meta', $meta );
        $hit = $meta->hit;
        $hit = $hit->attributes()->number;
        $hit = ( string ) $hit;
        if ( $need_count ) {
            $repeat = FALSE;
            $ctx->restore( $localvars );
            return $hit;
        }
        $time = $meta->time;
        $time = $time->attributes()->time;
        $time = ( string ) $time;
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
        if ( isset( $args[ 'shuffle' ] ) ) {
            if ( $args[ 'shuffle' ] ) {
                $_count = count( $records );
                $_records = array();
                for ( $i = 0; $i < $_count; $i++ ) {
                    $_records[] = $records[ $i ];
                }
                shuffle( $_records );
                $records = $_records;
            }
        }
        $ctx->stash( '_estraier_search_results', $records );
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
            $val = $attr->attributes()->value[ 0 ];
            $val = ( string ) $val;
            $name = $attr->attributes()->name;
            $name = ( string ) $name;
            $ctx->__stash[ 'vars' ][ $prefix . $name ] = $val;
        }
        $ctx->stash( '_estraier_record', $record );
        $_uri = $record->attributes()->uri;
        $_uri = ( string )$_uri; 
        $_id = $record->attributes()->id;
        $_id = ( string )$_id; 
        $ctx->__stash[ 'vars' ][ 'estraier_uri' ] = $_uri;
        $ctx->__stash[ 'vars' ][ 'estraier_id' ] = $_id;
        $ctx->__stash[ 'vars' ][ $prefix . 'snippet' ] = (string)$record->snippet;
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