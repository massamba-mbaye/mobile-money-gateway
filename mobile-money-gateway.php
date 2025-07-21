<?php
/*
Plugin Name: Passerelle de paiement Mobile Money pour WooCommerce
Plugin URI: https://votre-site.com/developers/wordpress
Description: Plugin de paiement mobile money supportant Wave et Orange Money pour WooCommerce
Version: 1.0.0
Author: Votre Nom
Author URI: https://votre-site.com
Text Domain: mobile-money-gateway
*/

// Sécurité - empêche l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Constantes du plugin
define('MMG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MMG_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MMG_VERSION', '1.0.0');

// Vérification de l'activation de WooCommerce
add_action('admin_init', 'mmg_check_woocommerce_active');

function mmg_check_woocommerce_active()
{
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        deactivate_plugins(plugin_basename(__FILE__));
        mmg_woocommerce_inactive_notice();
        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
        wp_safe_redirect(admin_url('plugins.php'));
        exit;
    }
}

// Message d'erreur si WooCommerce n'est pas actif
function mmg_woocommerce_inactive_notice()
{
    ?>
    <div class="error">
        <p><strong>Erreur :</strong> WooCommerce doit être activé pour que le plugin "Passerelle de paiement Mobile Money" fonctionne.</p>
    </div>
    <?php
}

// Notice pour la devise
add_action('admin_notices', 'mmg_currency_notice');

function mmg_currency_notice()
{
    $screen = get_current_screen();
    if ($screen->id === 'woocommerce_page_wc-settings' && isset($_GET['section'])) {
        if ($_GET['section'] === 'wave_gateway' || $_GET['section'] === 'orange_money_gateway') {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p>' . __('Ce plugin accepte les devises XOF (FCFA) et EUR. Assurez-vous de configurer la devise appropriée.', 'mobile-money-gateway') . '</p>';
            echo '</div>';
        }
    }
}

// Chargement des classes de passerelles
add_action('plugins_loaded', 'mmg_init_gateways', 0);

function mmg_init_gateways()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    // Inclusion des fichiers des passerelles
    include_once(MMG_PLUGIN_PATH . 'includes/class-wc-wave-gateway.php');
    include_once(MMG_PLUGIN_PATH . 'includes/class-wc-orange-money-gateway.php');
}

// Ajout des passerelles à WooCommerce
add_filter('woocommerce_payment_gateways', 'mmg_add_gateways');

function mmg_add_gateways($gateways)
{
    $gateways[] = 'WC_Wave_Gateway';
    $gateways[] = 'WC_Orange_Money_Gateway';
    return $gateways;
}

// Liens d'action dans la page des plugins
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'mmg_plugin_action_links');

function mmg_plugin_action_links($links)
{
    $settings_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=wave_gateway') . '">Wave</a>',
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=orange_money_gateway') . '">Orange Money</a>'
    );
    return array_merge($settings_links, $links);
}

// Compatibilité avec les blocs WooCommerce
add_action('before_woocommerce_init', 'mmg_declare_cart_checkout_blocks_compatibility');

function mmg_declare_cart_checkout_blocks_compatibility()
{
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}

// Enregistrement des blocs de paiement
add_action('woocommerce_blocks_loaded', 'mmg_register_payment_method_blocks');

function mmg_register_payment_method_blocks()
{
    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        return;
    }

    include_once(MMG_PLUGIN_PATH . 'includes/class-wave-blocks.php');
    include_once(MMG_PLUGIN_PATH . 'includes/class-orange-money-blocks.php');

    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            $payment_method_registry->register(new Wave_Blocks());
            $payment_method_registry->register(new Orange_Money_Blocks());
        }
    );
}

// Fonction d'activation du plugin
register_activation_hook(__FILE__, 'mmg_activate_plugin');

function mmg_activate_plugin()
{
    // Vérifier que WooCommerce est installé
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('Ce plugin nécessite WooCommerce pour fonctionner.', 'mobile-money-gateway'));
    }
}

// Chargement des fichiers de traduction
add_action('plugins_loaded', 'mmg_load_textdomain');

function mmg_load_textdomain()
{
    load_plugin_textdomain('mobile-money-gateway', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

// Ajout des devises supportées
add_filter('woocommerce_currencies', 'mmg_add_currencies');

function mmg_add_currencies($currencies)
{
    $currencies['XOF'] = __('Franc CFA (XOF)', 'mobile-money-gateway');
    return $currencies;
}

// Ajout des symboles de devises
add_filter('woocommerce_currency_symbols', 'mmg_add_currency_symbols');

function mmg_add_currency_symbols($currency_symbols)
{
    $currency_symbols['XOF'] = 'FCFA';
    return $currency_symbols;
}

// Scripts et styles admin
add_action('admin_enqueue_scripts', 'mmg_admin_scripts');

function mmg_admin_scripts($hook)
{
    if ('woocommerce_page_wc-settings' !== $hook) {
        return;
    }

    wp_enqueue_script(
        'mmg-admin-settings',
        MMG_PLUGIN_URL . 'assets/js/admin-settings.js',
        array('jquery'),
        MMG_VERSION,
        true
    );
}