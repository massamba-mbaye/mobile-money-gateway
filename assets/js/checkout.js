jQuery(document).ready(function($) {
    
    // Gestion du formulaire de checkout pour les passerelles mobile money
    const MobileMoneyCheckout = {
        
        init: function() {
            this.bindEvents();
            this.setupPhoneValidation();
        },
        
        bindEvents: function() {
            // Validation en temps réel des numéros de téléphone
            $(document).on('input blur', 'input[name*="_phone_number"]', this.validatePhoneNumber);
            
            // Gestion du changement de méthode de paiement
            $('body').on('updated_checkout', this.handleCheckoutUpdate);
            
            // Soumission du formulaire
            $(document).on('checkout_place_order_wave_gateway', this.validateWaveForm);
            $(document).on('checkout_place_order_orange_money_gateway', this.validateOrangeMoneyForm);
        },
        
        setupPhoneValidation: function() {
            // Ajouter des patterns de validation selon la passerelle
            const patterns = {
                wave: /^(\+221|221)?[73][0-9]{7}$/,
                orange_sn: /^(\+221|221)?[73][0-9]{7}$/,
                orange_ci: /^(\+225|225)?[0-9]{8,10}$/,
                orange_ml: /^(\+223|223)?[0-9]{8}$/,
                orange_bf: /^(\+226|226)?[0-9]{8}$/
            };
            
            this.validationPatterns = patterns;
        },
        
        validatePhoneNumber: function() {
            const $input = $(this);
            const value = $input.val().replace(/[\s\-()]+/g, '');
            const gatewayId = $input.closest('.payment_method').attr('id');
            
            let isValid = false;
            let errorMessage = '';
            
            if (!value) {
                isValid = false;
                errorMessage = 'Le numéro de téléphone est requis';
            } else if (gatewayId && gatewayId.includes('wave')) {
                isValid = MobileMoneyCheckout.validationPatterns.wave.test(value);
                errorMessage = isValid ? '' : 'Format Wave invalide (+221XXXXXXXX)';
            } else if (gatewayId && gatewayId.includes('orange')) {
                // Détecter le pays depuis les données ou utiliser Sénégal par défaut
                isValid = MobileMoneyCheckout.validationPatterns.orange_sn.test(value);
                errorMessage = isValid ? '' : 'Format Orange Money invalide';
            }
            
            MobileMoneyCheckout.showValidationFeedback($input, isValid, errorMessage);
            
            return isValid;
        },
        
        showValidationFeedback: function($input, isValid, message) {
            const $feedback = $input.siblings('.phone-validation-feedback');
            
            if (!$feedback.length && message) {
                $input.after('<div class="phone-validation-feedback"></div>');
            }
            
            const $feedbackElement = $input.siblings('.phone-validation-feedback');
            
            $input.removeClass('valid invalid');
            $feedbackElement.removeClass('success error').text('');
            
            if (message) {
                $input.addClass(isValid ? 'valid' : 'invalid');
                $feedbackElement.addClass(isValid ? 'success' : 'error').text(message);
            }
        },
        
        validateWaveForm: function() {
            const $phoneInput = $('input[name="wave_gateway_phone_number"]');
            
            if ($phoneInput.length && !MobileMoneyCheckout.validatePhoneNumber.call($phoneInput[0])) {
                MobileMoneyCheckout.scrollToError($phoneInput);
                return false;
            }
            
            return true;
        },
        
        validateOrangeMoneyForm: function() {
            const $phoneInput = $('input[name="orange_money_gateway_phone_number"]');
            
            if ($phoneInput.length && !MobileMoneyCheckout.validatePhoneNumber.call($phoneInput[0])) {
                MobileMoneyCheckout.scrollToError($phoneInput);
                return false;
            }
            
            return true;
        },
        
        scrollToError: function($element) {
            $('html, body').animate({
                scrollTop: $element.offset().top - 100
            }, 500);
            $element.focus();
        },
        
        handleCheckoutUpdate: function() {
            // Réinitialiser la validation lors de la mise à jour du checkout
            $('.phone-validation-feedback').remove();
            $('input[name*="_phone_number"]').removeClass('valid invalid');
        },
        
        formatPhoneNumber: function(value, format) {
            // Fonction utilitaire pour formater les numéros
            const cleaned = value.replace(/\D/g, '');
            
            switch (format) {
                case 'senegal':
                    if (cleaned.length >= 9) {
                        const number = cleaned.startsWith('221') ? cleaned.slice(3) : cleaned;
                        return '+221 ' + number.replace(/(\d{2})(\d{3})(\d{2})(\d{2})/, '$1 $2 $3 $4');
                    }
                    break;
                case 'ivory_coast':
                    if (cleaned.length >= 8) {
                        const number = cleaned.startsWith('225') ? cleaned.slice(3) : cleaned;
                        return '+225 ' + number.replace(/(\d{2})(\d{2})(\d{2})(\d{2})/, '$1 $2 $3 $4');
                    }
                    break;
                default:
                    return value;
            }
            
            return value;
        },
        
        // Fonction pour afficher les icônes des passerelles
        addPaymentIcons: function() {
            $('.payment_method_wave_gateway label').prepend('<img src="' + mmg_params.wave_icon + '" alt="Wave" style="width: 24px; height: 24px; margin-right: 8px; vertical-align: middle;">');
            $('.payment_method_orange_money_gateway label').prepend('<img src="' + mmg_params.orange_icon + '" alt="Orange Money" style="width: 24px; height: 24px; margin-right: 8px; vertical-align: middle;">');
        },
        
        // Gestion des erreurs de paiement
        handlePaymentError: function(errorMessage) {
            $('.woocommerce-error, .woocommerce-message').remove();
            
            const errorHtml = '<div class="woocommerce-error" role="alert">' + errorMessage + '</div>';
            $('.woocommerce-notices-wrapper').first().html(errorHtml);
            
            $('html, body').animate({
                scrollTop: $('.woocommerce-notices-wrapper').first().offset().top - 100
            }, 500);
        },
        
        // Mise à jour de l'interface selon la passerelle sélectionnée
        updatePaymentInterface: function() {
            const selectedGateway = $('input[name="payment_method"]:checked').val();
            
            // Masquer tous les champs de téléphone
            $('.mobile-money-phone-field').hide();
            
            // Afficher uniquement le champ correspondant à la passerelle sélectionnée
            if (selectedGateway === 'wave_gateway') {
                $('.wave-phone-field').show();
            } else if (selectedGateway === 'orange_money_gateway') {
                $('.orange-money-phone-field').show();
            }
        }
    };
    
    // Gestionnaire pour les messages de statut de paiement
    const PaymentStatusHandler = {
        
        init: function() {
            this.checkPaymentStatus();
            this.handleReturnFromGateway();
        },
        
        checkPaymentStatus: function() {
            // Vérifier s'il y a des paramètres de retour dans l'URL
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('payment_status');
            const orderId = urlParams.get('order_id');
            
            if (status && orderId) {
                this.displayPaymentResult(status, orderId);
            }
        },
        
        handleReturnFromGateway: function() {
            // Gérer le retour depuis les passerelles de paiement
            const urlParams = new URLSearchParams(window.location.search);
            
            if (urlParams.get('wave_return') || urlParams.get('orange_return')) {
                // Afficher un message de traitement
                this.showProcessingMessage();
            }
        },
        
        displayPaymentResult: function(status, orderId) {
            let message = '';
            let messageClass = '';
            
            switch (status) {
                case 'success':
                    message = 'Votre paiement a été traité avec succès.';
                    messageClass = 'woocommerce-message';
                    break;
                case 'pending':
                    message = 'Votre paiement est en cours de traitement.';
                    messageClass = 'woocommerce-info';
                    break;
                case 'failed':
                    message = 'Votre paiement a échoué. Veuillez réessayer.';
                    messageClass = 'woocommerce-error';
                    break;
                default:
                    return;
            }
            
            const messageHtml = '<div class="' + messageClass + '" role="alert">' + message + '</div>';
            $('.woocommerce-notices-wrapper').first().html(messageHtml);
        },
        
        showProcessingMessage: function() {
            const processingHtml = '<div class="woocommerce-info" role="alert">Traitement de votre paiement en cours...</div>';
            $('.woocommerce-notices-wrapper').first().html(processingHtml);
        }
    };
    
    // Utilitaires pour l'interface utilisateur
    const UIHelpers = {
        
        addLoadingOverlay: function(element) {
            const $element = $(element);
            const overlay = '<div class="mmg-loading-overlay"><div class="mmg-spinner"></div></div>';
            
            $element.css('position', 'relative').append(overlay);
        },
        
        removeLoadingOverlay: function(element) {
            $(element).find('.mmg-loading-overlay').remove();
        },
        
        showToast: function(message, type = 'info') {
            const toast = $('<div class="mmg-toast mmg-toast-' + type + '">' + message + '</div>');
            
            $('body').append(toast);
            
            setTimeout(function() {
                toast.addClass('show');
            }, 100);
            
            setTimeout(function() {
                toast.removeClass('show');
                setTimeout(function() {
                    toast.remove();
                }, 300);
            }, 3000);
        }
    };
    
    // Initialisation
    MobileMoneyCheckout.init();
    PaymentStatusHandler.init();
    
    // Événements de mise à jour du checkout
    $('body').on('updated_checkout', function() {
        MobileMoneyCheckout.addPaymentIcons();
        MobileMoneyCheckout.updatePaymentInterface();
    });
    
    // Événement de changement de méthode de paiement
    $(document).on('change', 'input[name="payment_method"]', function() {
        MobileMoneyCheckout.updatePaymentInterface();
    });
    
    // Styles CSS pour les composants
    if (!$('#mmg-checkout-styles').length) {
        $('<style id="mmg-checkout-styles">')
            .html(`
                .phone-validation-feedback {
                    font-size: 12px;
                    margin-top: 5px;
                    display: block;
                }
                
                .phone-validation-feedback.success {
                    color: #46b450;
                }
                
                .phone-validation-feedback.error {
                    color: #dc3232;
                }
                
                input.valid {
                    border-color: #46b450 !important;
                    box-shadow: 0 0 2px rgba(70, 180, 80, 0.3) !important;
                }
                
                input.invalid {
                    border-color: #dc3232 !important;
                    box-shadow: 0 0 2px rgba(220, 50, 50, 0.3) !important;
                }
                
                .mmg-loading-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(255, 255, 255, 0.8);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 999;
                }
                
                .mmg-spinner {
                    width: 30px;
                    height: 30px;
                    border: 3px solid #f3f3f3;
                    border-top: 3px solid #0073aa;
                    border-radius: 50%;
                    animation: mmg-spin 1s linear infinite;
                }
                
                @keyframes mmg-spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                
                .mmg-toast {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 12px 20px;
                    border-radius: 4px;
                    color: white;
                    font-weight: bold;
                    z-index: 9999;
                    transform: translateX(100%);
                    transition: transform 0.3s ease;
                }
                
                .mmg-toast.show {
                    transform: translateX(0);
                }
                
                .mmg-toast-info {
                    background-color: #0073aa;
                }
                
                .mmg-toast-success {
                    background-color: #46b450;
                }
                
                .mmg-toast-error {
                    background-color: #dc3232;
                }
                
                .mobile-money-phone-field {
                    margin: 10px 0;
                }
                
                .mobile-money-phone-field label {
                    display: block;
                    margin-bottom: 5px;
                    font-weight: bold;
                }
                
                .mobile-money-phone-field input {
                    width: 100%;
                    padding: 8px 12px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    font-size: 14px;
                }
                
                .payment_method_wave_gateway .payment_box,
                .payment_method_orange_money_gateway .payment_box {
                    background: #f8f9fa;
                    border: 1px solid #e9ecef;
                    border-radius: 4px;
                    padding: 15px;
                    margin-top: 10px;
                }
            `)
            .appendTo('head');
    }
    
    // Fonction globale pour les autres scripts
    window.MobileMoneyCheckout = MobileMoneyCheckout;
    window.UIHelpers = UIHelpers;
});