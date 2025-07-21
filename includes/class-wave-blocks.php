<?php

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Classe pour l'intégration de Wave avec les blocs WooCommerce
 */
final class Wave_Blocks extends AbstractPaymentMethodType
{
    private $gateway;
    protected $name = 'wave_gateway';

    public function initialize()
    {
        $this->settings = get_option('woocommerce_wave_gateway_settings', []);
        $this->gateway = new WC_Wave_Gateway();
    }

    public function is_active()
    {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles()
    {
        wp_register_script(
            'wave-blocks-integration',
            MMG_PLUGIN_URL . 'assets/js/wave-checkout.js',
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
            wp_set_script_translations('wave-blocks-integration', 'mobile-money-gateway');
        }

        return ['wave-blocks-integration'];
    }

    public function get_payment_method_data()
    {
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->description,
            'icon' => MMG_PLUGIN_URL . 'assets/images/wave-logo.png',
            'phone_number_field' => $this->gateway->get_option('phone_number_field') === 'yes',
            'gateway_id' => $this->name,
            'phone_pattern' => '^(\+221|221)?[73][0-9]{7}$',
            'phone_placeholder' => '+221 XX XXX XX XX',
            'country' => 'SN', // Wave est principalement au Sénégal
            'supported_currencies' => ['XOF', 'EUR'],
            'current_currency' => get_woocommerce_currency(),
            'is_sandbox' => $this->gateway->get_option('sandbox_mode') === 'yes',
            'merchant_name' => get_bloginfo('name')
        ];
    }

    /**
     * Retourne les données de configuration pour le script JavaScript
     */
    public function get_payment_method_script_handles_localization()
    {
        return [
            'wave_gateway_data' => $this->get_payment_method_data()
        ];
    }

    /**
     * Vérifie si la passerelle peut traiter les paiements
     */
    public function can_make_payment()
    {
        // Vérifier si les clés API sont configurées
        if (empty($this->gateway->get_option('api_key')) || 
            empty($this->gateway->get_option('secret_key'))) {
            return false;
        }

        // Vérifier la devise - Wave supporte principalement XOF et EUR
        $current_currency = get_woocommerce_currency();
        $supported_currencies = ['XOF', 'EUR'];
        
        if (!in_array($current_currency, $supported_currencies)) {
            return false;
        }

        return true;
    }

    /**
     * Retourne les fonctionnalités supportées par Wave
     */
    public function get_supported_features()
    {
        return [
            'products',
            'refunds', // Wave supporte les remboursements
            'tokenization' => false, // Wave ne supporte pas encore la tokenisation
            'subscriptions' => true, // Wave peut supporter les abonnements
            'multiple_subscriptions' => false,
            'subscription_cancellation' => true,
            'subscription_suspension' => false,
            'subscription_reactivation' => false,
            'subscription_amount_changes' => false,
            'subscription_date_changes' => false,
            'subscription_payment_method_change' => false,
            'pre-orders' => true // Wave peut supporter les pré-commandes
        ];
    }

    /**
     * Validation côté serveur pour les champs de paiement Wave
     */
    public function validate_payment_data($payment_data)
    {
        $errors = [];

        // Validation du numéro de téléphone Wave si requis
        if ($this->gateway->get_option('phone_number_field') === 'yes') {
            $phone_number = $payment_data['wave_gateway_phone_number'] ?? '';
            
            if (empty($phone_number)) {
                $errors[] = __('Le numéro de téléphone Wave est requis.', 'mobile-money-gateway');
            } else if (!$this->validate_wave_phone($phone_number)) {
                $errors[] = __('Format de numéro Wave invalide. Utilisez le format +221XXXXXXXX', 'mobile-money-gateway');
            }
        }

        // Validation du montant minimum Wave (si applicable)
        $order_total = WC()->cart->get_total('');
        $min_amount = $this->gateway->get_option('min_amount', 100);
        
        if ($order_total < $min_amount) {
            $errors[] = sprintf(
                __('Le montant minimum pour Wave est de %s FCFA.', 'mobile-money-gateway'),
                number_format($min_amount)
            );
        }

        return $errors;
    }

    /**
     * Validation spécifique du numéro Wave (Sénégal)
     */
    private function validate_wave_phone($phone_number)
    {
        $cleaned = preg_replace('/[\s\-()]+/', '', $phone_number);
        
        // Pattern spécifique pour Wave au Sénégal
        // Commence par 7 ou 3, suivi de 7 chiffres
        return preg_match('/^(\+221|221)?[73][0-9]{7}$/', $cleaned);
    }

    /**
     * Traitement des données de paiement avant envoi
     */
    public function process_payment_data($payment_data)
    {
        // Normaliser le numéro de téléphone Wave
        if (isset($payment_data['wave_gateway_phone_number'])) {
            $payment_data['wave_gateway_phone_number'] = $this->normalize_wave_phone(
                $payment_data['wave_gateway_phone_number']
            );
        }

        return $payment_data;
    }

    /**
     * Normalisation du numéro de téléphone Wave
     */
    private function normalize_wave_phone($phone_number)
    {
        $cleaned = preg_replace('/[\s\-()]+/', '', $phone_number);
        
        // Ajouter le préfixe +221 si manquant
        if (!preg_match('/^\+221/', $cleaned)) {
            if (preg_match('/^221/', $cleaned)) {
                $cleaned = '+' . $cleaned;
            } else if (preg_match('/^[73]/', $cleaned)) {
                $cleaned = '+221' . $cleaned;
            }
        }
        
        return $cleaned;
    }

    /**
     * Retourne les métadonnées pour le contexte du bloc Wave
     */
    public function get_block_context()
    {
        return [
            'gateway_id' => $this->name,
            'gateway_title' => $this->gateway->title,
            'gateway_description' => $this->gateway->description,
            'requires_phone' => $this->gateway->get_option('phone_number_field') === 'yes',
            'country' => 'SN',
            'currency' => 'XOF',
            'merchant_name' => get_bloginfo('name'),
            'is_sandbox' => $this->gateway->get_option('sandbox_mode') === 'yes',
            'min_amount' => $this->gateway->get_option('min_amount', 100),
            'max_amount' => $this->gateway->get_option('max_amount', 1000000),
            'phone_help_text' => __('Numéros Wave acceptés : 7XXXXXXX ou 3XXXXXXX', 'mobile-money-gateway')
        ];
    }

    /**
     * Gestion des erreurs spécifiques à Wave
     */
    public function get_error_messages()
    {
        return [
            'invalid_phone' => __('Numéro Wave invalide. Format attendu : +221XXXXXXXX', 'mobile-money-gateway'),
            'insufficient_funds' => __('Fonds insuffisants sur votre compte Wave.', 'mobile-money-gateway'),
            'service_unavailable' => __('Service Wave temporairement indisponible.', 'mobile-money-gateway'),
            'transaction_failed' => __('Transaction Wave échouée. Veuillez réessayer.', 'mobile-money-gateway'),
            'amount_too_low' => __('Montant trop faible pour Wave.', 'mobile-money-gateway'),
            'amount_too_high' => __('Montant trop élevé pour Wave.', 'mobile-money-gateway')
        ];
    }

    /**
     * Configuration des limites de transaction Wave
     */
    public function get_transaction_limits()
    {
        $currency = get_woocommerce_currency();
        
        if ($currency === 'XOF') {
            return [
                'min_amount' => 100,    // 100 FCFA
                'max_amount' => 1000000, // 1,000,000 FCFA
                'daily_limit' => 2000000 // 2,000,000 FCFA par jour
            ];
        } else if ($currency === 'EUR') {
            return [
                'min_amount' => 1,      // 1 EUR
                'max_amount' => 1500,   // 1,500 EUR
                'daily_limit' => 3000   // 3,000 EUR par jour
            ];
        }
        
        return [];
    }
}