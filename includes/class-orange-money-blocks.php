<?php

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Classe pour l'intégration d'Orange Money avec les blocs WooCommerce
 */
final class Orange_Money_Blocks extends AbstractPaymentMethodType
{
    private $gateway;
    protected $name = 'orange_money_gateway';

    public function initialize()
    {
        $this->settings = get_option('woocommerce_orange_money_gateway_settings', []);
        $this->gateway = new WC_Orange_Money_Gateway();
    }

    public function is_active()
    {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles()
    {
        wp_register_script(
            'orange-money-blocks-integration',
            MMG_PLUGIN_URL . 'assets/js/orange-money-checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            MMG_VERSION,
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('orange-money-blocks-integration', 'mobile-money-gateway');
        }

        return ['orange-money-blocks-integration'];
    }

    public function get_payment_method_data()
    {
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->description,
            'icon' => MMG_PLUGIN_URL . 'assets/images/orange-money-logo.png',
            'phone_number_field' => $this->gateway->get_option('phone_number_field') === 'yes',
            'gateway_id' => $this->name,
            'country_code' => $this->gateway->get_option('country_code', 'SN'),
            'merchant_name' => $this->gateway->get_option('merchant_name', get_bloginfo('name')),
            'supported_countries' => [
                'SN' => __('Sénégal', 'mobile-money-gateway'),
                'CI' => __('Côte d\'Ivoire', 'mobile-money-gateway'),
                'ML' => __('Mali', 'mobile-money-gateway'),
                'BF' => __('Burkina Faso', 'mobile-money-gateway'),
                'NE' => __('Niger', 'mobile-money-gateway'),
                'GN' => __('Guinée', 'mobile-money-gateway'),
                'CM' => __('Cameroun', 'mobile-money-gateway'),
            ],
            'phone_patterns' => [
                'SN' => '^(\+221|221)?[73][0-9]{7}$',
                'CI' => '^(\+225|225)?[0-9]{8,10}$',
                'ML' => '^(\+223|223)?[0-9]{8}$',
                'BF' => '^(\+226|226)?[0-9]{8}$',
                'NE' => '^(\+227|227)?[0-9]{8}$',
                'GN' => '^(\+224|224)?[0-9]{8}$',
                'CM' => '^(\+237|237)?[0-9]{8}$',
            ],
            'phone_placeholders' => [
                'SN' => '+221 XX XXX XX XX',
                'CI' => '+225 XX XX XX XX XX',
                'ML' => '+223 XX XX XX XX',
                'BF' => '+226 XX XX XX XX',
                'NE' => '+227 XX XX XX XX',
                'GN' => '+224 XX XX XX XX',
                'CM' => '+237 XX XX XX XX',
            ]
        ];
    }

    /**
     * Retourne les données de configuration pour le script JavaScript
     */
    public function get_payment_method_script_handles_localization()
    {
        return [
            'orange_money_gateway_data' => $this->get_payment_method_data()
        ];
    }

    /**
     * Vérifie si la passerelle peut traiter les paiements
     */
    public function can_make_payment()
    {
        // Vérifier si les clés API sont configurées
        if (empty($this->gateway->get_option('api_key')) || 
            empty($this->gateway->get_option('secret_key')) ||
            empty($this->gateway->get_option('merchant_id'))) {
            return false;
        }

        // Vérifier la devise
        $current_currency = get_woocommerce_currency();
        $supported_currencies = ['XOF', 'EUR', 'USD'];
        
        if (!in_array($current_currency, $supported_currencies)) {
            return false;
        }

        return true;
    }

    /**
     * Retourne les fonctionnalités supportées
     */
    public function get_supported_features()
    {
        return [
            'products',
            'refunds',
            'tokenization' => false, // Orange Money ne supporte généralement pas la tokenisation
            'subscriptions' => false, // Nécessite une configuration spéciale
            'multiple_subscriptions' => false,
            'subscription_cancellation' => false,
            'subscription_suspension' => false,
            'subscription_reactivation' => false,
            'subscription_amount_changes' => false,
            'subscription_date_changes' => false,
            'subscription_payment_method_change' => false,
            'pre-orders' => false
        ];
    }

    /**
     * Validation côté serveur pour les champs de paiement
     */
    public function validate_payment_data($payment_data)
    {
        $errors = [];

        // Validation du numéro de téléphone si requis
        if ($this->gateway->get_option('phone_number_field') === 'yes') {
            $phone_number = $payment_data['orange_money_gateway_phone_number'] ?? '';
            
            if (empty($phone_number)) {
                $errors[] = __('Le numéro de téléphone Orange Money est requis.', 'mobile-money-gateway');
            } else {
                $country_code = $this->gateway->get_option('country_code', 'SN');
                if (!$this->validate_phone_for_country($phone_number, $country_code)) {
                    $errors[] = __('Format de numéro Orange Money invalide pour ce pays.', 'mobile-money-gateway');
                }
            }
        }

        return $errors;
    }

    /**
     * Validation du numéro de téléphone selon le pays
     */
    private function validate_phone_for_country($phone_number, $country_code)
    {
        $cleaned = preg_replace('/[\s\-()]+/', '', $phone_number);
        
        $patterns = [
            'SN' => '/^(\+221|221)?[73][0-9]{7}$/',
            'CI' => '/^(\+225|225)?[0-9]{8,10}$/',
            'ML' => '/^(\+223|223)?[0-9]{8}$/',
            'BF' => '/^(\+226|226)?[0-9]{8}$/',
            'NE' => '/^(\+227|227)?[0-9]{8}$/',
            'GN' => '/^(\+224|224)?[0-9]{8}$/',
            'CM' => '/^(\+237|237)?[0-9]{8}$/',
        ];

        $pattern = $patterns[$country_code] ?? '/^[+]?[0-9]{8,15}$/';
        return preg_match($pattern, $cleaned);
    }

    /**
     * Traitement des données de paiement avant envoi
     */
    public function process_payment_data($payment_data)
    {
        // Normaliser le numéro de téléphone
        if (isset($payment_data['orange_money_gateway_phone_number'])) {
            $payment_data['orange_money_gateway_phone_number'] = $this->normalize_phone_number(
                $payment_data['orange_money_gateway_phone_number'],
                $this->gateway->get_option('country_code', 'SN')
            );
        }

        return $payment_data;
    }

    /**
     * Normalisation du numéro de téléphone
     */
    private function normalize_phone_number($phone_number, $country_code)
    {
        $cleaned = preg_replace('/[\s\-()]+/', '', $phone_number);
        
        $prefixes = [
            'SN' => '+221',
            'CI' => '+225',
            'ML' => '+223',
            'BF' => '+226',
            'NE' => '+227',
            'GN' => '+224',
            'CM' => '+237'
        ];

        $prefix = $prefixes[$country_code] ?? '+221';

        // Ajouter le préfixe si manquant
        if (!preg_match('/^\+/', $cleaned)) {
            if (preg_match('/^' . substr($prefix, 1) . '/', $cleaned)) {
                $cleaned = '+' . $cleaned;
            } else if (preg_match('/^[0-9]+$/', $cleaned)) {
                $cleaned = $prefix . $cleaned;
            }
        }

        return $cleaned;
    }

    /**
     * Retourne les métadonnées pour le contexte du bloc
     */
    public function get_block_context()
    {
        return [
            'gateway_id' => $this->name,
            'gateway_title' => $this->gateway->title,
            'gateway_description' => $this->gateway->description,
            'requires_phone' => $this->gateway->get_option('phone_number_field') === 'yes',
            'country_code' => $this->gateway->get_option('country_code', 'SN'),
            'merchant_name' => $this->gateway->get_option('merchant_name', get_bloginfo('name')),
            'is_sandbox' => $this->gateway->get_option('sandbox_mode') === 'yes',
            'supported_currencies' => ['XOF', 'EUR', 'USD'],
            'current_currency' => get_woocommerce_currency()
        ];
    }
}