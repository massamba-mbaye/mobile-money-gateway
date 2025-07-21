const settings = window.wc.wcSettings.getSetting("orange_money_gateway_data", {});
const label = window.wp.htmlEntities.decodeEntities(settings.title) ||
    window.wp.i18n.__("Orange Money", "mobile-money-gateway");

const Content = () => {
    const { useState } = window.wp.element;
    const [phoneNumber, setPhoneNumber] = useState('');
    const [isValid, setIsValid] = useState(true);

    const description = window.wp.htmlEntities.decodeEntities(
        settings.description ||
        window.wp.i18n.__("Payez en toute sécurité avec Orange Money.", "mobile-money-gateway")
    );

    const countryCode = settings.country_code || 'SN';

    // Validation selon le pays
    const validateOrangeNumber = (number) => {
        const cleaned = number.replace(/[\s\-()]+/g, '');
        
        switch (countryCode) {
            case 'SN': // Sénégal
                return /^(\+221|221)?[73][0-9]{7}$/.test(cleaned);
            case 'CI': // Côte d'Ivoire
                return /^(\+225|225)?[0-9]{8,10}$/.test(cleaned);
            case 'ML': // Mali
                return /^(\+223|223)?[0-9]{8}$/.test(cleaned);
            case 'BF': // Burkina Faso
                return /^(\+226|226)?[0-9]{8}$/.test(cleaned);
            default:
                return /^[+]?[0-9]{8,15}$/.test(cleaned);
        }
    };

    // Placeholder selon le pays
    const getPlaceholder = () => {
        switch (countryCode) {
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
    };

    // Format d'aide selon le pays
    const getHelpText = () => {
        if (isValid) {
            switch (countryCode) {
                case 'SN':
                    return window.wp.i18n.__("Format: +221XXXXXXXX", "mobile-money-gateway");
                case 'CI':
                    return window.wp.i18n.__("Format: +225XXXXXXXX", "mobile-money-gateway");
                case 'ML':
                    return window.wp.i18n.__("Format: +223XXXXXXXX", "mobile-money-gateway");
                case 'BF':
                    return window.wp.i18n.__("Format: +226XXXXXXXX", "mobile-money-gateway");
                default:
                    return window.wp.i18n.__("Entrez votre numéro Orange Money", "mobile-money-gateway");
            }
        } else {
            return window.wp.i18n.__("Veuillez entrer un numéro Orange Money valide", "mobile-money-gateway");
        }
    };

    const handlePhoneChange = (event) => {
        const value = event.target.value;
        setPhoneNumber(value);
        setIsValid(validateOrangeNumber(value));
    };

    return window.wp.element.createElement(
        "div",
        { className: "wc-block-gateway-orange-money" },
        window.wp.element.createElement(
            "div",
            { style: { display: "flex", alignItems: "center", marginBottom: "10px" } },
            window.wp.element.createElement("img", {
                src: settings.icon,
                alt: label,
                style: { width: "32px", height: "32px", marginRight: "10px" },
            }),
            window.wp.element.createElement("span", null, description)
        ),
        settings.phone_number_field && window.wp.element.createElement(
            "div",
            { className: "wc-block-gateway-orange-money-phone" },
            window.wp.element.createElement(
                "label",
                { 
                    htmlFor: "orange_money_phone_number",
                    style: { display: "block", marginBottom: "5px", fontWeight: "bold" }
                },
                window.wp.i18n.__("Numéro de téléphone Orange Money", "mobile-money-gateway"),
                window.wp.element.createElement("span", { style: { color: "red" } }, " *")
            ),
            window.wp.element.createElement("input", {
                type: "tel",
                id: "orange_money_phone_number",
                name: "orange_money_gateway_phone_number",
                value: phoneNumber,
                onChange: handlePhoneChange,
                placeholder: getPlaceholder(),
                pattern: "[+]?[0-9\\s\\-()]+",
                required: true,
                style: {
                    width: "100%",
                    padding: "8px",
                    border: isValid ? "1px solid #ddd" : "1px solid #e74c3c",
                    borderRadius: "4px",
                    fontSize: "14px"
                }
            }),
            window.wp.element.createElement(
                "small",
                { 
                    style: { 
                        color: isValid ? "#666" : "#e74c3c",
                        fontSize: "12px",
                        marginTop: "5px",
                        display: "block"
                    }
                },
                getHelpText()
            )
        )
    );
};

const OrangeMoneyGateway = {
    name: "orange_money_gateway",
    label: label,
    content: window.wp.element.createElement(Content, null),
    edit: window.wp.element.createElement(Content, null),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
};

window.wc.wcBlocksRegistry.registerPaymentMethod(OrangeMoneyGateway);