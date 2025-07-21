const settings = window.wc.wcSettings.getSetting("wave_gateway_data", {});
const label = window.wp.htmlEntities.decodeEntities(settings.title) ||
    window.wp.i18n.__("Wave", "mobile-money-gateway");

const Content = () => {
    const { useState } = window.wp.element;
    const [phoneNumber, setPhoneNumber] = useState('');
    const [isValid, setIsValid] = useState(true);

    const description = window.wp.htmlEntities.decodeEntities(
        settings.description ||
        window.wp.i18n.__("Payez en toute sécurité avec Wave Money.", "mobile-money-gateway")
    );

    // Validation du numéro de téléphone Wave (Sénégal)
    const validateWaveNumber = (number) => {
        const cleaned = number.replace(/[\s\-()]+/g, '');
        return /^(\+221|221)?[73][0-9]{7}$/.test(cleaned);
    };

    const handlePhoneChange = (event) => {
        const value = event.target.value;
        setPhoneNumber(value);
        setIsValid(validateWaveNumber(value));
    };

    return window.wp.element.createElement(
        "div",
        { className: "wc-block-gateway-wave" },
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
            { className: "wc-block-gateway-wave-phone" },
            window.wp.element.createElement(
                "label",
                { 
                    htmlFor: "wave_phone_number",
                    style: { display: "block", marginBottom: "5px", fontWeight: "bold" }
                },
                window.wp.i18n.__("Numéro de téléphone Wave", "mobile-money-gateway"),
                window.wp.element.createElement("span", { style: { color: "red" } }, " *")
            ),
            window.wp.element.createElement("input", {
                type: "tel",
                id: "wave_phone_number",
                name: "wave_gateway_phone_number",
                value: phoneNumber,
                onChange: handlePhoneChange,
                placeholder: "+221 XX XXX XX XX",
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
                isValid 
                    ? window.wp.i18n.__("Format: +221XXXXXXXX", "mobile-money-gateway")
                    : window.wp.i18n.__("Veuillez entrer un numéro Wave valide", "mobile-money-gateway")
            )
        )
    );
};

const WaveGateway = {
    name: "wave_gateway",
    label: label,
    content: window.wp.element.createElement(Content, null),
    edit: window.wp.element.createElement(Content, null),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
};

window.wc.wcBlocksRegistry.registerPaymentMethod(WaveGateway);