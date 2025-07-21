<?php

if (!defined('ABSPATH')) {
    exit;
}

// Inclure la classe de base
require_once MMG_PLUGIN_PATH . 'includes/class-wc-mobile-money-base.php';

/**
 * Passerelle de paiement Wave
 */
class WC_Wave_Gateway extends WC_Mobile_Money_Base
{
    public function __construct()
    {
        $this->id = 'wave_gateway';
        $this->method_title = __('Wave', 'mobile-money-gateway');
        $this->icon = MMG_PLUGIN_URL . 'assets/images/wave-logo.png';
        
        // URLs de l'API Wave (à adapter selon la vraie API)
        $this->api_url_live = 'https://api.wave.com/v1/';
        $this->api_url_sandbox = 'https://api.sandbox.wave.com/v1/';
        
        parent::__construct();
    }
    
    /**
     * Description de la méthode
     */
    public function get_method_description()
    {
        return __('Permet aux clients de payer avec Wave Money.', 'mobile-money-gateway');
    }
    
    /**
     * Type de passerelle
     */
    protected function get_gateway_type()
    {
        return 'wave';
    }
    
    /**
     * Placeholder pour le numéro Wave
     */
    protected function get_phone_placeholder()
    {
        return '+221 XX XXX XX XX';
    }
    
    /**
     * Texte d'aide pour Wave
     */
    protected function get_phone_help_text()
    {
        return __('Entrez votre numéro de téléphone Wave (format: +221XXXXXXXX)', 'mobile-money-gateway');
    }
    
    /**
     * Validation du numéro Wave
     */
    protected function validate_phone_number($phone_number)
    {
        // Supprime tous les espaces, tirets et parenthèses
        $cleaned = preg_replace('/[\s\-()]+/', '', $phone_number);
        
        // Vérifie le format sénégalais pour Wave
        if (preg_match('/^(\+221|221)?[73][0-9]{7}$/', $cleaned)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Normalise le numéro de téléphone pour Wave
     */
    private function normalize_phone_number($phone_number)
    {
        $cleaned = preg_replace('/[\s\-()]+/', '', $phone_number);
        
        // Ajoute le préfixe +221 si manquant
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
     * Traitement du paiement Wave
     */
    protected function process_mobile_money_payment($order, $phone_number)
    {
        try {
            $this->log("Début du traitement paiement Wave pour commande #{$order->get_id()}");
            
            // Normalisation du numéro
            $normalized_phone = $this->normalize_phone_number($phone_number);
            
            // Préparation des données pour l'API Wave
            $payment_data = array(
                'amount' => $this->format_amount($order->get_total()),
                'currency' => get_woocommerce_currency(),
                'phone_number' => $normalized_phone,
                'description' => sprintf(
                    __('Paiement commande #%s - %s', 'mobile-money-gateway'),
                    $order->get_id(),
                    get_bloginfo('name')
                ),
                'order_id' => $order->get_id(),
                'transaction_id' => $this->generate_transaction_id($order->get_id()),
                'callback_url' => $this->get_callback_url(),
                'return_url' => $this->get_return_url($order),
                'cancel_url' => $order->get_cancel_order_url()
            );
            
            $this->log('Données envoyées à Wave: ' . json_encode($payment_data));
            
            // Appel à l'API Wave
            $response = $this->call_wave_api('payments', $payment_data);
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);
            
            $this->log('Réponse de Wave: ' . $response_body);
            
            if (isset($response_data['status']) && $response_data['status'] === 'success') {
                // Sauvegarde des métadonnées de transaction
                $order->update_meta_data('_wave_transaction_id', $response_data['transaction_id']);
                $order->update_meta_data('_wave_payment_url', $response_data['payment_url']);
                $order->save();
                
                // Mise à jour du statut
                $order->update_status('pending', __('En attente du paiement Wave.', 'mobile-money-gateway'));
                
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
                        : __('Erreur lors de l\'initialisation du paiement Wave.', 'mobile-money-gateway')
                );
            }
            
        } catch (Exception $e) {
            $this->log('Erreur paiement Wave: ' . $e->getMessage(), 'error');
            
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
     * Appel à l'API Wave
     */
    private function call_wave_api($endpoint, $data)
    {
        $url = $this->get_api_url() . $endpoint;
        
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->api_key,
            'X-Secret-Key' => $this->secret_key,
            'User-Agent' => 'WooCommerce-Wave-Gateway/' . MMG_VERSION
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
     * URL de callback pour Wave
     */
    private function get_callback_url()
    {
        return add_query_arg('wc-api', strtolower(get_class($this)), home_url('/'));
    }
    
    /**
     * Gestion des webhooks Wave
     */
    public function handle_webhook()
    {
        $this->log('Webhook Wave reçu');
        
        try {
            // Récupération des données POST
            $raw_body = file_get_contents('php://input');
            $data = json_decode($raw_body, true);
            
            if (!$data) {
                throw new Exception('Données webhook invalides');
            }
            
            $this->log('Données webhook: ' . $raw_body);
            
            // Vérification de la signature (si Wave fournit une signature)
            if (!$this->verify_webhook_signature($raw_body, $_SERVER['HTTP_X_WAVE_SIGNATURE'] ?? '')) {
                throw new Exception('Signature webhook invalide');
            }
            
            $transaction_id = $data['transaction_id'] ?? '';
            $status = $data['status'] ?? '';
            $order_id = $data['order_id'] ?? '';
            
            if (empty($order_id)) {
                throw new Exception('ID de commande manquant');
            }
            
            $order = wc_get_order($order_id);
            if (!$order) {
                throw new Exception('Commande non trouvée: ' . $order_id);
            }
            
            // Traitement selon le statut
            switch ($status) {
                case 'completed':
                case 'success':
                    $this->handle_successful_payment($order, $data);
                    break;
                    
                case 'failed':
                case 'cancelled':
                    $this->handle_failed_payment($order, $data);
                    break;
                    
                case 'pending':
                    $this->handle_pending_payment($order, $data);
                    break;
                    
                default:
                    $this->log('Statut webhook inconnu: ' . $status, 'warning');
            }
            
            // Réponse de succès
            http_response_code(200);
            echo 'OK';
            exit;
            
        } catch (Exception $e) {
            $this->log('Erreur webhook Wave: ' . $e->getMessage(), 'error');
            http_response_code(400);
            echo 'Error: ' . $e->getMessage();
            exit;
        }
    }
    
    /**
     * Vérification de la signature du webhook
     */
    private function verify_webhook_signature($payload, $signature)
    {
        if (empty($signature)) {
            return false;
        }
        
        // Calcul de la signature attendue
        $expected_signature = hash_hmac('sha256', $payload, $this->secret_key);
        
        return hash_equals($expected_signature, $signature);
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
        $amount = $data['amount'] ?? 0;
        
        // Vérification du montant
        if ($amount != $this->format_amount($order->get_total())) {
            $this->log('Montant incorrect. Attendu: ' . $order->get_total() . ', Reçu: ' . $amount, 'error');
            return;
        }
        
        // Marquer comme payé
        $order->payment_complete($transaction_id);
        
        // Ajouter une note
        $order->add_order_note(sprintf(
            __('Paiement Wave confirmé. ID de transaction: %s', 'mobile-money-gateway'),
            $transaction_id
        ));
        
        // Mettre à jour les métadonnées
        $order->update_meta_data('_wave_payment_completed', current_time('mysql'));
        $order->save();
        
        $this->log('Paiement Wave confirmé pour commande #' . $order->get_id());
    }
    
    /**
     * Traitement d'un paiement échoué
     */
    private function handle_failed_payment($order, $data)
    {
        $reason = $data['failure_reason'] ?? __('Raison inconnue', 'mobile-money-gateway');
        
        $order->update_status('failed', sprintf(
            __('Paiement Wave échoué: %s', 'mobile-money-gateway'),
            $reason
        ));
        
        $this->log('Paiement Wave échoué pour commande #' . $order->get_id() . ': ' . $reason);
    }
    
    /**
     * Traitement d'un paiement en attente
     */
    private function handle_pending_payment($order, $data)
    {
        $order->update_status('on-hold', __('Paiement Wave en attente de confirmation.', 'mobile-money-gateway'));
        
        $this->log('Paiement Wave en attente pour commande #' . $order->get_id());
    }
    
    /**
     * Vérification du statut de paiement
     */
    public function check_payment_status($transaction_id)
    {
        try {
            $response = $this->call_wave_api('payments/' . $transaction_id . '/status', array(), 'GET');
            
            if (is_wp_error($response)) {
                return false;
            }
            
            $response_body = wp_remote_retrieve_body($response);
            $data = json_decode($response_body, true);
            
            return $data['status'] ?? false;
            
        } catch (Exception $e) {
            $this->log('Erreur vérification statut: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Remboursement via Wave
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return new WP_Error('invalid_order', __('Commande non trouvée.', 'mobile-money-gateway'));
        }
        
        $transaction_id = $order->get_meta('_wave_transaction_id');
        
        if (empty($transaction_id)) {
            return new WP_Error('no_transaction', __('ID de transaction Wave non trouvé.', 'mobile-money-gateway'));
        }
        
        if (is_null($amount)) {
            $amount = $order->get_total();
        }
        
        try {
            $refund_data = array(
                'transaction_id' => $transaction_id,
                'amount' => $this->format_amount($amount),
                'reason' => $reason ?: __('Remboursement demandé', 'mobile-money-gateway')
            );
            
            $response = $this->call_wave_api('refunds', $refund_data);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $response_body = wp_remote_retrieve_body($response);
            $data = json_decode($response_body, true);
            
            if (isset($data['status']) && $data['status'] === 'success') {
                $order->add_order_note(sprintf(
                    __('Remboursement Wave de %s effectué. ID: %s', 'mobile-money-gateway'),
                    wc_price($amount),
                    $data['refund_id']
                ));
                
                return true;
            } else {
                return new WP_Error('refund_failed', $data['message'] ?? __('Échec du remboursement.', 'mobile-money-gateway'));
            }
            
        } catch (Exception $e) {
            return new WP_Error('refund_error', $e->getMessage());
        }
    }
}