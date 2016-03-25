package Estraier::Tags;
use strict;
use warnings;
use XML::Simple;
use Encode;
use Data::Dumper;
use File::Spec;

sub _hdlr_estraier_search {
    my ( $ctx, $args, $cond ) = @_;
    my $prefix = $args->{ prefix };
    my $cache_ttl = $args->{ cache_ttl };
    my $limit = $args->{ limit };
    if ( $limit && ( $limit !~ /^[0-9]+$/ ) ) {
        $limit = undef;
    }
    $limit = '-1' unless $limit;
    my $offset = $args->{ offset };
    if ( $offset && ( $offset !~ /^[0-9]+$/ ) ) {
        $offset = undef;
    }
    if ( $offset && $args->{ decrementoffset } ) {
        $offset--;
    }
    my $need_count = $args->{ count };
    if ( $need_count ) {
        $limit = 0;
    }
    my $ad_attr = $args->{ ad_attr } || $args->{ ad_attrs };
    my $add_condition = $args->{ add_condition } || $args->{ add_conditions };
    my $values = $args->{ 'values' } || $args->{ value };
    my $phrase = $args->{ 'phrase' } || $args->{ 'query' };
    my @_ad_attr;
    if ( $ad_attr && ( ( ref $ad_attr ) eq 'ARRAY' ) ) {
        @_ad_attr = @$ad_attr;
    } else {
        push ( @_ad_attr, $ad_attr );
    }
    my @_add_condition;
    if ( $add_condition && ( ( ref $add_condition ) eq 'ARRAY' ) ) {
        @_add_condition = @$add_condition;
    } else {
        push ( @_add_condition, $add_condition );
    }
    my @_values;
    if ( $values && ( ( ref $values ) eq 'ARRAY' ) ) {
        @_values = @$values;
    } else {
        push ( @_values, $values );
    }
    my $i = 0;
    my $condition = '';
    if ( $ad_attr ) {
        for my $attr( @_ad_attr ) {
            my $cond = $_add_condition[ $i ];
            my $value = $_values[ $i ];
            my $add_cond = " -attr " . _escapeshellarg( "${attr} ${cond} ${value}" );
            $condition .= $add_cond;
            $i++;
        }
    }
    my $cmd = MT->config( 'EstcmdPath' );
    $cmd .= " search -vx";
    if ( $offset ) {
        $cmd .= " -sk ${offset}";
    }
    $cmd .= " -max ${limit}";
    $cmd .= $condition;
    if (! $need_count ) {
        my $sort_by = $args->{ sort_by };
        my $sort_order = $args->{ sort_order };
        if ( $sort_by && $sort_order ) {
            if ( $sort_order eq 'ascend' ) {
                $sort_order = 'NUMA';
            } elsif ( $sort_order eq 'decend' ) {
                $sort_order = 'NUMD';
            }
            $cmd .= " -ord " . _escapeshellarg( "${sort_by} ${sort_order}" );
        }
    }
    $cmd .= ' ' . MT->config( 'EstcmdIndex' );
    if ( $phrase ) {
        my $raw_query = $args->{ raw_query };
        if (! $raw_query ) {
            my @_phrase;
            my $and_or = $args->{ and_or };
            $and_or = 'OR' unless $and_or;
            $and_or = uc( $and_or );
            $and_or = " ${and_or} ";
            if ( ( ref $phrase ) eq 'ARRAY' ) {
                @_phrase = @$phrase;
            } else {
                my $separator = $args->{ separator } || ' ';
                my $search = quotemeta( $separator );
                if ( $phrase =~ m!$search! ) {
                    @_phrase = split( $separator, $phrase );
                } else {
                    push ( @_phrase, $phrase );
                }
            }
            $phrase = _escapeshellarg( join( $and_or, @_phrase ) );
        } else {
            $phrase = _escapeshellarg( $phrase );
        }
        $cmd .= " ${phrase}";
    }
    my $hash;
    my $xml;
    if ( $cache_ttl ) {
        require Digest::MD5;
        $hash = Digest::MD5::md5_hex( MT::I18N::utf8_off( $cmd ) );
        $xml = _get_cache( $hash, $cache_ttl );
    }
    if (! $xml ) {
        my @list = qx/$cmd/;
        $xml = join( '', @list );
    }
    if (! Encode::is_utf8( $xml ) ) {
        Encode::_utf8_on( $xml );
    }
    if ( $cache_ttl ) {
        _set_cache( $hash, $xml );
    }
    my $xs = new XML::Simple();
    $xml =~ s!<document\sid="(.*?)"\suri="(.*?)">!<document>\n<attribute name="estraier_id" value="$1"/>\n<attribute name="estraier_uri" value="$2"/>!g;
    my $ref = $xs->XMLin( $xml );
    my $meta = $ref->{ meta };
    my $records = $ref->{ document };
    my $hit = $meta->{ hit }[ 0 ]->{ number };
    if ( $need_count ) {
        return $hit;
    }
    if (! $hit ) {
        return '';
    }
    if ( $limit > 0 ) {
        $ctx->stash( 'vars' )->{ $prefix . 'pagertotal' } = _ceil( $limit / $hit );
        if ( ( $offset + $limit ) < $hit ) {
            $ctx->stash( 'vars' )->{ $prefix . 'nextoffset' } = $offset + $limit;
        }
        if ( $offset ) {
            my $prevoffset = $offset - $limit;
            if ( $prevoffset < 0 ) {
                $prevoffset = 0;
            }
            $ctx->stash( 'vars' )->{ $prefix . 'prevoffset' } = $prevoffset;
        }
    }
    if ( ( ref $records ) ne 'ARRAY' ) {
        $records = [ $records ];
    }
    if ( $args->{ 'shuffle' } ) {
        eval "require List::Util;";
        unless ( $@ ) {
            @$records = List::Util::shuffle( @$records );
        }
    }
    my $time = $meta->{ 'time' }->{ 'time' };
    $ctx->stash( '_estraier_search_time', $time );
    $ctx->stash( 'vars' )->{ $prefix . 'totaltime' } = $time;
    $ctx->stash( '_estraier_search_hit', $hit );
    my $max = scalar( @$records );
    $ctx->stash( 'vars' )->{ $prefix . 'resultcount' } = $max;
    $ctx->stash( 'vars' )->{ $prefix . 'totalresult' } = $max;
    $ctx->stash( '_estraier_count', $max );
    my $tokens = $ctx->stash( 'tokens' );
    my $builder = $ctx->stash( 'builder' );
    my $res = '';
    $i = 0;
    my $odd = 1; my $even = 0;
    for my $doc ( @$records ) {
        local $ctx->{ __stash }->{ vars }->{ estraier_uri } = $doc->{ uri };
        local $ctx->{ __stash }->{ vars }->{ estraier_id } = $doc->{ id };
        local $ctx->{ __stash }->{ vars }->{ $prefix . 'snippet' } = $doc->{ snippet }->{ content };
        local $ctx->{ __stash }->{ vars }->{ __total__ } = $hit;
        my $attribute = $doc->{ attribute };
        for my $_key ( keys %$attribute ) {
            $ctx->{ __stash }->{ vars }->{ $prefix . $_key } = $attribute->{ $_key }->{ value };
        }
        my $next = $i + 1;
        local $ctx->{ __stash }->{ vars }->{ __first__ } = 1 if ( $i == 0 );
        local $ctx->{ __stash }->{ vars }->{ __first__ } = 0 if ( $i != 0 );
        local $ctx->{ __stash }->{ vars }->{ __counter__ } = $next;
        local $ctx->{ __stash }->{ vars }->{ __odd__ } = $odd;
        local $ctx->{ __stash }->{ vars }->{ __even__ } = $even;
        local $ctx->{ __stash }->{ vars }->{ __last__ } = 1 if ( $next == $max  );
        my $out = $builder->build( $ctx, $tokens, $cond );
        if ( !defined( $out ) ) { return $ctx->error( $builder->errstr ) };
        $res .= $out;
        if ( $odd == 1 ) { $odd = 0 } else { $odd = 1 };
        if ( $even == 1 ) { $even = 0 } else { $even = 1 };
        $i++;
    }
    return $res;
}

sub _hdlr_estraier_count {
    my ( $ctx, $args, $cond ) = @_;
    $args->{ count } = 1;
    return _hdlr_estraier_search( $ctx, $args, $cond );
}

sub _escapeshellarg {
    $_ = shift;
    s/([\&\;\`\'\\\"\|\*\?\~\<\>\^\(\)\[\]\{\}\$\n\r])/\\$1/g;
    return "'" . $_ . "'";
}

sub _ceil {
    my $var = shift;
    my $a = 0;
    $a = 1 if ( $var > 0 and $var != int( $var ) );
    return int( $var + $a );
}

sub _get_cache {
    my ( $hash, $cache_ttl ) = @_;
    require MT::FileMgr;
    my $fmgr = MT::FileMgr->new( 'Local' )
                    or die MT::FileMgr->errstr;
    my $cache_dir = File::Spec->catdir( MT->config( 'PowerCMSFilesDir' ) , 'cache' );
    my $cache_file = File::Spec->catfile( $cache_dir, $hash );
    if ( $fmgr->exists( $cache_file ) ) {
        my $mtime = $fmgr->file_mod_time( $cache_file );
        my $time = time();
        if ( ( $time - $cache_ttl ) < $mtime ) {
            my $buf = $fmgr->get_data( $cache_file );;
            return $buf;
        }
    }
    return '';
}

sub _set_cache {
    my ( $hash, $data ) = @_;
    require MT::FileMgr;
    my $fmgr = MT::FileMgr->new( 'Local' )
                    or die MT::FileMgr->errstr;
    my $cache_dir = File::Spec->catdir( MT->config( 'PowerCMSFilesDir' ) , 'cache' );
    my $cache_file = File::Spec->catfile( $cache_dir, $hash );
    return $fmgr->put_data( $data, $cache_file );
}

1;