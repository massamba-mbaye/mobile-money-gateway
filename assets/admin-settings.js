jQuery(document).ready(function($) {
    
    // Gestion des paramètres Wave
    function toggleWaveSettings() {
        const $phoneField = $('#woocommerce_wave_gateway_phone_number_field');
        const $debugField = $('#woocommerce_wave_gateway_debug');
        
        if ($phoneField.is(':checked')) {
            $('.wave-phone-settings').show();
        } else {
            $('.wave-phone-settings').hide();
        }
    }
    
    // Gestion des paramètres Orange Money
    function toggleOrangeMoneySettings() {
        const $phoneField = $('#woocommerce_orange_money_gateway_phone_number_field');
        const $countrySelect = $('#woocommerce_orange_money_gateway_country_code');
        
        if ($phoneField.is(':checked')) {
            $('.orange-money-phone-settings').show();
        } else {
            $('.orange-money-phone-settings').hide();
        }
        
        // Mise à jour du placeholder selon le pays
        updatePhonePlaceholder($countrySelect.val());
    }
    
    // Mise à jour du placeholder du téléphone selon le pays
    function updatePhonePlaceholder(countryCode) {
        const placeholders = {
            'SN': '+221 XX XXX XX XX',
            'CI': '+225 XX XX XX XX XX',
            'ML': '+223 XX XX XX XX',
            'BF': '+226 XX XX XX XX',
            'NE': '+227 XX XX XX XX',
            'GN': '+224 XX XX XX XX',
            'CM': '+237 XX XX XX XX'
        };
        
        const placeholder = placeholders[countryCode] || '+XXX XX XXX XX XX';
        $('#phone-example').text('Exemple: ' + placeholder);
    }
    
    // Test de connexion API
    function testApiConnection(gateway) {
        const $button = $('#test-' + gateway + '-connection');
        const $result = $('#' + gateway + '-connection-result');
        
        $button.prop('disabled', true).text('Test en cours...');
        $result.removeClass('success error').text('');
        
        const data = {
            action: 'mmg_test_api_connection',
            gateway: gateway,
            nonce: mmg_admin.nonce
        };
        
        // Récupération des paramètres selon la passerelle
        if (gateway === 'wave') {
            data.api_key = $('#woocommerce_wave_gateway_api_key').val();
            data.secret_key = $('#woocommerce_wave_gateway_secret_key').val();
            data.sandbox = $('#woocommerce_wave_gateway_sandbox_mode').is(':checked');
        } else if (gateway === 'orange_money') {
            data.api_key = $('#woocommerce_orange_money_gateway_api_key').val();
            data.secret_key = $('#woocommerce_orange_money_gateway_secret_key').val();
            data.merchant_id = $('#woocommerce_orange_money_gateway_merchant_id').val();
            data.sandbox = $('#woocommerce_orange_money_gateway_sandbox_mode').is(':checked');
        }
        
        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                $result.addClass('success').text('✓ Connexion réussie');
            } else {
                $result.addClass('error').text('✗ Erreur: ' + response.data);
            }
        }).fail(function() {
            $result.addClass('error').text('✗ Erreur de connexion');
        }).always(function() {
            $button.prop('disabled', false).text('Tester la connexion');
        });
    }
    
    // Validation en temps réel des clés API
    function validateApiKey($field, minLength = 10) {
        const value = $field.val();
        const $feedback = $field.next('.api-key-feedback');
        
        if (!$feedback.length) {
            $field.after('<span class="api-key-feedback"></span>');
        }
        
        if (value.length === 0) {
            $feedback.text('').removeClass('valid invalid');
        } else if (value.length < minLength) {
            $feedback.text('Clé trop courte').removeClass('valid').addClass('invalid');
        } else {
            $feedback.text('Format valide').removeClass('invalid').addClass('valid');
        }
    }
    
    // Initialisation
    toggleWaveSettings();
    toggleOrangeMoneySettings();
    
    // Event listeners pour Wave
    $('#woocommerce_wave_gateway_phone_number_field').on('change', toggleWaveSettings);
    
    // Event listeners pour Orange Money
    $('#woocommerce_orange_money_gateway_phone_number_field').on('change', toggleOrangeMoneySettings);
    $('#woocommerce_orange_money_gateway_country_code').on('change', function() {
        updatePhonePlaceholder($(this).val());
    });
    
    // Validation des clés API
    $('#woocommerce_wave_gateway_api_key, #woocommerce_orange_money_gateway_api_key').on('input', function() {
        validateApiKey($(this));
    });
    
    $('#woocommerce_wave_gateway_secret_key, #woocommerce_orange_money_gateway_secret_key').on('input', function() {
        validateApiKey($(this), 20);
    });
    
    // Boutons de test de connexion
    $(document).on('click', '#test-wave-connection', function(e) {
        e.preventDefault();
        testApiConnection('wave');
    });
    
    $(document).on('click', '#test-orange-money-connection', function(e) {
        e.preventDefault();
        testApiConnection('orange_money');
    });
    
    // Copie des URLs de webhook
    $(document).on('click', '.copy-webhook-url', function(e) {
        e.preventDefault();
        const $input = $(this).prev('input');
        $input.select();
        document.execCommand('copy');
        
        const $button = $(this);
        const originalText = $button.text();
        $button.text('Copié !');
        
        setTimeout(function() {
            $button.text(originalText);
        }, 2000);
    });
    
    // Affichage conditionnel des champs selon le mode sandbox
    function toggleSandboxFields() {
        $('.sandbox-only').toggle($('#woocommerce_wave_gateway_sandbox_mode').is(':checked'));
        $('.production-only').toggle(!$('#woocommerce_wave_gateway_sandbox_mode').is(':checked'));
    }
    
    $('#woocommerce_wave_gateway_sandbox_mode, #woocommerce_orange_money_gateway_sandbox_mode').on('change', toggleSandboxFields);
    toggleSandboxFields();
    
    // Ajout d'informations d'aide
    if ($('#woocommerce_wave_gateway_api_key').length) {
        addHelpText();
    }
    
    function addHelpText() {
        // Ajouter des liens d'aide
        const $waveSection = $('.woocommerce_wave_gateway');
        const $orangeSection = $('.woocommerce_orange_money_gateway');
        
        if ($waveSection.length) {
            $waveSection.prepend(`
                <div class="mmg-help-section">
                    <h4>🌊 Configuration Wave</h4>
                    <p>Pour obtenir vos clés API Wave, consultez la <a href="#" target="_blank">documentation Wave</a>.</p>
                    <div id="wave-webhook-info" style="background: #f9f9f9; padding: 10px; margin: 10px 0; border-left: 4px solid #0073aa;">
                        <strong>URL de webhook Wave:</strong><br>
                        <input type="text" readonly value="${window.location.origin}/?wc-api=wc_wave_gateway" style="width: 100%; margin-top: 5px;">
                        <button type="button" class="button copy-webhook-url">Copier</button>
                    </div>
                </div>
            `);
        }
        
        if ($orangeSection.length) {
            $orangeSection.prepend(`
                <div class="mmg-help-section">
                    <h4>🧡 Configuration Orange Money</h4>
                    <p>Pour obtenir vos clés API Orange Money, consultez la <a href="#" target="_blank">documentation Orange Money</a>.</p>
                    <div id="orange-webhook-info" style="background: #f9f9f9; padding: 10px; margin: 10px 0; border-left: 4px solid #ff6600;">
                        <strong>URL de webhook Orange Money:</strong><br>
                        <input type="text" readonly value="${window.location.origin}/?wc-api=wc_orange_money_gateway" style="width: 100%; margin-top: 5px;">
                        <button type="button" class="button copy-webhook-url">Copier</button>
                    </div>
                    <div id="phone-example" style="color: #666; font-style: italic; margin-top: 5px;"></div>
                </div>
            `);
        }
    }
    
    // Style pour les messages de feedback
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .api-key-feedback {
                margin-left: 10px;
                font-size: 12px;
            }
            .api-key-feedback.valid {
                color: #46b450;
            }
            .api-key-feedback.invalid {
                color: #dc3232;
            }
            #wave-connection-result,
            #orange-money-connection-result {
                margin-left: 10px;
                font-weight: bold;
            }
            #wave-connection-result.success,
            #orange-money-connection-result.success {
                color: #46b450;
            }
            #wave-connection-result.error,
            #orange-money-connection-result.error {
                color: #dc3232;
            }
            .mmg-help-section {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                padding: 15px;
                margin-bottom: 20px;
            }
            .mmg-help-section h4 {
                margin-top: 0;
                color: #1d2327;
            }
        `)
        .appendTo('head');
});