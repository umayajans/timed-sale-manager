<?php
/**
 * Plugin Name: Timed Sale Manager
 * Plugin URI: https://wordpress.org/plugins/timed-sale-manager/
 * Description: Adds timed sale feature for WooCommerce products
 * Version: 1.0.0
 * Requires at least: 5.2
 * Requires PHP: 7.2
 * Author: https://profiles.wordpress.org/umayajans/
 * Author URI: https://www.umayajans.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: timed-sale-manager
 * Domain Path: /languages
 *
 * @package TimedSaleManager
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main plugin class
 *
 * @since 1.0.0
 */
class Timed_Sale_Manager {

    /**
     * Instance of this class
     *
     * @since 1.0.0
     * @var object
     */
    private static $instance = null;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

        if ( is_admin() ) {
            add_action( 'admin_init', array( $this, 'check_woocommerce' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        }

        $this->init();
    }

    /**
     * Get instance of this class
     *
     * @since 1.0.0
     * @return Timed_Sale_Manager
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load plugin text domain
     *
     * @since 1.2.0
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'timed-sale-manager',
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages/'
        );
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @since 1.0.0
     */
    public function enqueue_admin_scripts() {
        wp_enqueue_style(
            'timed-sale-admin',
            plugins_url( 'css/admin.css', __FILE__ ),
            array(),
            '1.2.0'
        );
    }

    /**
     * Check if WooCommerce is active
     *
     * @since 1.0.0
     */
    public function check_woocommerce() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            deactivate_plugins( plugin_basename( __FILE__ ) );
            if ( isset( $_GET['activate'] ) ) {
                unset( $_GET['activate'] );
            }
        }
    }

    /**
     * Display WooCommerce missing notice
     *
     * @since 1.0.0
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p>
                <?php
                esc_html_e(
                    'Timed Sale Manager requires WooCommerce to be installed and activated.',
                    'timed-sale-manager'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Initialize plugin features
     *
     * @since 1.0.0
     */
    public function init() {
        add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_tab' ) );
        add_action( 'woocommerce_product_data_panels', array( $this, 'add_product_tab_content' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_tab_content' ) );
        
        // Price filters
        add_filter( 'woocommerce_product_get_price', array( $this, 'get_timed_sale_price' ), 10, 2 );
        add_filter( 'woocommerce_product_get_regular_price', array( $this, 'get_timed_sale_price' ), 10, 2 );
        add_filter( 'woocommerce_product_is_on_sale', array( $this, 'check_if_on_timed_sale' ), 10, 2 );
    }

    /**
     * Add product data tab
     *
     * @since 1.0.0
     * @param array $tabs Product data tabs.
     * @return array
     */
    public function add_product_tab( $tabs ) {
        $tabs['timed_sale'] = array(
            'label'    => esc_html__( 'Timed Sale', 'timed-sale-manager' ),
            'target'   => 'timed_sale_data',
            'priority' => 100,
            'class'    => array(),
        );
        return $tabs;
    }

    /**
     * Add product tab content
     *
     * @since 1.0.0
     */
    public function add_product_tab_content() {
        global $post;

        wp_nonce_field( 'timed_sale_save_data', 'timed_sale_nonce' );

        ?>
        <div id="timed_sale_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <?php
                woocommerce_wp_text_input(
                    array(
                        'id'                => '_timed_sale_price',
                        'label'             => esc_html__( 'Sale Price', 'timed-sale-manager' ),
                        'desc_tip'          => true,
                        'description'       => esc_html__( 'Enter the sale price for the timed discount', 'timed-sale-manager' ),
                        'type'              => 'number',
                        'custom_attributes' => array(
                            'step' => 'any',
                            'min'  => '0',
                        ),
                        'value'             => get_post_meta( $post->ID, '_timed_sale_price', true ),
                    )
                );

                woocommerce_wp_text_input(
                    array(
                        'id'          => '_timed_sale_start',
                        'label'       => esc_html__( 'Start Date', 'timed-sale-manager' ),
                        'desc_tip'    => true,
                        'description' => esc_html__( 'Select the start date and time for the sale', 'timed-sale-manager' ),
                        'type'        => 'datetime-local',
                        'value'       => get_post_meta( $post->ID, '_timed_sale_start', true ),
                    )
                );

                woocommerce_wp_text_input(
                    array(
                        'id'          => '_timed_sale_end',
                        'label'       => esc_html__( 'End Date', 'timed-sale-manager' ),
                        'desc_tip'    => true,
                        'description' => esc_html__( 'Select the end date and time for the sale', 'timed-sale-manager' ),
                        'type'        => 'datetime-local',
                        'value'       => get_post_meta( $post->ID, '_timed_sale_end', true ),
                    )
                );
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Save product tab content
     *
     * @since 1.0.0
     * @param int $post_id Product ID.
     */
    public function save_product_tab_content( $post_id ) {
        $nonce = isset( $_POST['timed_sale_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['timed_sale_nonce'] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, 'timed_sale_save_data' ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_product', $post_id ) ) {
            return;
        }

        $fields = array( '_timed_sale_price', '_timed_sale_start', '_timed_sale_end' );

        foreach ( $fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_post_meta(
                    $post_id,
                    $field,
                    sanitize_text_field( wp_unslash( $_POST[ $field ] ) )
                );
            }
        }
    }

    /**
     * Get timed sale price
     *
     * @since 1.0.0
     * @param string    $price   Product price.
     * @param WC_Product $product Product object.
     * @return string
     */
    public function get_timed_sale_price( $price, $product ) {
        $product_id = $product->get_id();
        $sale_price = get_post_meta( $product_id, '_timed_sale_price', true );
        $start_date = get_post_meta( $product_id, '_timed_sale_start', true );
        $end_date   = get_post_meta( $product_id, '_timed_sale_end', true );

        if ( ! empty( $sale_price ) && ! empty( $start_date ) && ! empty( $end_date ) ) {
            $current_time = current_time( 'timestamp' );
            $start_time  = strtotime( $start_date );
            $end_time    = strtotime( $end_date );

            if ( $current_time >= $start_time && $current_time <= $end_time ) {
                return $sale_price;
            }
        }

        return $price;
    }

    /**
     * Check if product is on timed sale
     *
     * @since 1.0.0
     * @param bool       $on_sale Whether the product is on sale.
     * @param WC_Product $product Product object.
     * @return bool
     */
    public function check_if_on_timed_sale( $on_sale, $product ) {
        $product_id = $product->get_id();
        $sale_price = get_post_meta( $product_id, '_timed_sale_price', true );
        $start_date = get_post_meta( $product_id, '_timed_sale_start', true );
        $end_date   = get_post_meta( $product_id, '_timed_sale_end', true );

        if ( ! empty( $sale_price ) && ! empty( $start_date ) && ! empty( $end_date ) ) {
            $current_time = current_time( 'timestamp' );
            $start_time  = strtotime( $start_date );
            $end_time    = strtotime( $end_date );

            if ( $current_time >= $start_time && $current_time <= $end_time ) {
                return true;
            }
        }

        return $on_sale;
    }
}

/**
 * Initialize plugin
 *
 * @since 1.0.0
 * @return Timed_Sale_Manager
 */
function timed_sale_manager() {
    return Timed_Sale_Manager::get_instance();
}

// Initialize plugin.
add_action( 'plugins_loaded', 'timed_sale_manager' );

/**
 * Activation hook
 *
 * @since 1.0.0
 */
function timed_sale_manager_activate() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            esc_html__(
                'This plugin requires WooCommerce to be installed and activated.',
                'timed-sale-manager'
            )
        );
    }

    create_plugin_files();
}
register_activation_hook( __FILE__, 'timed_sale_manager_activate' );

/**
 * Create necessary plugin files and directories
 *
 * @since 1.0.0
 */
function create_plugin_files() {
    // Create languages directory.
    $lang_dir = plugin_dir_path( __FILE__ ) . 'languages';
    if ( ! file_exists( $lang_dir ) ) {
        wp_mkdir_p( $lang_dir );
    }

    // Create CSS directory.
    $css_dir = plugin_dir_path( __FILE__ ) . 'css';
    if ( ! file_exists( $css_dir ) ) {
        wp_mkdir_p( $css_dir );
    }

    // Create CSS file.
    $css_file = $css_dir . '/admin.css';
    if ( ! file_exists( $css_file ) ) {
        $css_content = "
            #woocommerce-product-data ul.wc-tabs li.timed_sale_options a::before {
                content: '\\f508';
                font-family: Dashicons;
            }
            #timed_sale_data .options_group {
                padding: 10px;
                box-sizing: border-box;
            }
        ";
        file_put_contents( $css_file, wp_strip_all_tags( $css_content ) );
    }
}