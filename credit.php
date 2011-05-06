<?php
/*
 * Plugin Name: Navis Media Credit
 * Plugin URI: http://argoproject.org/blog/
 * Description: Adds support for credit fields on media stored in WordPress
 * Version: 0.1
 * Author: Project Argo
 * Author URI: http://argoproject.org/blog/
 * License: GPLv2
 * */
?>

<?php
/*
 * CREDIT FIELD
 */
define( 'MEDIA_CREDIT_POSTMETA_KEY', '_media_credit' );

function argo_get_media_credit( $obj=null ) {
    $post = get_post( $obj );
    return get_post_meta( $post->ID, MEDIA_CREDIT_POSTMETA_KEY, true );
}

function argo_add_media_credit( $fields, $post ) {
    $credit = argo_get_media_credit( $post );
    $html = "<input id='attachments[$post->ID][media_credit]' class='text media_credit' value='$credit' name='attachments[$post->ID][media_credit]' />";
    $fields[ 'media_credit' ] = array(
        'label' => 'Credit',
        'input' => 'html',
        'html'  => $html
    );
    return $fields;
}
add_filter( 'attachment_fields_to_edit', 'argo_add_media_credit', 10, 2 );

function argo_save_media_credit( $post, $attachment ) {
    if ( $_POST['attachments'] ) {
        $input = $_POST['attachments'][$post['ID']]['media_credit'];
    } 
    else {
        // XXX: not sure if this branch is ever followed
        $input = $_POST[ 'media_credit' ];
        if ( ! $input ) {
            $input = $_POST[ "attachments[" . $post['ID'] . "][media_credit]" ];
        }
    }
    if ( $input ) {
        update_post_meta( $post[ 'ID' ], MEDIA_CREDIT_POSTMETA_KEY, $input );
    }
    return $post;
}
add_filter( 'attachment_fields_to_save', 'argo_save_media_credit', 10, 2 );


/**
 * argo_add_caption_shortcode(): replaces the built-in caption shortcode
 * with one that supports a credit field.
 */
function argo_add_caption_shortcode( $html, $id, $caption, $title, $align, $url, $size, $alt = '' ) {
    $credit = argo_get_media_credit( $id );

    if ( empty( $caption ) && empty( $credit ) )
        return $html;

    $id = ( 0 < (int) $id ) ? 'attachment_' . $id : '';
    if ( ! preg_match( '/width="([0-9]+)/', $html, $matches ) )
        return $html;

    $width = $matches[1];

    // XXX: not sure what this does
    $html = preg_replace( '/(class=["\'][^\'"]*)align(none|left|right|center)\s?/', '$1', $html );
    if ( empty($align) )
        $align = 'none';

    $shcode = '[caption id="' . $id . '" align="align' . $align . 
        '" width="' . $width . '" caption="' . addslashes( $caption ) .
        '" credit="' . addslashes( $credit ) . '"]' .  $html . '[/caption]';
    return $shcode;
}
function argo_remove_caption_handler() {
    remove_filter( 'image_send_to_editor', 'image_add_caption', 20, 8 );
}
add_action( 'admin_init', 'argo_remove_caption_handler', 10 );
add_filter( 'image_send_to_editor', 'argo_add_caption_shortcode', 19, 8 );


/**
 * argo_image_shortcode(): renders caption shortcodes with our layout
 * and credit field.
 */
define( 'DEFAULT_ALIGNMENT', 'right' );
function argo_image_shortcode( $atts, $content, $code ) {
    extract( shortcode_atts( array(
        'align' => DEFAULT_ALIGNMENT,
        'width' => 620,
        'credit' => '',
        'caption' => '',
    ), $atts ) );

    if ( $width >= 400 ) {
        $align = 'centered';
    }
    else {
        if ( $align == 'alignnone' ) {
            $align = DEFAULT_ALIGNMENT;
        } elseif ( $align == 'center' ) {
            $align = 'centered';
        }
    }
    $out = sprintf( '<div class="module image %s" style="width: %spx;">%s',
        $align, $width, $content );
    if ( $credit ) {
        $out .= sprintf( '<p class="credit">%s</p>', $credit );
    }
    if ( $caption ) {
        $out .= sprintf( '<p class="caption">%s</p>', $caption );
    }
    $out .= "</div>";
 
    return $out;
}
add_shortcode( 'caption', 'argo_image_shortcode' );

/*
 * functions to override default wpeditimage TinyMCE plugin.
 */
function argo_monkeypatch_wpeditimage() {
    echo '<script type="text/javascript" src="' . plugins_url() . 
        '/navis-media-credit/js/media_credit_editor_plugin.js' .
        '"></script>';
}
add_action( 'admin_print_footer_scripts', 'argo_monkeypatch_wpeditimage', 1000 );


?>