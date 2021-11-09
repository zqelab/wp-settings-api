<?php
namespace Zqe;

class Wp_Settings_Api {

    protected $name; 

    protected $pages = array();
    protected $defaults = array();

    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

    }

    public function admin_enqueue_scripts() {
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'zqe-color-picker-handle', plugins_url('zqe-color-picker.js', __FILE__ ), array( 'jquery', 'wp-color-picker' ), false, true );
    }

    public function set_name( $name ) {
        $this->name = $name;
        return $this;
    }

    public function set_pages( $pages ) {
        $this->pages = $pages;
        return $this;
    }

    public function set_defaults(  ) {
        $defaults = array();
        foreach ( $this->pages as $key => $page ) {
            foreach ( $page['sections'] as $key => $section ) {
                foreach ( $section['fields'] as $key => $field ) { 
                   $defaults[$page['id']][$field['id']] = array(
                        'id' => $field['id'],
                        'type' => $field['type'],
                        'default' => $field['default'],
                    );
                }
            }
        }
        $this->defaults = $defaults;
        return $this;
    }


    public function admin_init() {

        foreach ($this->pages as $key => $page) {

            register_setting( $page['group'], $this->name, [ $this, 'sanitize_callback' ] );

            foreach ($page['sections'] as $key => $section) {
                
                add_settings_section( $section['id'], $section['title'], function () use ( $section ) {
                    if ( isset( $section['desc'] ) && ! empty( $section['desc'] ) ) {
                        echo '<div class="inside">' . $section['desc'] . '</div>';
                    }
                }, $page['id'] );

                foreach ($section['fields'] as $key => $field) {
                    add_settings_field( $field['id'], $field['title'], [ $this, 'callback' ], $page['id'], $section['id'], wp_parse_args( $field,
                        [ 'page' => $page['id'] ]
                    ) );
                }
            }
        }
    }

    public function sanitize_callback( $options ) {
        $options = $this->sanitize_options( $options );
        if ( is_array( get_option( $this->name ) ) && is_array( $options ) ) {
            $options = array_merge( get_option( $this->name ), $options );
        }
        return $options; 
    }

    public function sanitize_options( $options ) {
        $page = current(array_keys($options));
        $defaults = $this->defaults[$page];
        foreach ($this->defaults[$page] as $key => $value) {
            if( ! isset( $options[$page][$value['id']] ) ) {
                $options[$page][$value['id']] = '';
            }
        }
        return $options;
    }

    public function get_option( $key ) {
        
        $options = get_option( $this->name ) ? get_option( $this->name ) : array();
        
        if( is_array( $options ) && ! empty( $options ) ) {
            foreach ($options as $page => $option) {
               if( isset( $option[$key] ) ) {
                    return $option[$key];
               }
            }
        }

        if( is_array( $this->defaults ) && ! empty( $this->defaults ) ) {
            foreach ($this->defaults as $page => $option) {
               if( isset( $option[$key] ) ) {
                    return $option[$key]['default'];
               }
            }
        }
    }

    public function show_forms() {
        foreach ( $this->pages as $key => $page ) { 
            $current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : current( $this->pages )['id'];
            if($current_tab === $page['id']) {
            ?>
            <form method="post" action="options.php">
                <?php settings_errors(); ?>
                <div id="zqe-options-<?php echo $page['group']; ?>" class="zqe-options-group">
                <?php settings_fields( $page['group'] );  ?>
                <?php $this->do_settings_sections( $page['id'] );  ?>
                </div>
                <?php submit_button();  ?>
            </form>
           
        <?php }
        } ?>
        <style type="text/css">.zqe-options-group h2{font-size:1.5em;margin-bottom:10px}.zqe-options-group .form-table{margin-top:15px;background:#fff;border-radius:2px;box-shadow:0 0 0 1px rgba(0,0,0,.07),0 1px 1px rgba(0,0,0,.04)}.zqe-options-group .form-table tr{border-bottom:1px solid #eee;display:block}.zqe-options-group .form-table tr:last-child{border:0}.zqe-options-group .form-table th{padding:20px 10px 20px 20px}.zqe-options-group .zqe-new-setting-field,.zqe-options-group .zqe-pro-setting-field{font-size:9px;font-weight:400;text-transform:uppercase;padding:3px 5px;line-height:1;border-radius:10px;display:inline-block;margin:0 3px}.zqe-options-group .zqe-pro-setting-field{color:#fff;background:#ff5722}.zqe-options-group .zqe-new-setting-field{border:1px solid #ff5722;color:#ff5722;background:#fff}</style>
        <?php
    }

    private function do_settings_sections( $page ) {
        
        global $wp_settings_sections, $wp_settings_fields;
        
        if ( ! isset( $wp_settings_sections[ $page ] ) ) {
            return;
        }

        foreach ( (array) $wp_settings_sections[ $page ] as $section ) {
            if ( $section['title'] ) {
                echo sprintf('<h2>%s</h2>', $section['title']);
            }
            if ( $section['callback'] ) {
                call_user_func( $section['callback'], $section );
            }
            if ( ! isset( $wp_settings_fields ) || ! isset( $wp_settings_fields[ $page ] ) || ! isset( $wp_settings_fields[ $page ][ $section['id'] ] ) ) {
                continue;
            }
            echo '<table class="form-table">';
            $this->do_settings_fields( $page, $section['id'] );
            echo '</table>';
        }
    }

    private function do_settings_fields( $page, $section ) {
        
        global $wp_settings_fields;
        
        if ( ! isset( $wp_settings_fields[ $page ][ $section ] ) ) {
            return;
        }

        foreach ( (array) $wp_settings_fields[ $page ][ $section ] as $field ) {

            echo '<tr>';
            echo '<th scope="row">';
            if ( ! ( $field['args']['type'] == 'checkbox' || $field['args']['type'] == 'radio' ) ) {
                echo '<label for="zqe-' . esc_attr( $field['id'] ) . '">' . $field['title'] . '</label>';
            } else {
                echo $field['title'];
            }
            echo ( isset( $field['args']['is_new'] ) && $field['args']['is_new'] ) ? ('<span class="zqe-new-setting-field">' . esc_html__( 'NEW', 'woo-variation-swatches' ) . '</span>') : '';
            echo ( isset( $field['args']['is_pro'] ) && $field['args']['is_pro'] ) ? ('<span class="zqe-pro-setting-field">' . esc_html__( 'PRO', 'woo-variation-swatches' ) . '</span>') : '';
            echo '</th>';
            echo '<td>';
            call_user_func( $field['callback'], $field['args'] );
            echo '</td>';
            echo '</tr>';
        }
    }

    public function callback( $args ) {

        $args['value'] = $this->get_option( $args['id'] );

        $args['size']  = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'small';
        $args['suffix']  = isset( $args['suffix'] ) && ! is_null( $args['suffix'] ) ? ' <span>' . $args['suffix'] . '</span>' : '';
        
        switch ( $args['type'] ) {
            case 'checkbox':
                $this->checkbox( $args );
                break;
            case 'radio':
                $this->radio( $args );
                break;
            case 'select':
                $this->select( $args );
                break;
            case 'number':
                $this->number( $args );
                break;
            case 'color':
                $this->color( $args );
                break;
            case 'wysiwyg':
                $this->wysiwyg( $args );
                break;
            default:
                $this->text( $args );
                break;
        }
    }

    public function checkbox( $args ) {
        echo '<fieldset>';
        
        if( isset($args['options']) && is_array($args['options']) ) {
            foreach ( $args['options'] as $key => $label ) {
                echo sprintf( '<label for="zqe-%2$s-%3$s-%4$s">', $this->name, $args['page'], $args['id'], $key );
                echo sprintf( '<input type="checkbox" id="zqe-%2$s-%3$s-%4$s" name="%1$s[%2$s][%3$s][%4$s]" value="%4$s" %5$s />', $this->name, $args['page'], $args['id'], $key, checked( (isset( $args['value'][$key] ) ? $args['value'][$key] : '0'), $key, false ) );
                echo sprintf( '%1$s</label><br>',  $label );
            }
            echo $this->field_description( $args );
        } else {
            echo sprintf( '<label for="zqe-%2$s-%3$s">', $this->name, $args['page'], $args['id'] );
            echo sprintf( '<input type="checkbox" id="zqe-%2$s-%3$s" name="%1$s[%2$s][%3$s]" value="on" %4$s />', $this->name, $args['page'], $args['id'], checked( $args['value'], 'on', false ) );
            echo sprintf( '%1$s</label>', $args['desc'] );
        }

        echo '</fieldset>';
    }


    public function radio( $args ) {


        echo '<fieldset>';
        foreach ( $args['options'] as $key => $label ) {
            echo sprintf( '<label for="zqe-%2$s-%3$s-%4$s">',  $this->name, $args['page'], $args['id'], $key );
            echo sprintf( '<input type="radio" id="zqe-%2$s-%3$s-%4$s" name="%1$s[%2$s][%3$s]" value="%4$s" %5$s />', $this->name, $args['page'], $args['id'], $key, checked( $args['value'], $key, false ) );
            echo sprintf( '%1$s</label>', $label );
            echo sprintf( '<br>');
        }
        echo $this->field_description( $args );
        echo '</fieldset>';
    }

    public function select( $args ) {
        echo sprintf( '<select id="zqe-%2$s-%3$s" name="%1$s[%2$s][%3$s]">', $this->name, $args['page'], $args['id']);
        foreach ( $args['options'] as $key => $option ) {
            echo sprintf( '<option value="%s"%s>%s</option>', $key, selected( $args['value'], $key, false ), $option );
        }
        echo sprintf( '</select>');
        echo $this->field_description( $args );
    }


    public function color( $args ) {
        echo sprintf( '<input type="text" class="zqe-color-picker wp-color-picker" id="zqe-%2$s-%3$s" name="%1$s[%2$s][%3$s]" value="%4$s" />', $this->name, $args['page'], $args['id'], $args['value'] );
        echo $this->field_description( $args );
    }

    public function wysiwyg( $args ) {
        $editor_settings = array(
            'teeny'         => true,
            'textarea_name' => $this->name . '[' . $args['id'] . ']',
            'textarea_rows' => 10
        );
        if ( isset( $args['options'] ) && is_array( $args['options'] ) ) {
            $editor_settings = array_merge( $editor_settings, $args['options'] );
        }
        wp_editor( $args['value'], $this->name . '-' . $args['id'], $editor_settings );

        echo $this->field_description( $args );
    }

    public function text( $args ) {
        echo sprintf( '<input type="text" class="%5$s-text" id="zqe-%2$s-%3$s" name="%1$s[%2$s][%3$s]" value="%4$s"/>', $this->name, $args['page'], $args['id'], $args['value'], $args['size']);
        echo $this->field_description( $args );
    }

    public function number( $args ) {

        

        echo sprintf( '<input type="number" class="%5$s-text" id="zqe-%2$s-%3$s" name="%1$s[%2$s][%3$s]" value="%4$s"/> %6$s', $this->name, $args['page'], $args['id'], $args['value'], $args['size'], $args['suffix']);
        echo $this->field_description( $args );
    }

    public function field_description( $args, $html = '' ) {
        if ( ! empty( $args['desc'] ) ) {
            
            return sprintf( '<p class="description"><i><small>%s</small></i></p>', $args['desc'] );
        } 
    }
}