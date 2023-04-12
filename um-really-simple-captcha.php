<?php
/**
 * Plugin Name:     Ultimate Member - Really Simple CAPTCHA
 * Description:     Extension to Ultimate Member for integration of the Really Simple CAPTCHA plugin.
 * Version:         1.3.0
 * Requires PHP:    7.4
 * Author:          Miss Veronica
 * License:         GPL v2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI:      https://github.com/MissVeronica
 * Text Domain:     ultimate-member
 * Domain Path:     /languages
 * UM version:      2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'UM' ) ) return;
if ( ! defined( 'REALLYSIMPLECAPTCHA_VERSION' ) ) return;

class UM_Really_Simple_CAPTCHA {

    public $captcha_instance = false;
    public $meta_key         = 'really_simple_captcha';

    function __construct() {

        $this->captcha_instance = new ReallySimpleCaptcha();

        $this->captcha_instance->tmp_dir = UM()->uploader()->get_upload_base_dir() . 'um-really-simple-captcha';
        if ( ! file_exists( $this->captcha_instance->tmp_dir )) {
            $this->captcha_instance->make_tmp_dir();
        }

        add_filter( 'um_predefined_fields_hook',       array( $this, 'um_predefined_fields_really_simple_captcha' ), 10, 1 );
        add_action( 'um_submit_form_errors_hook',      array( $this, 'check_really_simple_captcha' ), 100, 1 );
        add_filter( "um_edit_label_{$this->meta_key}", array( $this, 'um_edit_label_really_simple_captcha' ), 10, 1 );
        add_filter( 'um_settings_structure',           array( $this, 'um_settings_structure_really_simple_captcha' ), 10, 1 );
    }

    public function um_edit_label_really_simple_captcha( $label ) {
   
        $image_width = sanitize_text_field( UM()->options()->get( 'really_simple_captcha_image_width' ));
        if ( empty( $image_width ) || ! is_numeric( $image_width )) {
            $image_width = 72;
        } 

        $image_heigth = sanitize_text_field( UM()->options()->get( 'really_simple_captcha_image_height' ));
        if ( empty( $image_heigth ) || ! is_numeric( $image_heigth )) {
            $image_heigth = 24;
        } 

        $this->captcha_instance->img_size = array( (int)$image_width, (int)$image_heigth );

        $coord_x = sanitize_text_field( UM()->options()->get( 'really_simple_captcha_image_coord_x' ));
        if ( empty( $coord_x ) || ! is_numeric( $coord_x )) {
            $coord_x = 6;
        } 

        $coord_y = sanitize_text_field( UM()->options()->get( 'really_simple_captcha_image_coord_y' ));
        if ( empty( $coord_y ) || ! is_numeric( $coord_y )) {
            $coord_y = 18;
        }

        $this->captcha_instance->base = array( (int)$coord_x, (int)$coord_y );

        $characters = sanitize_text_field( UM()->options()->get( 'really_simple_captcha_characters' ));
        if ( ! empty( $characters ) && is_numeric( $characters )) {
            $this->captcha_instance->char_length = (int)$characters;
        }

        $font_size = sanitize_text_field( UM()->options()->get( 'really_simple_captcha_font_size' ));
        if ( ! empty( $font_size ) && is_numeric( $font_size )) {
            $this->captcha_instance->font_size = (int)$font_size;
        }

        $font_width = sanitize_text_field( UM()->options()->get( 'really_simple_captcha_font_width' ));
        if ( ! empty( $font_width ) && is_numeric( $font_width )) {
            $this->captcha_instance->font_char_width = (int)$font_width;
        }

        $backgr_color = sanitize_text_field( UM()->options()->get( 'really_simple_captcha_backgr_color' ));
        if ( ! empty( $backgr_color ) && strlen( $backgr_color ) == 7 ) {
            $this->captcha_instance->bg = sscanf( $backgr_color, "#%02x%02x%02x" );
        }

        $text_color = sanitize_text_field( UM()->options()->get( 'really_simple_captcha_text_color' ));
        if ( ! empty( $text_color ) && strlen( $text_color ) == 7 ) {
            $this->captcha_instance->fg = sscanf( $text_color, "#%02x%02x%02x" );
        }

        $captcha_word = sanitize_text_field( UM()->options()->get( 'really_simple_captcha_word' ));

        switch ( $captcha_word ) {

            case 'digit':   do { $word = $this->captcha_instance->generate_random_word(); }
                            while ( preg_match('~[0-9]+~', $word ) == false );
                            break;
            case 'vowels':  $this->captcha_instance->chars = 'BCDFGHJKLMNPQRSTVWXZ23456789';
                            $word = $this->captcha_instance->generate_random_word();
                            break;
            case 'digits':  $this->captcha_instance->chars = '0123456789';
                            $word = $this->captcha_instance->generate_random_word();
                            break;
            case 'default': $word = $this->captcha_instance->generate_random_word();
                            break;
            default:        $word = $this->captcha_instance->generate_random_word();
                            break;
        }

        $prefix = mt_rand();
        $image = $this->captcha_instance->generate_image( $prefix, $word );

        $url = home_url( str_replace( ABSPATH, '', $this->captcha_instance->tmp_dir . DIRECTORY_SEPARATOR . $image ));
        $title = __( 'CAPTCHA challenge image', 'ultimate-member' );

        $label = '<div class="um-really-simple-captcha">
                      <img class="um-really-simple-captcha-img" src="' . esc_url( $url ) . '" alt="CAPTCHA" title="' . esc_attr( $title ) . '">
                  </div>' . esc_attr( $label ) . 
                 '<input type="hidden" name="really_simple_captcha_prefix" id="really_simple_captcha_prefix" value="' . esc_attr( $prefix ) . '" />';

        return $label;
    }

    function check_really_simple_captcha( $args ) {

        if ( isset( $args[$this->meta_key] ) && isset( $args[$this->meta_key . '_prefix'] )) {

            $prefix = sanitize_text_field( $args[$this->meta_key . '_prefix'] );

            if ( empty( $args[$this->meta_key] )) {
                UM()->form()->add_error( $this->meta_key, __( 'Really Simple CAPTCHA empty', 'ultimate-member' ) );

            } else {

                $user_reply = sanitize_text_field( $args[$this->meta_key] );

                $correct = $this->captcha_instance->check( $prefix, $user_reply );

                if ( ! $correct ) {
                    UM()->form()->add_error( $this->meta_key, __( 'Invalid Really Simple CAPTCHA', 'ultimate-member' ) );
                }
            }

            $this->captcha_instance->remove( $prefix );
            $this->captcha_instance->cleanup();
        }
    }

    public function um_predefined_fields_really_simple_captcha( $predefined_fields ) {

        $predefined_fields[$this->meta_key] = array(

                        'title'       => __( 'Really Simple CAPTCHA', 'ultimate-member' ),
                        'metakey'     => $this->meta_key,
                        'type'        => 'text',
                        'label'       => __( 'Really Simple CAPTCHA', 'ultimate-member' ),
                        'required'    => 1,
                        'public'      => 1,
                        'editable'    => 0,
                        'placeholder' => __( 'Enter the characters', 'ultimate-member' ),
                        'help'        => __( 'You must enter all the CAPTCHA challenge characters', 'ultimate-member' ),
        );

        return $predefined_fields;
    }

    public function um_settings_structure_really_simple_captcha( $settings_structure ) {

        $settings_structure['appearance']['sections']['registration_form']['fields'][] =
 
                array(
                    'id'          => 'really_simple_captcha_characters',
                    'type'        => 'number',
                    'label'       => __( 'Really Simple CAPTCHA - Number of Captcha challenge characters', 'ultimate-member' ),
                    'tooltip'     => __( 'Enter the Number of characters in the Captcha challenge, default is four characters.', 'ultimate-member' ),
                    'size'        => 'small',
                );

        $settings_structure['appearance']['sections']['registration_form']['fields'][] =
 
                array(
                    'id'          => 'really_simple_captcha_word',
                    'type'        => 'select',
                    'label'       => __( 'Really Simple CAPTCHA - Select Challenge Word characters', 'ultimate-member' ),
                    'tooltip'     => __( 'Select the Captcha challenge characters to build the word.', 'ultimate-member' ),
                    'size'        => 'medium',
                    'options'     => array( 'default' => __( 'Default: ABCDEFGHJKLMNPQRSTUVWXYZ23456789', 'ultimate-member' ),
                                            'digit'   => __( 'At least one digit in the challenge word', 'ultimate-member' ),
                                            'exclude' => __( 'Exclude all vowels: BCDFGHJKLMNPQRSTVWXZ23456789', 'ultimate-member' ),
                                            'digits'  => __( 'Only digits: 0123456789', 'ultimate-member' )),
                );

        $settings_structure['appearance']['sections']['registration_form']['fields'][] =
 
                array(
                    'id'          => 'really_simple_captcha_image_width',
                    'type'        => 'number',
                    'label'       => __( 'Really Simple CAPTCHA - Width of the Captcha Image', 'ultimate-member' ),
                    'tooltip'     => __( 'Enter the Width of the Captcha Image in pixels, default is 72 pixels.', 'ultimate-member' ),
                    'size'        => 'small',
                );

        $settings_structure['appearance']['sections']['registration_form']['fields'][] =
 
                array(
                    'id'          => 'really_simple_captcha_image_height',
                    'type'        => 'number',
                    'label'       => __( 'Really Simple CAPTCHA - Height of the Captcha Image', 'ultimate-member' ),
                    'tooltip'     => __( 'Enter the Heigth of the Captcha Image in pixels, default is 24 pixels.', 'ultimate-member' ),
                    'size'        => 'small',
                );

        $settings_structure['appearance']['sections']['registration_form']['fields'][] =

                array(
                    'id'          => 'really_simple_captcha_backgr_color',
                    'type'        => 'text',
                    'label'       => __( 'Really Simple CAPTCHA - Captcha Image Background Color', 'ultimate-member' ),
                    'tooltip'     => __( 'Enter the Captcha Image Background Color in HEX notation like #ffffff and #00000 represents White and Black.', 'ultimate-member' ),
                    'size'        => 'small',
                );

        $settings_structure['appearance']['sections']['registration_form']['fields'][] =

                array(
                    'id'          => 'really_simple_captcha_text_color',
                    'type'        => 'text',
                    'label'       => __( 'Really Simple CAPTCHA - Captcha challenge text Color', 'ultimate-member' ),
                    'tooltip'     => __( 'Enter the Captcha challenge text Color in HEX notation like #ffffff and #00000 represents White and Black.', 'ultimate-member' ),
                    'size'        => 'small',
                );

        $settings_structure['appearance']['sections']['registration_form']['fields'][] =
 
                array(
                    'id'          => 'really_simple_captcha_image_coord_x',
                    'type'        => 'number',
                    'label'       => __( 'Really Simple CAPTCHA - X Coordinate for the first Captcha character', 'ultimate-member' ),
                    'tooltip'     => __( 'Enter the X Coordinate horizontal for the first Captcha character, default is 6 pixels.', 'ultimate-member' ),
                    'size'        => 'small',
                );

        $settings_structure['appearance']['sections']['registration_form']['fields'][] =

                array(
                    'id'          => 'really_simple_captcha_image_coord_y',
                    'type'        => 'number',
                    'label'       => __( 'Really Simple CAPTCHA - Y Coordinate for the first Captcha character', 'ultimate-member' ),
                    'tooltip'     => __( 'Enter the Y Coordinate vertical for the first Captcha character, default is 18 pixels.', 'ultimate-member' ),
                    'size'        => 'small',
                );

        $settings_structure['appearance']['sections']['registration_form']['fields'][] =

                array(
                    'id'          => 'really_simple_captcha_font_size',
                    'type'        => 'number',
                    'label'       => __( 'Really Simple CAPTCHA - Captcha character Font Size', 'ultimate-member' ),
                    'tooltip'     => __( 'Enter the Captcha character Font Size in pixels, default value is 14.', 'ultimate-member' ),
                    'size'        => 'small',
                );

        $settings_structure['appearance']['sections']['registration_form']['fields'][] =

                array(
                    'id'          => 'really_simple_captcha_font_width',
                    'type'        => 'number',
                    'label'       => __( 'Really Simple CAPTCHA - Captcha character Font Width', 'ultimate-member' ),
                    'tooltip'     => __( 'Enter the Captcha character Font Width in pixels, default value is 15.', 'ultimate-member' ),
                    'size'        => 'small',
                );

        return $settings_structure;
    }

}

new UM_Really_Simple_CAPTCHA();
