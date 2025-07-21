<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe de base pour les passerelles Mobile Money
 */
class WC_Mobile_Money_Base extends WC_Payment_Gateway
{
    protected $api_url_live;
    protected $api_url_sandbox;
    protected $api_key;
    protected $secret_key;
    protected $sandbox_mode;
    protected $phone_number_field;
    protected $supported_currencies = array('XOF', 'EUR');
    
    public function __construct()
    {
        $this->has_fields = true;
        $this->method_description = $this->get_method_description();
        
        $this->init_form_fields();
        $this->init_settings();
        
        // Propriétés communes
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->api_key = $this->get_option('api_key');
        $this->secret_key = $this->get_option('secret_key');
        $this->sandbox_mode = 'yes' === $this->get_option('sandbox_mode');
        $this->phone_number_field = 'yes' === $this->get_option('phone_number_field');
        
        // Hooks
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'handle_webhook'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
    }
    
    /**
     * Description de la méthode (à surcharger dans les classes enfants)
     */
    public function get_method_description()
    {
        return __('Passerelle de paiement mobile money', 'mobile-money-gateway');
    }
    
    /**
     * Identifie le type de passerelle (à surcharger)
     */
    protected function get_gateway_type()
    {
        return 'generic';
    }
    
    /**
     * Obtenir l'URL de l'API selon l'environnement
     */
    protected function get_api_url()
    {
        return $this->sandbox_mode ? $this->api_url_sandbox : $this->api_url_live;
    }
    
    /**
     * Champs de configuration communs
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Activer/Désactiver', 'mobile-money-gateway'),
                'type' => 'checkbox',
                'label' => sprintf(__('Activer %s', 'mobile-money-gateway'), $this->method_title),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Titre', 'mobile-money-gateway'),
                'type' => 'text',
                'description' => __('Le titre que vos clients verront lors du checkout.', 'mobile-money-gateway'),
                'default' => $this->method_title
            ),
            'description' => array(
                'title' => __('Description', 'mobile-money-gateway'),
                'type' => 'textarea',
                'description' => __('Description que verront vos clients lors du checkout.', 'mobile-money-gateway'),
                'default' => sprintf(__('Payez en toute sécurité avec %s.', 'mobile-money-gateway'), $this->method_title)
            ),
            'api_key' => array(
                'title' => __('Clé API', 'mobile-money-gateway'),
                'type' => 'text',
                'description' => sprintf(__('Votre clé API %s.', 'mobile-money-gateway'), $this->method_title),
            ),
            'secret_key' => array(
                'title' => __('Clé Secrète', 'mobile-money-gateway'),
                'type' => 'password',
                'description' => sprintf(__('Votre clé secrète %s.', 'mobile-money-gateway'), $this->method_title),
            ),
            'sandbox_mode' => array(
                'title' => __('Mode Test', 'mobile-money-gateway'),
                'type' => 'checkbox',
                'label' => __('Activer le mode test', 'mobile-money-gateway'),
                'description' => __('Utilisez ceci pour tester les paiements. Aucun paiement réel ne sera traité.', 'mobile-money-gateway'),
                'default' => 'yes'
            ),
            'phone_number_field' => array(
                'title' => __('Champ numéro de téléphone', 'mobile-money-gateway'),
                'type' => 'checkbox',
                'label' => __('Afficher le champ de saisie du numéro de téléphone', 'mobile-money-gateway'),
                'description' => __('Permet au client de saisir son numéro de téléphone mobile money.', 'mobile-money-gateway'),
                'default' => 'yes'
            ),
            'debug' => array(
                'title' => __('Debug', 'mobile-money-gateway'),
                'type' => 'checkbox',
                'label' => __('Activer les logs de débogage', 'mobile-money-gateway'),
                'description' => sprintf(__('Enregistrer les événements dans %s', 'mobile-money-gateway'), '<code>WooCommerce > Status > Logs</code>'),
                'default' => 'no'
            )
        );
    }
    
    /**
     * Affichage des champs de paiement
     */
    public function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }
        
        if ($this->phone_number_field) {
            $this->render_phone_number_field();
        }
    }
    
    /**
     * Champ numéro de téléphone
     */
    protected function render_phone_number_field()
    {
        ?>
        <fieldset>
            <p class="form-row form-row-wide">
                <label for="<?php echo esc_attr($this->id); ?>_phone_number">
                    <?php echo sprintf(__('Numéro de téléphone %s', 'mobile-money-gateway'), $this->method_title); ?>
                    <span class="required">*</span>
                </label>
                <input 
                    id="<?php echo esc_attr($this->id); ?>_phone_number" 
                    name="<?php echo esc_attr($this->id); ?>_phone_number" 
                    type="tel" 
                    placeholder="<?php echo esc_attr($this->get_phone_placeholder()); ?>"
                    pattern="<?php echo esc_attr($this->get_phone_pattern()); ?>"
                    required
                />
                <small><?php echo esc_html($this->get_phone_help_text()); ?></small>
            </p>
        </fieldset>
        <?php
    }
    
    /**
     * Placeholder pour le numéro de téléphone (à surcharger)
     */
    protected function get_phone_placeholder()
    {
        return '+221 XX XXX XX XX';
    }
    
    /**
     * Pattern de validation pour le numéro (à surcharger)
     */
    protected function get_phone_pattern()
    {
        return '[+]?[0-9\s\-()]+';
    }
    
    /**
     * Texte d'aide pour le numéro (à surcharger)
     */
    protected function get_phone_help_text()
    {
        return __('Entrez votre numéro de téléphone mobile money', 'mobile-money-gateway');
    }
    
    /**
     * Scripts pour le front-end
     */
    public function payment_scripts()
    {
        if (!is_admin() && !is_cart() && !is_checkout()) {
            return;
        }
        
        wp_enqueue_script(
            $this->id . '-checkout',
            MMG_PLUGIN_URL . 'assets/js/checkout.js',
            array('jquery'),
            MMG_VERSION,
            true
        );
        
        wp_localize_script($this->id . '-checkout', 'mmg_params', array(
            'gateway_id' => $this->id,
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce($this->id . '_nonce')
        ));
    }
    
    /**
     * Validation des champs
     */
    public function validate_fields()
    {
        if ($this->phone_number_field) {
            $phone_number = sanitize_text_field($_POST[$this->id . '_phone_number'] ?? '');
            
            if (empty($phone_number)) {
                wc_add_notice(__('Le numéro de téléphone est requis.', 'mobile-money-gateway'), 'error');
                return false;
            }
            
            if (!$this->validate_phone_number($phone_number)) {
                wc_add_notice(__('Veuillez entrer un numéro de téléphone valide.', 'mobile-money-gateway'), 'error');
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Validation du numéro de téléphone (à surcharger si nécessaire)
     */
    protected function validate_phone_number($phone_number)
    {
        // Supprime tous les espaces, tirets et parenthèses
        $cleaned = preg_replace('/[\s\-()]+/', '', $phone_number);
        
        // Vérifie si c'est un numéro valide (au moins 8 chiffres)
        return preg_match('/^[+]?[0-9]{8,15}$/', $cleaned);
    }
    
    /**
     * Vérification de la disponibilité
     */
    public function is_available()
    {
        if (!parent::is_available()) {
            return false;
        }
        
        if (!in_array(get_woocommerce_currency(), $this->supported_currencies)) {
            return false;
        }
        
        if (empty($this->api_key) || empty($this->secret_key)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Traitement du paiement
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return array(
                'result' => 'failure',
                'messages' => __('Commande non trouvée.', 'mobile-money-gateway')
            );
        }
        
        // Récupération du numéro de téléphone si nécessaire
        $phone_number = '';
        if ($this->phone_number_field) {
            $phone_number = sanitize_text_field($_POST[$this->id . '_phone_number'] ?? '');
        }
        
        // Appel à la méthode de traitement spécifique
        return $this->process_mobile_money_payment($order, $phone_number);
    }
    
    /**
     * Traitement spécifique du paiement mobile money (à surcharger)
     */
    protected function process_mobile_money_payment($order, $phone_number)
    {
        // Implémentation par défaut - doit être surchargée
        return array(
            'result' => 'failure',
            'messages' => __('Méthode de paiement non implémentée.', 'mobile-money-gateway')
        );
    }
    
    /**
     * Gestion des webhooks (à surcharger)
     */
    public function handle_webhook()
    {
        // Implémentation par défaut - doit être surchargée
        http_response_code(200);
        echo 'Webhook reçu mais non traité';
        exit;
    }
    
    /**
     * Log des messages de débogage
     */
    protected function log($message, $level = 'info')
    {
        if ('yes' !== $this->get_option('debug')) {
            return;
        }
        
        if (!isset($this->logger)) {
            $this->logger = wc_get_logger();
        }
        
        $this->logger->log($level, $message, array('source' => $this->id));
    }
    
    /**
     * Formatage du montant selon la devise
     */
    protected function format_amount($amount, $currency = null)
    {
        if (!$currency) {
            $currency = get_woocommerce_currency();
        }
        
        // Pour XOF, pas de décimales
        if ($currency === 'XOF') {
            return intval($amount);
        }
        
        // Pour EUR, 2 décimales
        return number_format($amount, 2, '.', '');
    }
    
    /**
     * Génération d'un ID de transaction unique
     */
    protected function generate_transaction_id($order_id)
    {
        return $order_id . '_' . time() . '_' . wp_generate_password(6, false);
    }
}