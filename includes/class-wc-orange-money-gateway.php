<?php

if (!defined('ABSPATH')) {
    exit;
}

// Inclure la classe de base
require_once MMG_PLUGIN_PATH . 'includes/class-wc-mobile-money-base.php';

/**
 * Passerelle de paiement Orange Money
 */
class WC_Orange_Money_Gateway extends WC_Mobile_Money_Base
{
    public function __construct()
    {
        $this->id = 'orange_money_gateway';
        $this->method_title = __('Orange Money', 'mobile-money-gateway');
        $this->icon = MMG_PLUGIN_URL . 'assets/images/orange-money-logo.png';
        
        // URLs de l'API Orange Money (à adapter selon la vraie API)
        $this->api_url_live = 'https://api.orange.com/orange-money-webpay/v1/';
        $this->api_url_sandbox = 'https://api.orange.com/orange-money-webpay-sand/v1/';
        
        // Devises supportées par Orange Money
        $this->supported_currencies = array('XOF', 'EUR', 'USD');
        
        parent::__construct();
        
        // Configuration spécifique Orange Money
        $this->merchant_id = $this->get_option('merchant_id');
    }
    
    /**
     * Description de la méthode
     */
    public function get_method_description()
    {
        return __('Permet aux clients de payer avec Orange Money.', 'mobile-money-gateway');
    }
    
    /**
     * Type de passerelle
     */
    protected function get_gateway_type()
    {
        return 'orange_money';
    }
    
    /**
     * Champs de configuration spécifiques à Orange Money
     */
    public function init_form_fields()
    {
        parent::init_form_fields();
        
        // Ajouter des champs spécifiques à Orange Money
        $orange_fields = array(
            'merchant_id' => array(
                'title' => __('ID Marchand', 'mobile-money-gateway'),
                'type' => 'text',
                'description' => __('Votre identifiant marchand Orange Money.', 'mobile-money-gateway'),
            ),
            'merchant_name' => array(
                'title' => __('Nom du marchand', 'mobile-money-gateway'),
                'type' => 'text',
                'description' => __('Le nom de votre entreprise tel qu\'il apparaîtra sur Orange Money.', 'mobile-money-gateway'),
                'default' => get_bloginfo('name')
            ),
            'country_code' => array(
                'title' => __('Code pays', 'mobile-money-gateway'),
                'type' => 'select',
                'description' => __('Sélectionnez votre pays pour Orange Money.', 'mobile-money-gateway'),
                'options' => array(
                    'SN' => __('Sénégal', 'mobile-money-gateway'),
                    'CI' => __('Côte d\'Ivoire', 'mobile-money-gateway'),
                    'ML' => __('Mali', 'mobile-money-gateway'),
                    'BF' => __('Burkina Faso', 'mobile-money-gateway'),
                    'NE' => __('Niger', 'mobile-money-gateway'),
                    'GN' => __('Guinée', 'mobile-money-gateway'),
                    'CM' => __('Cameroun', 'mobile-money-gateway'),
                ),
                'default' => 'SN'
            )
        );
        
        // Insérer les nouveaux champs après les champs de base
        $position = array_search('secret_key', array_keys($this->form_fields)) + 1;
        $this->form_fields = array_slice($this->form_fields, 0, $position, true) +
                            $orange_fields +
                            array_slice($this->form_fields, $position, null, true);
    }
    
    /**
     * Placeholder pour le numéro Orange Money
     */
    protected function get_phone_placeholder()
    {
        $country_code = $this->get_option('country_code', 'SN');
        
        switch ($country_code) {
            case 'SN':
                return '+221 XX XXX XX XX';
            case 'CI':
                return '+225 XX XX XX XX XX';
            case 'ML':
                return '+223 XX XX XX XX';
            case 'BF':
                return '+226 XX XX XX XX';
            default:
                return '+XXX XX XXX XX XX';
        }
    }
    
    /**
     * Texte d'aide pour Orange Money
     */
    protected function get_phone_help_text()
    {
        return __('Entrez votre numéro de téléphone Orange Money', 'mobile-money-gateway');
    }
    
    /**
     * Validation du numéro Orange Money
     */
    protected function validate_phone_number($phone_number)
    {
        $cleaned = preg_replace('/[\s\-()]+/', '', $phone_number);
        $country_code = $this->get_option('country_code', 'SN');
        
        switch ($country_code) {
            case 'SN': // Sénégal
                return preg_match('/^(\+221|221)?[73][0-9]{7}$/', $cleaned);
            case 'CI': // Côte d'Ivoire
                return preg_match('/^(\+225|225)?[0-9]{8,10}$/', $cleaned);
            case 'ML': // Mali
                return preg_match('/^(\+223|223)?[0-9]{8}$/', $cleaned);
            case 'BF': // Burkina Faso
                return preg_match('/^(\+226|226)?[0-9]{8}$/', $cleaned);
            default:
                return preg_match('/^[+]?[0-9]{8,15}$/', $cleaned);
        }
    }
    
    /**
     * Normalise le numéro pour Orange Money
     */
    private function normalize_phone_number($phone_number)
    {
        $cleaned = preg_replace('/[\s\-()]+/', '', $phone_number);
        $country_code = $this->get_option('country_code', 'SN');
        
        // Préfixes par pays
        $prefixes = array(
            'SN' => '+221',
            'CI' => '+225',
            'ML' => '+223',
            'BF' => '+226',
            'NE' => '+227',
            'GN' => '+224',
            'CM' => '+237'
        );
        
        $prefix = $prefixes[$country_code] ?? '+221';
        
        // Ajoute le préfixe si manquant
        if (!preg_match('/^\+/', $cleaned)) {
            if (preg_match('/^' . substr($prefix, 1) . '/', $cleaned)) {
                $cleaned = '+' . $cleaned;
            } else if (!preg_match('/^[0-9]+$/', $cleaned)) {
                return $cleaned; // Retourner tel quel si format non reconnu
            } else {
                $cleaned = $prefix . $cleaned;
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Vérification de la disponibilité
     */
    public function is_available()
    {
        if (!parent::is_available()) {
            return false;
        }
        
        if (empty($this->merchant_id)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Traitement du paiement Orange Money
     */
    protected function process_mobile_money_payment($order, $phone_number)
    {
        try {
            $this->log("Début du traitement paiement Orange Money pour commande #{$order->get_id()}");
            
            // Normalisation du numéro
            $normalized_phone = $this->normalize_phone_number($phone_number);
            
            // Obtenir le token d'autorisation
            $auth_token = $this->get_auth_token();
            if (!$auth_token) {
                throw new Exception(__('Impossible d\'obtenir le token d\'autorisation Orange Money.', 'mobile-money-gateway'));
            }
            
            // Préparation des données pour l'API Orange Money
            $payment_data = array(
                'merchant_key' => $this->merchant_id,
                'currency' => get_woocommerce_currency(),
                'order_id' => $order->get_id(),
                'amount' => $this->format_amount($order->get_total()),
                'return_url' => $this->get_return_url($order),
                'cancel_url' => $order->get_cancel_order_url(),
                'notif_url' => $this->get_callback_url(),
                'lang' => 'fr',
                'reference' => $this->generate_transaction_id($order->get_id()),
                'customer_msisdn' => $normalized_phone,
                'customer_email' => $order->get_billing_email(),
                'customer_firstname' => $order->get_billing_first_name(),
                'customer_lastname' => $order->get_billing_last_name()
            );
            
            $this->log('Données envoyées à Orange Money: ' . json_encode($payment_data));
            
            // Appel à l'API Orange Money
            $response = $this->call_orange_api('webpayment', $payment_data, $auth_token);
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);
            
            $this->log('Réponse d\'Orange Money: ' . $response_body);
            
            if (isset($response_data['payment_url'])) {
                // Sauvegarde des métadonnées
                $order->update_meta_data('_orange_money_reference', $payment_data['reference']);
                $order->update_meta_data('_orange_money_payment_url', $response_data['payment_url']);
                if (isset($response_data['pay_token'])) {
                    $order->update_meta_data('_orange_money_pay_token', $response_data['pay_token']);
                }
                $order->save();
                
                // Mise à jour du statut
                $order->update_status('pending', __('En attente du paiement Orange Money.', 'mobile-money-gateway'));
                
                // Vider le panier
                WC()->cart->empty_cart();
                
                return array(
                    'result' => 'success',
                    'redirect' => $response_data['payment_url']
                );
            } else {
                throw new Exception(
                    isset($response_data['message']) 
                        ? $response_data['message'] 
                        : __('Erreur lors de l\'initialisation du paiement Orange Money.', 'mobile-money-gateway')
                );
            }
            
        } catch (Exception $e) {
            $this->log('Erreur paiement Orange Money: ' . $e->getMessage(), 'error');
            
            wc_add_notice(
                sprintf(__('Erreur de paiement: %s', 'mobile-money-gateway'), $e->getMessage()),
                'error'
            );
            
            return array(
                'result' => 'failure',
                'messages' => $e->getMessage()
            );
        }
    }
    
    /**
     * Obtenir le token d'autorisation Orange Money
     */
    private function get_auth_token()
    {
        try {
            $credentials = base64_encode($this->api_key . ':' . $this->secret_key);
            
            $headers = array(
                'Authorization' => 'Basic ' . $credentials,
                'Content-Type' => 'application/x-www-form-urlencoded'
            );
            
            $body = 'grant_type=client_credentials';
            
            $args = array(
                'method' => 'POST',
                'headers' => $headers,
                'body' => $body,
                'timeout' => 30,
                'sslverify' => !$this->sandbox_mode
            );
            
            $url = $this->get_api_url() . 'oauth/token';
            $response = wp_remote_request($url, $args);
            
            if (is_wp_error($response)) {
                $this->log('Erreur auth Orange Money: ' . $response->get_error_message(), 'error');
                return false;
            }
            
            $response_body = wp_remote_retrieve_body($response);
            $data = json_decode($response_body, true);
            
            if (isset($data['access_token'])) {
                return $data['access_token'];
            }
            
            $this->log('Échec auth Orange Money: ' . $response_body, 'error');
            return false;
            
        } catch (Exception $e) {
            $this->log('Exception auth Orange Money: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Appel à l'API Orange Money
     */
    private function call_orange_api($endpoint, $data, $auth_token)
    {
        $url = $this->get_api_url() . $endpoint;
        
        $headers = array(
            'Authorization' => 'Bearer ' . $auth_token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        );
        
        $args = array(
            'method' => 'POST',
            'headers' => $headers,
            'body' => json_encode($data),
            'timeout' => 30,
            'sslverify' => !$this->sandbox_mode
        );
        
        return wp_remote_request($url, $args);
    }
    
    /**
     * URL de callback pour Orange Money
     */
    private function get_callback_url()
    {
        return add_query_arg('wc-api', strtolower(get_class($this)), home_url('/'));
    }
    
    /**
     * Gestion des webhooks Orange Money
     */
    public function handle_webhook()
    {
        $this->log('Webhook Orange Money reçu');
        
        try {
            // Orange Money envoie généralement des données via POST form
            $status = sanitize_text_field($_POST['status'] ?? '');
            $order_id = sanitize_text_field($_POST['order_id'] ?? '');
            $reference = sanitize_text_field($_POST['reference'] ?? '');
            $amount = sanitize_text_field($_POST['amount'] ?? '');
            $transaction_id = sanitize_text_field($_POST['txnid'] ?? '');
            
            $this->log('Données webhook Orange Money: ' . json_encode($_POST));
            
            if (empty($order_id)) {
                throw new Exception('ID de commande manquant');
            }
            
            $order = wc_get_order($order_id);
            if (!$order) {
                throw new Exception('Commande non trouvée: ' . $order_id);
            }
            
            // Vérification de la référence
            $stored_reference = $order->get_meta('_orange_money_reference');
            if ($stored_reference && $stored_reference !== $reference) {
                throw new Exception('Référence non correspondante');
            }
            
            // Traitement selon le statut
            switch (strtolower($status)) {
                case 'success':
                case 'completed':
                    $this->handle_successful_payment($order, array(
                        'transaction_id' => $transaction_id,
                        'amount' => $amount,
                        'reference' => $reference
                    ));
                    break;
                    
                case 'failed':
                case 'cancelled':
                case 'expired':
                    $this->handle_failed_payment($order, array(
                        'failure_reason' => $_POST['failure_reason'] ?? 'Paiement annulé ou expiré'
                    ));
                    break;
                    
                case 'pending':
                    $this->handle_pending_payment($order, array());
                    break;
                    
                default:
                    $this->log('Statut webhook Orange Money inconnu: ' . $status, 'warning');
            }
            
            // Réponse de succès
            http_response_code(200);
            echo 'OK';
            exit;
            
        } catch (Exception $e) {
            $this->log('Erreur webhook Orange Money: ' . $e->getMessage(), 'error');
            http_response_code(400);
            echo 'Error: ' . $e->getMessage();
            exit;
        }
    }
    
    /**
     * Traitement d'un paiement réussi
     */
    private function handle_successful_payment($order, $data)
    {
        if ($order->is_paid()) {
            $this->log('Commande déjà payée: ' . $order->get_id());
            return;
        }
        
        $transaction_id = $data['transaction_id'] ?? '';
        $amount = floatval($data['amount'] ?? 0);
        
        // Vérification du montant si fourni
        if ($amount > 0 && $amount != $this->format_amount($order->get_total())) {
            $this->log('Montant incorrect. Attendu: ' . $order->get_total() . ', Reçu: ' . $amount, 'error');
            return;
        }
        
        // Marquer comme payé
        $order->payment_complete($transaction_id);
        
        // Ajouter une note
        $order->add_order_note(sprintf(
            __('Paiement Orange Money confirmé. ID de transaction: %s', 'mobile-money-gateway'),
            $transaction_id
        ));
        
        // Mettre à jour les métadonnées
        $order->update_meta_data('_orange_money_transaction_id', $transaction_id);
        $order->update_meta_data('_orange_money_payment_completed', current_time('mysql'));
        $order->save();
        
        $this->log('Paiement Orange Money confirmé pour commande #' . $order->get_id());
    }
    
    /**
     * Traitement d'un paiement échoué
     */
    private function handle_failed_payment($order, $data)
    {
        $reason = $data['failure_reason'] ?? __('Raison inconnue', 'mobile-money-gateway');
        
        $order->update_status('failed', sprintf(
            __('Paiement Orange Money échoué: %s', 'mobile-money-gateway'),
            $reason
        ));
        
        $this->log('Paiement Orange Money échoué pour commande #' . $order->get_id() . ': ' . $reason);
    }
    
    /**
     * Traitement d'un paiement en attente
     */
    private function handle_pending_payment($order, $data)
    {
        $order->update_status('on-hold', __('Paiement Orange Money en attente de confirmation.', 'mobile-money-gateway'));
        
        $this->log('Paiement Orange Money en attente pour commande #' . $order->get_id());
    }
    
    /**
     * Remboursement via Orange Money
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return new WP_Error('invalid_order', __('Commande non trouvée.', 'mobile-money-gateway'));
        }
        
        $transaction_id = $order->get_meta('_orange_money_transaction_id');
        
        if (empty($transaction_id)) {
            return new WP_Error('no_transaction', __('ID de transaction Orange Money non trouvé.', 'mobile-money-gateway'));
        }
        
        // Orange Money ne supporte pas toujours les remboursements automatiques
        // Dans ce cas, on peut juste enregistrer la demande de remboursement
        
        $order->add_order_note(sprintf(
            __('Demande de remboursement Orange Money de %s. Raison: %s. Transaction ID: %s', 'mobile-money-gateway'),
            wc_price($amount ?: $order->get_total()),
            $reason ?: __('Remboursement demandé', 'mobile-money-gateway'),
            $transaction_id
        ));
        
        // Retourner false pour indiquer que le remboursement doit être fait manuellement
        return new WP_Error(
            'manual_refund_required', 
            __('Le remboursement Orange Money doit être effectué manuellement. Une note a été ajoutée à la commande.', 'mobile-money-gateway')
        );
    }
}