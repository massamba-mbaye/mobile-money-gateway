jQuery(document).ready(function($) {
    
    // Initialisation du tableau de bord
    const MMGAdminDashboard = {
        
        charts: {},
        
        init: function() {
            this.initCharts();
            this.bindEvents();
            this.loadDashboardData();
        },
        
        initCharts: function() {
            // Graphique des ventes
            const salesCtx = document.getElementById('mmg-sales-chart');
            if (salesCtx) {
                this.charts.sales = new Chart(salesCtx, {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: [{
                            label: 'Wave',
                            data: [],
                            borderColor: '#00a8e6',
                            backgroundColor: 'rgba(0, 168, 230, 0.1)',
                            tension: 0.4
                        }, {
                            label: 'Orange Money',
                            data: [],
                            borderColor: '#ff6600',
                            backgroundColor: 'rgba(255, 102, 0, 0.1)',
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            title: {
                                display: true,
                                text: 'Évolution des ventes (FCFA)'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return new Intl.NumberFormat('fr-FR').format(value) + ' FCFA';
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Graphique en camembert des passerelles
            const gatewayCtx = document.getElementById('mmg-gateway-chart');
            if (gatewayCtx) {
                this.charts.gateway = new Chart(gatewayCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Wave', 'Orange Money'],
                        datasets: [{
                            data: [0, 0],
                            backgroundColor: ['#00a8e6', '#ff6600'],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom',
                            },
                            title: {
                                display: true,
                                text: 'Répartition par montant'
                            }
                        }
                    }
                });
            }
        },
        
        bindEvents: function() {
            // Test de connexion Wave
            $('#test-wave-connection').on('click', function(e) {
                e.preventDefault();
                MMGAdminDashboard.testConnection('wave');
            });
            
            // Test de connexion Orange Money
            $('#test-orange-connection').on('click', function(e) {
                e.preventDefault();
                MMGAdminDashboard.testConnection('orange_money');
            });
            
            // Copie des URLs de webhook
            $('.copy-url').on('click', function(e) {
                e.preventDefault();
                const url = $(this).data('clipboard-text');
                MMGAdminDashboard.copyToClipboard(url, $(this));
            });
            
            // Export des transactions
            $('#export-transactions').on('click', function(e) {
                e.preventDefault();
                if (confirm(mmg_admin.strings.confirm_export)) {
                    MMGAdminDashboard.exportTransactions();
                }
            });
            
            // Actualisation des stats
            $(document).on('click', '.mmg-refresh-stats', function(e) {
                e.preventDefault();
                MMGAdminDashboard.loadDashboardData();
            });
        },
        
        loadDashboardData: function() {
            this.showLoader('.mmg-stats-grid');
            
            $.ajax({
                url: mmg_admin.ajax_url,
                method: 'POST',
                data: {
                    action: 'mmg_get_stats',
                    period: '30',
                    nonce: mmg_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MMGAdminDashboard.updateCharts(response.data);
                    } else {
                        MMGAdminDashboard.showError(mmg_admin.strings.error);
                    }
                },
                error: function() {
                    MMGAdminDashboard.showError(mmg_admin.strings.error);
                },
                complete: function() {
                    MMGAdminDashboard.hideLoader('.mmg-stats-grid');
                }
            });
        },
        
        updateCharts: function(data) {
            if (this.charts.sales && data.daily_stats) {
                const labels = data.daily_stats.map(item => {
                    const date = new Date(item.date);
                    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
                });
                
                const waveData = data.daily_stats.map(item => item.Wave || 0);
                const orangeData = data.daily_stats.map(item => item['Orange Money'] || 0);
                
                this.charts.sales.data.labels = labels;
                this.charts.sales.data.datasets[0].data = waveData;
                this.charts.sales.data.datasets[1].data = orangeData;
                this.charts.sales.update();
            }
            
            if (this.charts.gateway && data.gateway_totals) {
                this.charts.gateway.data.datasets[0].data = [
                    data.gateway_totals.wave || 0,
                    data.gateway_totals.orange || 0
                ];
                this.charts.gateway.update();
            }
        },
        
        testConnection: function(gateway) {
            const $button = $('#test-' + gateway + '-connection');
            const $result = $('#' + gateway + '-connection-result');
            
            $button.prop('disabled', true).text(mmg_admin.strings.loading);
            $result.removeClass('success error').text('');
            
            const data = {
                action: 'mmg_test_api_connection',
                gateway: gateway,
                nonce: mmg_admin.nonce
            };
            
            // Récupération des clés API selon la passerelle
            if (gateway === 'wave') {
                data.api_key = $('input[name="wave_api_key"]').val();
                data.secret_key = $('input[name="wave_secret_key"]').val();
                data.sandbox = $('select[name="wave_mode"]').val() === 'sandbox';
            } else if (gateway === 'orange_money') {
                data.api_key = $('input[name="orange_api_key"]').val();
                data.secret_key = $('input[name="orange_secret_key"]').val();
                data.merchant_id = $('input[name="orange_merchant_id"]').val();
                data.sandbox = $('select[name="orange_mode"]').val() === 'sandbox';
            }
            
            $.ajax({
                url: mmg_admin.ajax_url,
                method: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        $result.addClass('success').text('✓ Connexion réussie');
                        MMGAdminDashboard.showNotice(mmg_admin.strings.success, 'success');
                    } else {
                        $result.addClass('error').text('✗ Erreur: ' + response.data);
                        MMGAdminDashboard.showNotice('Erreur: ' + response.data, 'error');
                    }
                },
                error: function() {
                    $result.addClass('error').text('✗ Erreur de connexion');
                    MMGAdminDashboard.showNotice('Erreur de connexion', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Tester la connexion');
                }
            });
        },
        
        copyToClipboard: function(text, $button) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function() {
                    MMGAdminDashboard.showCopySuccess($button);
                }).catch(function() {
                    MMGAdminDashboard.fallbackCopyTextToClipboard(text, $button);
                });
            } else {
                MMGAdminDashboard.fallbackCopyTextToClipboard(text, $button);
            }
        },
        
        fallbackCopyTextToClipboard: function(text, $button) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.left = "-999999px";
            textArea.style.top = "-999999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                MMGAdminDashboard.showCopySuccess($button);
            } catch (err) {
                MMGAdminDashboard.showNotice('Erreur lors de la copie', 'error');
            }
            
            document.body.removeChild(textArea);
        },
        
        showCopySuccess: function($button) {
            const originalText = $button.text();
            $button.text('Copié !').addClass('copied');
            
            setTimeout(function() {
                $button.text(originalText).removeClass('copied');
            }, 2000);
        },
        
        exportTransactions: function() {
            const exportForm = $('<form>', {
                method: 'POST',
                action: mmg_admin.ajax_url,
                style: 'display: none;'
            });
            
            exportForm.append($('<input>', {
                type: 'hidden',
                name: 'action',
                value: 'mmg_export_transactions'
            }));
            
            exportForm.append($('<input>', {
                type: 'hidden',
                name: 'nonce',
                value: mmg_admin.nonce
            }));
            
            // Ajouter les filtres actuels
            const currentFilters = new URLSearchParams(window.location.search);
            currentFilters.forEach((value, key) => {
                if (key !== 'page') {
                    exportForm.append($('<input>', {
                        type: 'hidden',
                        name: key,
                        value: value
                    }));
                }
            });
            
            $('body').append(exportForm);
            exportForm.submit();
            exportForm.remove();
            
            this.showNotice('Export en cours...', 'info');
        },
        
        showLoader: function(selector) {
            $(selector).addClass('mmg-loading');
        },
        
        hideLoader: function(selector) {
            $(selector).removeClass('mmg-loading');
        },
        
        showNotice: function(message, type = 'info') {
            const notice = $('<div>', {
                class: 'notice notice-' + type + ' is-dismissible mmg-notice',
                html: '<p>' + message + '</p>'
            });
            
            $('.wrap h1').after(notice);
            
            // Auto-remove après 5 secondes
            setTimeout(function() {
                notice.fadeOut(function() {
                    notice.remove();
                });
            }, 5000);
        },
        
        showError: function(message) {
            this.showNotice(message, 'error');
        }
    };
    
    // Gestion des onglets (si nécessaire)
    const MMGTabs = {
        init: function() {
            $('.mmg-nav-tab').on('click', function(e) {
                e.preventDefault();
                const target = $(this).data('tab');
                
                $('.mmg-nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.mmg-tab-content').hide();
                $('#' + target).show();
            });
        }
    };
    
    // Validation des formulaires
    const MMGFormValidation = {
        init: function() {
            // Validation des clés API
            $('input[name*="api_key"]').on('input', function() {
                MMGFormValidation.validateApiKey($(this));
            });
            
            $('input[name*="secret_key"]').on('input', function() {
                MMGFormValidation.validateSecretKey($(this));
            });
            
            // Validation du merchant ID
            $('input[name="orange_merchant_id"]').on('input', function() {
                MMGFormValidation.validateMerchantId($(this));
            });
        },
        
        validateApiKey: function($input) {
            const value = $input.val();
            const $feedback = $input.next('.api-key-feedback');
            
            if (!$feedback.length) {
                $input.after('<span class="api-key-feedback"></span>');
            }
            
            if (value.length === 0) {
                $feedback.text('').removeClass('valid invalid');
            } else if (value.length < 10) {
                $feedback.text('Clé trop courte').removeClass('valid').addClass('invalid');
            } else {
                $feedback.text('Format valide').removeClass('invalid').addClass('valid');
            }
        },
        
        validateSecretKey: function($input) {
            const value = $input.val();
            const $feedback = $input.next('.secret-key-feedback');
            
            if (!$feedback.length) {
                $input.after('<span class="secret-key-feedback"></span>');
            }
            
            if (value.length === 0) {
                $feedback.text('').removeClass('valid invalid');
            } else if (value.length < 20) {
                $feedback.text('Clé secrète trop courte').removeClass('valid').addClass('invalid');
            } else {
                $feedback.text('Format valide').removeClass('invalid').addClass('valid');
            }
        },
        
        validateMerchantId: function($input) {
            const value = $input.val();
            const $feedback = $input.next('.merchant-id-feedback');
            
            if (!$feedback.length) {
                $input.after('<span class="merchant-id-feedback"></span>');
            }
            
            if (value.length === 0) {
                $feedback.text('').removeClass('valid invalid');
            } else if (!/^[A-Z0-9_-]+$/i.test(value)) {
                $feedback.text('Format invalide').removeClass('valid').addClass('invalid');
            } else {
                $feedback.text('Format valide').removeClass('invalid').addClass('valid');
            }
        }
    };
    
    // Gestion des tableaux avec tri et pagination
    const MMGDataTable = {
        init: function() {
            // Tri des colonnes
            $('.mmg-sortable').on('click', function() {
                const column = $(this).data('column');
                const direction = $(this).hasClass('asc') ? 'desc' : 'asc';
                
                $('.mmg-sortable').removeClass('asc desc');
                $(this).addClass(direction);
                
                MMGDataTable.sortTable(column, direction);
            });
            
            // Recherche en temps réel
            $('#mmg-search').on('input', function() {
                const searchTerm = $(this).val().toLowerCase();
                MMGDataTable.filterTable(searchTerm);
            });
        },
        
        sortTable: function(column, direction) {
            const $table = $('.mmg-data-table tbody');
            const rows = $table.find('tr').toArray();
            
            rows.sort(function(a, b) {
                const aVal = $(a).find('td').eq(column).text().trim();
                const bVal = $(b).find('td').eq(column).text().trim();
                
                // Tri numérique pour les montants
                if (column === 4) { // Colonne montant
                    const aNum = parseFloat(aVal.replace(/[^\d.-]/g, ''));
                    const bNum = parseFloat(bVal.replace(/[^\d.-]/g, ''));
                    return direction === 'asc' ? aNum - bNum : bNum - aNum;
                }
                
                // Tri alphabétique
                if (direction === 'asc') {
                    return aVal.localeCompare(bVal);
                } else {
                    return bVal.localeCompare(aVal);
                }
            });
            
            $table.empty().append(rows);
        },
        
        filterTable: function(searchTerm) {
            $('.mmg-data-table tbody tr').each(function() {
                const rowText = $(this).text().toLowerCase();
                if (rowText.includes(searchTerm)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }
    };
    
    // Widgets du tableau de bord
    const MMGWidgets = {
        init: function() {
            this.initCounters();
            this.initProgressBars();
            this.setupAutoRefresh();
        },
        
        initCounters: function() {
            $('.mmg-counter').each(function() {
                const $this = $(this);
                const target = parseInt($this.data('target'));
                const duration = 2000;
                const increment = target / (duration / 16);
                let current = 0;
                
                const timer = setInterval(function() {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    $this.text(Math.floor(current).toLocaleString('fr-FR'));
                }, 16);
            });
        },
        
        initProgressBars: function() {
            $('.mmg-progress-bar').each(function() {
                const $this = $(this);
                const percentage = $this.data('percentage');
                
                $this.css('width', '0%').animate({
                    width: percentage + '%'
                }, 1500);
            });
        },
        
        setupAutoRefresh: function() {
            // Actualisation automatique toutes les 5 minutes
            setInterval(function() {
                if ($('.mmg-dashboard').length) {
                    MMGAdminDashboard.loadDashboardData();
                }
            }, 300000); // 5 minutes
        }
    };
    
    // Gestion des graphiques avancés
    const MMGCharts = {
        colors: {
            wave: '#00a8e6',
            orange: '#ff6600',
            success: '#46b450',
            pending: '#ffb900',
            failed: '#dc3232'
        },
        
        initReportsCharts: function() {
            this.initSalesPeriodChart();
            this.initGatewayComparisonChart();
            this.initPaymentTrendsChart();
        },
        
        initSalesPeriodChart: function() {
            const ctx = document.getElementById('sales-period-chart');
            if (!ctx) return;
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
                    datasets: [{
                        label: 'Ventes par jour',
                        data: [12, 19, 3, 5, 2, 3, 9],
                        backgroundColor: this.colors.wave,
                        borderColor: this.colors.wave,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },
        
        initGatewayComparisonChart: function() {
            const ctx = document.getElementById('gateway-comparison-chart');
            if (!ctx) return;
            
            new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: ['Volume', 'Rapidité', 'Succès', 'Fiabilité', 'Satisfaction'],
                    datasets: [{
                        label: 'Wave',
                        data: [85, 90, 95, 88, 92],
                        borderColor: this.colors.wave,
                        backgroundColor: this.colors.wave + '20',
                        pointBackgroundColor: this.colors.wave
                    }, {
                        label: 'Orange Money',
                        data: [78, 85, 90, 85, 88],
                        borderColor: this.colors.orange,
                        backgroundColor: this.colors.orange + '20',
                        pointBackgroundColor: this.colors.orange
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        },
        
        initPaymentTrendsChart: function() {
            const ctx = document.getElementById('payment-trends-chart');
            if (!ctx) return;
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun'],
                    datasets: [{
                        label: 'Succès',
                        data: [95, 96, 94, 97, 95, 98],
                        borderColor: this.colors.success,
                        backgroundColor: this.colors.success + '20',
                        tension: 0.4
                    }, {
                        label: 'En attente',
                        data: [3, 2, 4, 2, 3, 1],
                        borderColor: this.colors.pending,
                        backgroundColor: this.colors.pending + '20',
                        tension: 0.4
                    }, {
                        label: 'Échecs',
                        data: [2, 2, 2, 1, 2, 1],
                        borderColor: this.colors.failed,
                        backgroundColor: this.colors.failed + '20',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            });
        }
    };
    
    // Notifications en temps réel
    const MMGNotifications = {
        init: function() {
            this.setupWebSocket();
            this.bindNotificationEvents();
        },
        
        setupWebSocket: function() {
            // Implementation future pour les notifications en temps réel
            // via WebSocket ou Server-Sent Events
        },
        
        bindNotificationEvents: function() {
            // Marquer les notifications comme lues
            $(document).on('click', '.mmg-notification', function() {
                $(this).removeClass('unread');
            });
            
            // Effacer toutes les notifications
            $('#clear-all-notifications').on('click', function() {
                $('.mmg-notification').fadeOut();
            });
        },
        
        showNotification: function(title, message, type = 'info') {
            const notification = $(`
                <div class="mmg-notification ${type} unread">
                    <div class="mmg-notification-title">${title}</div>
                    <div class="mmg-notification-message">${message}</div>
                    <div class="mmg-notification-time">${new Date().toLocaleTimeString('fr-FR')}</div>
                </div>
            `);
            
            $('#mmg-notifications-container').prepend(notification);
            
            // Auto-remove après 10 secondes
            setTimeout(function() {
                notification.fadeOut();
            }, 10000);
        }
    };
    
    // Initialisation de tous les modules
    if ($('.mmg-dashboard').length) {
        MMGAdminDashboard.init();
        MMGWidgets.init();
    }
    
    if ($('.mmg-api-config').length) {
        MMGFormValidation.init();
    }
    
    if ($('.mmg-transactions').length) {
        MMGDataTable.init();
    }
    
    if ($('.mmg-reports').length) {
        MMGCharts.initReportsCharts();
    }
    
    // Modules globaux
    MMGTabs.init();
    MMGNotifications.init();
    
    // Styles CSS dynamiques
    if (!$('#mmg-dynamic-styles').length) {
        $('<style id="mmg-dynamic-styles">')
            .html(`
                .mmg-loading {
                    position: relative;
                    opacity: 0.6;
                }
                
                .mmg-loading::after {
                    content: '';
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    width: 20px;
                    height: 20px;
                    margin: -10px 0 0 -10px;
                    border: 2px solid #f3f3f3;
                    border-top: 2px solid #0073aa;
                    border-radius: 50%;
                    animation: mmg-spin 1s linear infinite;
                }
                
                @keyframes mmg-spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                
                .api-key-feedback,
                .secret-key-feedback,
                .merchant-id-feedback {
                    display: block;
                    margin-top: 5px;
                    font-size: 12px;
                }
                
                .api-key-feedback.valid,
                .secret-key-feedback.valid,
                .merchant-id-feedback.valid {
                    color: #46b450;
                }
                
                .api-key-feedback.invalid,
                .secret-key-feedback.invalid,
                .merchant-id-feedback.invalid {
                    color: #dc3232;
                }
                
                .copy-url.copied {
                    background-color: #46b450;
                    color: white;
                }
                
                .mmg-notification {
                    padding: 15px;
                    margin-bottom: 10px;
                    border-left: 4px solid #0073aa;
                    background: white;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                    border-radius: 3px;
                }
                
                .mmg-notification.unread {
                    border-left-color: #dc3232;
                    background: #fff2f2;
                }
                
                .mmg-notification.success {
                    border-left-color: #46b450;
                }
                
                .mmg-notification.warning {
                    border-left-color: #ffb900;
                }
                
                .mmg-notification.error {
                    border-left-color: #dc3232;
                }
            `)
            .appendTo('head');
    }
    
    // Logs de débogage
    if (window.console && window.console.log) {
        console.log('🌊 Mobile Money Gateway - Dashboard initialisé');
    }
});