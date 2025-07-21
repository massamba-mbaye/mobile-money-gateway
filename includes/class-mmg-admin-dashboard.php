<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe pour le tableau de bord administrateur Mobile Money
 */
class MMG_Admin_Dashboard
{
    /**
     * Instance singleton
     */
    private static $instance = null;
    
    /**
     * Obtenir l'instance singleton
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructeur
     */
    private function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_mmg_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_mmg_export_transactions', array($this, 'ajax_export_transactions'));
    }
    
    /**
     * Ajouter le menu d'administration
     */
    public function add_admin_menu()
    {
        // Page principale - Tableau de bord
        add_menu_page(
            __('Mobile Money', 'mobile-money-gateway'),
            __('Mobile Money', 'mobile-money-gateway'),
            'manage_woocommerce',
            'mmg-dashboard',
            array($this, 'dashboard_page'),
            'dashicons-smartphone',
            25
        );
        
        // Sous-page - Tableau de bord
        add_submenu_page(
            'mmg-dashboard',
            __('Tableau de bord', 'mobile-money-gateway'),
            __('Tableau de bord', 'mobile-money-gateway'),
            'manage_woocommerce',
            'mmg-dashboard',
            array($this, 'dashboard_page')
        );
        
        // Sous-page - Configuration API
        add_submenu_page(
            'mmg-dashboard',
            __('Configuration API', 'mobile-money-gateway'),
            __('Configuration API', 'mobile-money-gateway'),
            'manage_woocommerce',
            'mmg-api-config',
            array($this, 'api_config_page')
        );
        
        // Sous-page - Transactions
        add_submenu_page(
            'mmg-dashboard',
            __('Transactions', 'mobile-money-gateway'),
            __('Transactions', 'mobile-money-gateway'),
            'manage_woocommerce',
            'mmg-transactions',
            array($this, 'transactions_page')
        );
        
        // Sous-page - Rapports
        add_submenu_page(
            'mmg-dashboard',
            __('Rapports', 'mobile-money-gateway'),
            __('Rapports', 'mobile-money-gateway'),
            'manage_woocommerce',
            'mmg-reports',
            array($this, 'reports_page')
        );
    }
    
    /**
     * Charger les scripts et styles admin
     */
    public function enqueue_admin_scripts($hook)
    {
        if (strpos($hook, 'mmg-') === false) {
            return;
        }
        
        wp_enqueue_script(
            'mmg-admin-dashboard',
            MMG_PLUGIN_URL . 'assets/js/admin-dashboard.js',
            array('jquery', 'wp-api-fetch'),
            MMG_VERSION,
            true
        );
        
        wp_enqueue_style(
            'mmg-admin-dashboard',
            MMG_PLUGIN_URL . 'assets/css/admin-dashboard.css',
            array(),
            MMG_VERSION
        );
        
        wp_localize_script('mmg-admin-dashboard', 'mmg_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mmg_admin_nonce'),
            'strings' => array(
                'loading' => __('Chargement...', 'mobile-money-gateway'),
                'error' => __('Erreur lors du chargement des données', 'mobile-money-gateway'),
                'success' => __('Opération réussie', 'mobile-money-gateway'),
                'confirm_export' => __('Êtes-vous sûr de vouloir exporter ces données ?', 'mobile-money-gateway'),
            )
        ));
    }
    
    /**
     * Page du tableau de bord
     */
    public function dashboard_page()
    {
        $stats = $this->get_dashboard_stats();
        $recent_transactions = $this->get_recent_transactions(10);
        ?>
        <div class="wrap mmg-dashboard">
            <h1><?php _e('Tableau de bord Mobile Money', 'mobile-money-gateway'); ?></h1>
            
            <!-- Statistiques rapides -->
            <div class="mmg-stats-grid">
                <div class="mmg-stat-card">
                    <div class="mmg-stat-icon wave-icon">🌊</div>
                    <div class="mmg-stat-content">
                        <h3><?php echo esc_html($stats['wave']['total_amount']); ?> FCFA</h3>
                        <p><?php _e('Ventes Wave', 'mobile-money-gateway'); ?></p>
                        <small><?php echo esc_html($stats['wave']['total_orders']); ?> <?php _e('commandes', 'mobile-money-gateway'); ?></small>
                    </div>
                </div>
                
                <div class="mmg-stat-card">
                    <div class="mmg-stat-icon orange-icon">🧡</div>
                    <div class="mmg-stat-content">
                        <h3><?php echo esc_html($stats['orange']['total_amount']); ?> FCFA</h3>
                        <p><?php _e('Ventes Orange Money', 'mobile-money-gateway'); ?></p>
                        <small><?php echo esc_html($stats['orange']['total_orders']); ?> <?php _e('commandes', 'mobile-money-gateway'); ?></small>
                    </div>
                </div>
                
                <div class="mmg-stat-card">
                    <div class="mmg-stat-icon total-icon">💰</div>
                    <div class="mmg-stat-content">
                        <h3><?php echo esc_html($stats['total']['total_amount']); ?> FCFA</h3>
                        <p><?php _e('Total Mobile Money', 'mobile-money-gateway'); ?></p>
                        <small><?php echo esc_html($stats['total']['total_orders']); ?> <?php _e('commandes', 'mobile-money-gateway'); ?></small>
                    </div>
                </div>
                
                <div class="mmg-stat-card">
                    <div class="mmg-stat-icon success-icon">✅</div>
                    <div class="mmg-stat-content">
                        <h3><?php echo esc_html(number_format($stats['success_rate'], 1)); ?>%</h3>
                        <p><?php _e('Taux de succès', 'mobile-money-gateway'); ?></p>
                        <small><?php _e('ce mois', 'mobile-money-gateway'); ?></small>
                    </div>
                </div>
            </div>
            
            <!-- Graphiques -->
            <div class="mmg-charts-row">
                <div class="mmg-chart-container">
                    <h3><?php _e('Évolution des ventes (30 derniers jours)', 'mobile-money-gateway'); ?></h3>
                    <canvas id="mmg-sales-chart"></canvas>
                </div>
                
                <div class="mmg-chart-container">
                    <h3><?php _e('Répartition par passerelle', 'mobile-money-gateway'); ?></h3>
                    <canvas id="mmg-gateway-chart"></canvas>
                </div>
            </div>
            
            <!-- Transactions récentes -->
            <div class="mmg-recent-transactions">
                <h3><?php _e('Transactions récentes', 'mobile-money-gateway'); ?></h3>
                <div class="mmg-table-container">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Commande', 'mobile-money-gateway'); ?></th>
                                <th><?php _e('Passerelle', 'mobile-money-gateway'); ?></th>
                                <th><?php _e('Montant', 'mobile-money-gateway'); ?></th>
                                <th><?php _e('Statut', 'mobile-money-gateway'); ?></th>
                                <th><?php _e('Date', 'mobile-money-gateway'); ?></th>
                                <th><?php _e('Actions', 'mobile-money-gateway'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_transactions as $transaction) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $transaction['order_id'] . '&action=edit')); ?>">
                                        #<?php echo esc_html($transaction['order_id']); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="mmg-gateway-badge <?php echo esc_attr($transaction['gateway']); ?>">
                                        <?php echo esc_html($transaction['gateway_name']); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($transaction['amount']); ?> <?php echo esc_html($transaction['currency']); ?></td>
                                <td>
                                    <span class="mmg-status-badge <?php echo esc_attr($transaction['status']); ?>">
                                        <?php echo esc_html($transaction['status_name']); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($transaction['date']); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $transaction['order_id'] . '&action=edit')); ?>" class="button button-small">
                                        <?php _e('Voir', 'mobile-money-gateway'); ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <p class="mmg-view-all">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mmg-transactions')); ?>" class="button button-primary">
                        <?php _e('Voir toutes les transactions', 'mobile-money-gateway'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Page de configuration API
     */
    public function api_config_page()
    {
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['mmg_api_nonce'], 'mmg_api_config')) {
            $this->save_api_configuration();
        }
        
        $wave_settings = get_option('woocommerce_wave_gateway_settings', array());
        $orange_settings = get_option('woocommerce_orange_money_gateway_settings', array());
        ?>
        <div class="wrap mmg-api-config">
            <h1><?php _e('Configuration des API Mobile Money', 'mobile-money-gateway'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('mmg_api_config', 'mmg_api_nonce'); ?>
                
                <!-- Configuration Wave -->
                <div class="mmg-config-section">
                    <h2>🌊 <?php _e('Configuration Wave', 'mobile-money-gateway'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Activer Wave', 'mobile-money-gateway'); ?></th>
                            <td>
                                <input type="checkbox" name="wave_enabled" value="yes" <?php checked($wave_settings['enabled'] ?? 'no', 'yes'); ?>>
                                <label><?php _e('Activer les paiements Wave', 'mobile-money-gateway'); ?></label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Mode', 'mobile-money-gateway'); ?></th>
                            <td>
                                <select name="wave_mode">
                                    <option value="sandbox" <?php selected($wave_settings['sandbox_mode'] ?? 'yes', 'yes'); ?>><?php _e('Test (Sandbox)', 'mobile-money-gateway'); ?></option>
                                    <option value="live" <?php selected($wave_settings['sandbox_mode'] ?? 'yes', 'no'); ?>><?php _e('Production', 'mobile-money-gateway'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Clé API Wave', 'mobile-money-gateway'); ?></th>
                            <td>
                                <input type="text" name="wave_api_key" value="<?php echo esc_attr($wave_settings['api_key'] ?? ''); ?>" class="regular-text" placeholder="sk_live_...">
                                <p class="description"><?php _e('Votre clé API Wave obtenue depuis le portail développeur', 'mobile-money-gateway'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Clé Secrète Wave', 'mobile-money-gateway'); ?></th>
                            <td>
                                <input type="password" name="wave_secret_key" value="<?php echo esc_attr($wave_settings['secret_key'] ?? ''); ?>" class="regular-text">
                                <p class="description"><?php _e('Votre clé secrète Wave pour sécuriser les webhooks', 'mobile-money-gateway'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Test de connexion', 'mobile-money-gateway'); ?></th>
                            <td>
                                <button type="button" id="test-wave-connection" class="button button-secondary">
                                    <?php _e('Tester la connexion Wave', 'mobile-money-gateway'); ?>
                                </button>
                                <span id="wave-connection-result"></span>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Configuration Orange Money -->
                <div class="mmg-config-section">
                    <h2>🧡 <?php _e('Configuration Orange Money', 'mobile-money-gateway'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Activer Orange Money', 'mobile-money-gateway'); ?></th>
                            <td>
                                <input type="checkbox" name="orange_enabled" value="yes" <?php checked($orange_settings['enabled'] ?? 'no', 'yes'); ?>>
                                <label><?php _e('Activer les paiements Orange Money', 'mobile-money-gateway'); ?></label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Pays', 'mobile-money-gateway'); ?></th>
                            <td>
                                <select name="orange_country">
                                    <option value="SN" <?php selected($orange_settings['country_code'] ?? 'SN', 'SN'); ?>><?php _e('Sénégal', 'mobile-money-gateway'); ?></option>
                                    <option value="CI" <?php selected($orange_settings['country_code'] ?? 'SN', 'CI'); ?>><?php _e('Côte d\'Ivoire', 'mobile-money-gateway'); ?></option>
                                    <option value="ML" <?php selected($orange_settings['country_code'] ?? 'SN', 'ML'); ?>><?php _e('Mali', 'mobile-money-gateway'); ?></option>
                                    <option value="BF" <?php selected($orange_settings['country_code'] ?? 'SN', 'BF'); ?>><?php _e('Burkina Faso', 'mobile-money-gateway'); ?></option>
                                    <option value="NE" <?php selected($orange_settings['country_code'] ?? 'SN', 'NE'); ?>><?php _e('Niger', 'mobile-money-gateway'); ?></option>
                                    <option value="GN" <?php selected($orange_settings['country_code'] ?? 'SN', 'GN'); ?>><?php _e('Guinée', 'mobile-money-gateway'); ?></option>
                                    <option value="CM" <?php selected($orange_settings['country_code'] ?? 'SN', 'CM'); ?>><?php _e('Cameroun', 'mobile-money-gateway'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Mode', 'mobile-money-gateway'); ?></th>
                            <td>
                                <select name="orange_mode">
                                    <option value="sandbox" <?php selected($orange_settings['sandbox_mode'] ?? 'yes', 'yes'); ?>><?php _e('Test (Sandbox)', 'mobile-money-gateway'); ?></option>
                                    <option value="live" <?php selected($orange_settings['sandbox_mode'] ?? 'yes', 'no'); ?>><?php _e('Production', 'mobile-money-gateway'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Clé API Orange Money', 'mobile-money-gateway'); ?></th>
                            <td>
                                <input type="text" name="orange_api_key" value="<?php echo esc_attr($orange_settings['api_key'] ?? ''); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Clé Secrète Orange Money', 'mobile-money-gateway'); ?></th>
                            <td>
                                <input type="password" name="orange_secret_key" value="<?php echo esc_attr($orange_settings['secret_key'] ?? ''); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('ID Marchand', 'mobile-money-gateway'); ?></th>
                            <td>
                                <input type="text" name="orange_merchant_id" value="<?php echo esc_attr($orange_settings['merchant_id'] ?? ''); ?>" class="regular-text">
                                <p class="description"><?php _e('Votre identifiant marchand Orange Money', 'mobile-money-gateway'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Test de connexion', 'mobile-money-gateway'); ?></th>
                            <td>
                                <button type="button" id="test-orange-connection" class="button button-secondary">
                                    <?php _e('Tester la connexion Orange Money', 'mobile-money-gateway'); ?>
                                </button>
                                <span id="orange-connection-result"></span>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- URLs Webhook -->
                <div class="mmg-config-section">
                    <h2>🔗 <?php _e('URLs de Webhook', 'mobile-money-gateway'); ?></h2>
                    <p><?php _e('Configurez ces URLs dans vos portails développeur :', 'mobile-money-gateway'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Webhook Wave', 'mobile-money-gateway'); ?></th>
                            <td>
                                <input type="text" readonly value="<?php echo esc_url(home_url('/?wc-api=wc_wave_gateway')); ?>" class="large-text">
                                <button type="button" class="button button-small copy-url" data-clipboard-text="<?php echo esc_url(home_url('/?wc-api=wc_wave_gateway')); ?>">
                                    <?php _e('Copier', 'mobile-money-gateway'); ?>
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Webhook Orange Money', 'mobile-money-gateway'); ?></th>
                            <td>
                                <input type="text" readonly value="<?php echo esc_url(home_url('/?wc-api=wc_orange_money_gateway')); ?>" class="large-text">
                                <button type="button" class="button button-small copy-url" data-clipboard-text="<?php echo esc_url(home_url('/?wc-api=wc_orange_money_gateway')); ?>">
                                    <?php _e('Copier', 'mobile-money-gateway'); ?>
                                </button>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button(__('Sauvegarder la configuration', 'mobile-money-gateway')); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Page des transactions
     */
    public function transactions_page()
    {
        $transactions = $this->get_all_transactions();
        ?>
        <div class="wrap mmg-transactions">
            <h1><?php _e('Transactions Mobile Money', 'mobile-money-gateway'); ?></h1>
            
            <!-- Filtres -->
            <div class="mmg-filters">
                <form method="get">
                    <input type="hidden" name="page" value="mmg-transactions">
                    
                    <select name="gateway">
                        <option value=""><?php _e('Toutes les passerelles', 'mobile-money-gateway'); ?></option>
                        <option value="wave_gateway" <?php selected($_GET['gateway'] ?? '', 'wave_gateway'); ?>><?php _e('Wave', 'mobile-money-gateway'); ?></option>
                        <option value="orange_money_gateway" <?php selected($_GET['gateway'] ?? '', 'orange_money_gateway'); ?>><?php _e('Orange Money', 'mobile-money-gateway'); ?></option>
                    </select>
                    
                    <select name="status">
                        <option value=""><?php _e('Tous les statuts', 'mobile-money-gateway'); ?></option>
                        <option value="completed" <?php selected($_GET['status'] ?? '', 'completed'); ?>><?php _e('Complété', 'mobile-money-gateway'); ?></option>
                        <option value="pending" <?php selected($_GET['status'] ?? '', 'pending'); ?>><?php _e('En attente', 'mobile-money-gateway'); ?></option>
                        <option value="failed" <?php selected($_GET['status'] ?? '', 'failed'); ?>><?php _e('Échoué', 'mobile-money-gateway'); ?></option>
                    </select>
                    
                    <input type="date" name="date_from" value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>">
                    <input type="date" name="date_to" value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>">
                    
                    <button type="submit" class="button"><?php _e('Filtrer', 'mobile-money-gateway'); ?></button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mmg-transactions')); ?>" class="button"><?php _e('Réinitialiser', 'mobile-money-gateway'); ?></a>
                </form>
            </div>
            
            <!-- Export -->
            <div class="mmg-export">
                <button type="button" id="export-transactions" class="button button-primary">
                    <?php _e('Exporter en CSV', 'mobile-money-gateway'); ?>
                </button>
            </div>
            
            <!-- Table des transactions -->
            <div class="mmg-table-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Commande', 'mobile-money-gateway'); ?></th>
                            <th><?php _e('Client', 'mobile-money-gateway'); ?></th>
                            <th><?php _e('Passerelle', 'mobile-money-gateway'); ?></th>
                            <th><?php _e('Téléphone', 'mobile-money-gateway'); ?></th>
                            <th><?php _e('Montant', 'mobile-money-gateway'); ?></th>
                            <th><?php _e('Statut', 'mobile-money-gateway'); ?></th>
                            <th><?php _e('Transaction ID', 'mobile-money-gateway'); ?></th>
                            <th><?php _e('Date', 'mobile-money-gateway'); ?></th>
                            <th><?php _e('Actions', 'mobile-money-gateway'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . $transaction['order_id'] . '&action=edit')); ?>">
                                    #<?php echo esc_html($transaction['order_id']); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($transaction['customer_name']); ?></td>
                            <td>
                                <span class="mmg-gateway-badge <?php echo esc_attr($transaction['gateway']); ?>">
                                    <?php echo esc_html($transaction['gateway_name']); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($transaction['phone_number']); ?></td>
                            <td><?php echo esc_html($transaction['amount']); ?> <?php echo esc_html($transaction['currency']); ?></td>
                            <td>
                                <span class="mmg-status-badge <?php echo esc_attr($transaction['status']); ?>">
                                    <?php echo esc_html($transaction['status_name']); ?>
                                </span>
                            </td>
                            <td>
                                <code><?php echo esc_html($transaction['transaction_id']); ?></code>
                            </td>
                            <td><?php echo esc_html($transaction['date']); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . $transaction['order_id'] . '&action=edit')); ?>" class="button button-small">
                                    <?php _e('Voir', 'mobile-money-gateway'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Page des rapports
     */
    public function reports_page()
    {
        ?>
        <div class="wrap mmg-reports">
            <h1><?php _e('Rapports Mobile Money', 'mobile-money-gateway'); ?></h1>
            
            <div class="mmg-reports-grid">
                <!-- Rapport des ventes par période -->
                <div class="mmg-report-section">
                    <h3><?php _e('Ventes par période', 'mobile-money-gateway'); ?></h3>
                    <canvas id="sales-period-chart"></canvas>
                </div>
                
                <!-- Rapport par passerelle -->
                <div class="mmg-report-section">
                    <h3><?php _e('Comparaison des passerelles', 'mobile-money-gateway'); ?></h3>
                    <canvas id="gateway-comparison-chart"></canvas>
                </div>
                
                <!-- Rapport des tendances -->
                <div class="mmg-report-section">
                    <h3><?php _e('Tendances des paiements', 'mobile-money-gateway'); ?></h3>
                    <canvas id="payment-trends-chart"></canvas>
                </div>
                
                <!-- Tableau de performance -->
                <div class="mmg-report-section">
                    <h3><?php _e('Performance des passerelles', 'mobile-money-gateway'); ?></h3>
                    <div id="performance-table"></div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Sauvegarder la configuration API
     */
    private function save_api_configuration()
    {
        // Configuration Wave
        $wave_settings = get_option('woocommerce_wave_gateway_settings', array());
        $wave_settings['enabled'] = isset($_POST['wave_enabled']) ? 'yes' : 'no';
        $wave_settings['sandbox_mode'] = $_POST['wave_mode'] === 'sandbox' ? 'yes' : 'no';
        $wave_settings['api_key'] = sanitize_text_field($_POST['wave_api_key']);
        $wave_settings['secret_key'] = sanitize_text_field($_POST['wave_secret_key']);
        update_option('woocommerce_wave_gateway_settings', $wave_settings);
        
        // Configuration Orange Money
        $orange_settings = get_option('woocommerce_orange_money_gateway_settings', array());
        $orange_settings['enabled'] = isset($_POST['orange_enabled']) ? 'yes' : 'no';
        $orange_settings['sandbox_mode'] = $_POST['orange_mode'] === 'sandbox' ? 'yes' : 'no';
        $orange_settings['country_code'] = sanitize_text_field($_POST['orange_country']);
        $orange_settings['api_key'] = sanitize_text_field($_POST['orange_api_key']);
        $orange_settings['secret_key'] = sanitize_text_field($_POST['orange_secret_key']);
        $orange_settings['merchant_id'] = sanitize_text_field($_POST['orange_merchant_id']);
        update_option('woocommerce_orange_money_gateway_settings', $orange_settings);
        
        // Message de confirmation
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Configuration sauvegardée avec succès !', 'mobile-money-gateway') . '</p></div>';
        });
    }
    
    /**
     * Obtenir les statistiques du tableau de bord
     */
    private function get_dashboard_stats()
    {
        global $wpdb;
        
        $current_month = date('Y-m-01');
        $next_month = date('Y-m-01', strtotime('+1 month'));
        
        // Statistiques Wave
        $wave_stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(meta_value), 0) as total_amount
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status = 'wc-completed'
            AND pm.meta_key = '_payment_method'
            AND pm.meta_value = 'wave_gateway'
            AND p.post_date >= %s
            AND p.post_date < %s
        ", $current_month, $next_month), ARRAY_A);
        
        // Statistiques Orange Money
        $orange_stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(meta_value), 0) as total_amount
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status = 'wc-completed'
            AND pm.meta_key = '_payment_method'
            AND pm.meta_value = 'orange_money_gateway'
            AND p.post_date >= %s
            AND p.post_date < %s
        ", $current_month, $next_month), ARRAY_A);
        
        // Calcul du taux de succès
        $total_attempts = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND pm.meta_key = '_payment_method'
            AND pm.meta_value IN ('wave_gateway', 'orange_money_gateway')
            AND p.post_date >= %s
            AND p.post_date < %s
        ", $current_month, $next_month));
        
        $successful_payments = intval($wave_stats['total_orders']) + intval($orange_stats['total_orders']);
        $success_rate = $total_attempts > 0 ? ($successful_payments / $total_attempts) * 100 : 0;
        
        return array(
            'wave' => array(
                'total_orders' => intval($wave_stats['total_orders']),
                'total_amount' => number_format(intval($wave_stats['total_amount']))
            ),
            'orange' => array(
                'total_orders' => intval($orange_stats['total_orders']),
                'total_amount' => number_format(intval($orange_stats['total_amount']))
            ),
            'total' => array(
                'total_orders' => intval($wave_stats['total_orders']) + intval($orange_stats['total_orders']),
                'total_amount' => number_format(intval($wave_stats['total_amount']) + intval($orange_stats['total_amount']))
            ),
            'success_rate' => $success_rate
        );
    }
    
    /**
     * Obtenir les transactions récentes
     */
    private function get_recent_transactions($limit = 10)
    {
        global $wpdb;
        
        $transactions = $wpdb->get_results($wpdb->prepare("
            SELECT 
                p.ID as order_id,
                p.post_date as date,
                p.post_status as status,
                pm1.meta_value as payment_method,
                pm2.meta_value as order_total,
                pm3.meta_value as billing_first_name,
                pm4.meta_value as billing_last_name,
                pm5.meta_value as transaction_id
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON (p.ID = pm1.post_id AND pm1.meta_key = '_payment_method')
            LEFT JOIN {$wpdb->postmeta} pm2 ON (p.ID = pm2.post_id AND pm2.meta_key = '_order_total')
            LEFT JOIN {$wpdb->postmeta} pm3 ON (p.ID = pm3.post_id AND pm3.meta_key = '_billing_first_name')
            LEFT JOIN {$wpdb->postmeta} pm4 ON (p.ID = pm4.post_id AND pm4.meta_key = '_billing_last_name')
            LEFT JOIN {$wpdb->postmeta} pm5 ON (p.ID = pm5.post_id AND pm5.meta_key LIKE '%%transaction_id')
            WHERE p.post_type = 'shop_order'
            AND pm1.meta_value IN ('wave_gateway', 'orange_money_gateway')
            ORDER BY p.post_date DESC
            LIMIT %d
        ", $limit), ARRAY_A);
        
        $formatted_transactions = array();
        foreach ($transactions as $transaction) {
            $gateway_name = $transaction['payment_method'] === 'wave_gateway' ? 'Wave' : 'Orange Money';
            $status_name = $this->get_status_name($transaction['status']);
            
            $formatted_transactions[] = array(
                'order_id' => $transaction['order_id'],
                'gateway' => $transaction['payment_method'],
                'gateway_name' => $gateway_name,
                'amount' => number_format(floatval($transaction['order_total'])),
                'currency' => get_woocommerce_currency(),
                'status' => str_replace('wc-', '', $transaction['status']),
                'status_name' => $status_name,
                'customer_name' => trim($transaction['billing_first_name'] . ' ' . $transaction['billing_last_name']),
                'date' => date_i18n('d/m/Y H:i', strtotime($transaction['date'])),
                'transaction_id' => $transaction['transaction_id'] ?: '-'
            );
        }
        
        return $formatted_transactions;
    }
    
    /**
     * Obtenir toutes les transactions avec filtres
     */
    private function get_all_transactions()
    {
        global $wpdb;
        
        $where_clauses = array("p.post_type = 'shop_order'");
        $where_clauses[] = "pm1.meta_value IN ('wave_gateway', 'orange_money_gateway')";
        
        // Filtres
        if (!empty($_GET['gateway'])) {
            $where_clauses[] = $wpdb->prepare("pm1.meta_value = %s", sanitize_text_field($_GET['gateway']));
        }
        
        if (!empty($_GET['status'])) {
            $status = 'wc-' . sanitize_text_field($_GET['status']);
            $where_clauses[] = $wpdb->prepare("p.post_status = %s", $status);
        }
        
        if (!empty($_GET['date_from'])) {
            $where_clauses[] = $wpdb->prepare("DATE(p.post_date) >= %s", sanitize_text_field($_GET['date_from']));
        }
        
        if (!empty($_GET['date_to'])) {
            $where_clauses[] = $wpdb->prepare("DATE(p.post_date) <= %s", sanitize_text_field($_GET['date_to']));
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $transactions = $wpdb->get_results("
            SELECT 
                p.ID as order_id,
                p.post_date as date,
                p.post_status as status,
                pm1.meta_value as payment_method,
                pm2.meta_value as order_total,
                pm3.meta_value as billing_first_name,
                pm4.meta_value as billing_last_name,
                pm5.meta_value as billing_phone,
                pm6.meta_value as transaction_id
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON (p.ID = pm1.post_id AND pm1.meta_key = '_payment_method')
            LEFT JOIN {$wpdb->postmeta} pm2 ON (p.ID = pm2.post_id AND pm2.meta_key = '_order_total')
            LEFT JOIN {$wpdb->postmeta} pm3 ON (p.ID = pm3.post_id AND pm3.meta_key = '_billing_first_name')
            LEFT JOIN {$wpdb->postmeta} pm4 ON (p.ID = pm4.post_id AND pm4.meta_key = '_billing_last_name')
            LEFT JOIN {$wpdb->postmeta} pm5 ON (p.ID = pm5.post_id AND pm5.meta_key = '_billing_phone')
            LEFT JOIN {$wpdb->postmeta} pm6 ON (p.ID = pm6.post_id AND pm6.meta_key LIKE '%%transaction_id')
            WHERE {$where_sql}
            ORDER BY p.post_date DESC
            LIMIT 100
        ", ARRAY_A);
        
        $formatted_transactions = array();
        foreach ($transactions as $transaction) {
            $gateway_name = $transaction['payment_method'] === 'wave_gateway' ? 'Wave' : 'Orange Money';
            $status_name = $this->get_status_name($transaction['status']);
            
            $formatted_transactions[] = array(
                'order_id' => $transaction['order_id'],
                'gateway' => $transaction['payment_method'],
                'gateway_name' => $gateway_name,
                'amount' => number_format(floatval($transaction['order_total'])),
                'currency' => get_woocommerce_currency(),
                'status' => str_replace('wc-', '', $transaction['status']),
                'status_name' => $status_name,
                'customer_name' => trim($transaction['billing_first_name'] . ' ' . $transaction['billing_last_name']),
                'phone_number' => $transaction['billing_phone'] ?: '-',
                'date' => date_i18n('d/m/Y H:i', strtotime($transaction['date'])),
                'transaction_id' => $transaction['transaction_id'] ?: '-'
            );
        }
        
        return $formatted_transactions;
    }
    
    /**
     * Obtenir le nom du statut
     */
    private function get_status_name($status)
    {
        $status_names = array(
            'wc-pending' => __('En attente', 'mobile-money-gateway'),
            'wc-processing' => __('En cours', 'mobile-money-gateway'),
            'wc-on-hold' => __('En attente', 'mobile-money-gateway'),
            'wc-completed' => __('Complété', 'mobile-money-gateway'),
            'wc-cancelled' => __('Annulé', 'mobile-money-gateway'),
            'wc-refunded' => __('Remboursé', 'mobile-money-gateway'),
            'wc-failed' => __('Échoué', 'mobile-money-gateway')
        );
        
        return $status_names[$status] ?? ucfirst(str_replace('wc-', '', $status));
    }
    
    /**
     * AJAX: Obtenir les statistiques
     */
    public function ajax_get_stats()
    {
        check_ajax_referer('mmg_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Accès refusé.', 'mobile-money-gateway'));
        }
        
        $period = sanitize_text_field($_POST['period'] ?? '30');
        $stats = $this->get_stats_for_period($period);
        
        wp_send_json_success($stats);
    }
    
    /**
     * AJAX: Exporter les transactions
     */
    public function ajax_export_transactions()
    {
        check_ajax_referer('mmg_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Accès refusé.', 'mobile-money-gateway'));
        }
        
        $transactions = $this->get_all_transactions();
        
        // Générer le CSV
        $filename = 'transactions-mobile-money-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // En-têtes CSV
        fputcsv($output, array(
            'Commande',
            'Client',
            'Passerelle',
            'Téléphone',
            'Montant',
            'Devise',
            'Statut',
            'Transaction ID',
            'Date'
        ));
        
        // Données
        foreach ($transactions as $transaction) {
            fputcsv($output, array(
                $transaction['order_id'],
                $transaction['customer_name'],
                $transaction['gateway_name'],
                $transaction['phone_number'],
                $transaction['amount'],
                $transaction['currency'],
                $transaction['status_name'],
                $transaction['transaction_id'],
                $transaction['date']
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Obtenir les statistiques pour une période
     */
    private function get_stats_for_period($days = 30)
    {
        global $wpdb;
        
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        $end_date = date('Y-m-d');
        
        $daily_stats = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(p.post_date) as date,
                pm1.meta_value as payment_method,
                COUNT(*) as orders,
                SUM(CAST(pm2.meta_value AS DECIMAL(10,2))) as total
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON (p.ID = pm1.post_id AND pm1.meta_key = '_payment_method')
            INNER JOIN {$wpdb->postmeta} pm2 ON (p.ID = pm2.post_id AND pm2.meta_key = '_order_total')
            WHERE p.post_type = 'shop_order'
            AND p.post_status = 'wc-completed'
            AND pm1.meta_value IN ('wave_gateway', 'orange_money_gateway')
            AND DATE(p.post_date) BETWEEN %s AND %s
            GROUP BY DATE(p.post_date), pm1.meta_value
            ORDER BY DATE(p.post_date)
        ", $start_date, $end_date), ARRAY_A);
        
        // Formatter pour les graphiques
        $formatted_stats = array();
        foreach ($daily_stats as $stat) {
            $date = $stat['date'];
            $gateway = $stat['payment_method'] === 'wave_gateway' ? 'Wave' : 'Orange Money';
            
            if (!isset($formatted_stats[$date])) {
                $formatted_stats[$date] = array('date' => $date, 'Wave' => 0, 'Orange Money' => 0);
            }
            
            $formatted_stats[$date][$gateway] = floatval($stat['total']);
        }
        
        return array_values($formatted_stats);
    }
}