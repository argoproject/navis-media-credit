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
define( 'MEDIA_CREDIT_POSTMETA_KEY', '_media_credit' );

class Navis_Media_Credit {
    function __construct( $post_id ) {
        $this->post_id = $post_id;
        $this->credit = get_post_meta( $post_id, MEDIA_CREDIT_POSTMETA_KEY, true );
        $this->org = get_post_meta( $post_id, '_navis_media_credit_org', true );
        $this->can_distribute = get_post_meta( $post_id, '_navis_media_can_distribute', true );
    }

    function to_string() {
        if ( $this->credit && $this->org ) {
            return sprintf( "%s / %s", esc_attr( $this->credit ), 
                            esc_attr( $this->org ) );
        } elseif ( $this->credit ) { // try returning just a name first
            return esc_attr( $this->credit );
        } else { // we don't have a name, so this should return an org or nothing
            return esc_attr( $this->org );
        }
    }

    function update( $field, $value ) {
        return update_post_meta( $this->post_id, '_' . $field, $value );
    }
}


function navis_get_media_credit( $id ) {
    // XXX: do we need to get the post->ID or can we just use this ID??
    $post = get_post( $id );
    $creditor = new Navis_Media_Credit( $post->ID );
    return $creditor;
}


function navis_get_media_credit_for_attachment( $text = '', $id ) {
    $creditor = navis_get_media_credit( $id );
    return $text . $creditor->to_string();
}
add_filter( 'navis_media_credit_for_attachment', 'navis_get_media_credit_for_attachment', 10, 2 );


function navis_add_media_credit( $fields, $post ) {
    $creditor = navis_get_media_credit( $post );
    $fields[ 'media_credit' ] = array(
        'label' => 'Credit',
        'input' => 'text',
        'value' => $creditor->credit,
    );
    
    $fields[ 'navis_media_credit_org' ] = array(
        'label' => 'Organization',
        'input' => 'text',
        'value' => $creditor->org
    );
    
    $can_distribute = $creditor->can_distribute;
    $checked = $can_distribute ? 'checked="checked"' : "";
    $distfield = 'attachments[' . $post->ID . '][navis_media_can_distribute]';
    $fields[ 'navis_media_can_distribute' ] = array(
        'label' => 'Can distribute?',
        'input' => 'html',
        'html' => '<input id="' . $distfield . '" name="' . $distfield . '" type="checkbox" value="1" ' . $checked . ' />'
    );
    return $fields;
}
add_filter( 'attachment_fields_to_edit', 'navis_add_media_credit', 10, 2 );


function navis_save_media_credit( $post, $attachment ) {
    $creditor = new Navis_Media_Credit( $post['ID'] );
    $fields = array( 'media_credit', 'navis_media_credit_org', 'navis_media_can_distribute' );
    foreach ( $fields as $field ) {
        if ( $_POST['attachments'] ) {
            $input = $_POST['attachments'][$post['ID']][$field];
        } 
        else {
            // XXX: not sure if this branch is ever followed
            $input = $_POST[ $field ];
            if ( ! $input ) {
                $input = $_POST[ "attachments[" . $post['ID'] . "][" . $field . "]" ];
            }
        }
        $creditor->update( $field, $input );
    }
    return $post;
}
add_filter( 'attachment_fields_to_save', 'navis_save_media_credit', 10, 2 );


/**
 * navis_add_caption_shortcode(): replaces the built-in caption shortcode
 * with one that supports a credit field.
 */
function navis_add_caption_shortcode( $html, $id, $caption, $title, $align, $url, $size, $alt = '' ) {
    $creditor = navis_get_media_credit( $id );

    if ( empty( $caption ) && !$creditor->to_string()) {
        return $html;
    };

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
        '" credit="' . addslashes( $creditor->to_string() ) . '"]' .  $html . '[/caption]';
    return $shcode;
}
function navis_remove_caption_handler() {
    remove_filter( 'image_send_to_editor', 'image_add_caption', 20, 8 );
}
add_action( 'admin_init', 'navis_remove_caption_handler', 10 );
add_filter( 'image_send_to_editor', 'navis_add_caption_shortcode', 19, 8 );


/**
 * navis_image_shortcode(): renders caption shortcodes with our layout
 * and credit field.
 */
function navis_image_shortcode( $text, $atts, $content ) {
    $atts = shortcode_atts( array(
        'id' => '',
        'align' => 'alignnone',
        'width' => '',
        'credit' => '',
        'caption' => '',
    ), $atts );
    $atts = apply_filters( 'navis_image_layout_defaults', $atts );
    extract( $atts );

    if ( $id ) $id = 'id="' . esc_attr($id) . '" ';

    // XXX: maybe remove module and image classes at some point
    $out = sprintf( '<div %s class="wp-caption module image %s" style="width: %spx;">%s', $id, $align, $width, do_shortcode( $content ) );
    if ( $credit ) {
        $out .= sprintf( '<p class="wp-media-credit">%s</p>', $credit );
    }
    if ( $caption ) {
        $out .= sprintf( '<p class="wp-caption-text">%s</p>', $caption );
    }
    $out .= "</div>";

    return $out;
}
add_filter( 'img_caption_shortcode', 'navis_image_shortcode', 10, 3 );

/*
 * functions to override default wpeditimage TinyMCE plugin.
 */
function navis_monkeypatch_wpeditimage() {
    echo '<script type="text/javascript" src="' . plugins_url() . 
        '/navis-media-credit/js/media_credit_editor_plugin.js' .
        '"></script>';
}
add_action( 'admin_print_footer_scripts', 'navis_monkeypatch_wpeditimage', 1000 );
?>
