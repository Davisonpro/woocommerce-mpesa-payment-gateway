/**
 * M-Pesa Blocks Integration
 * 
 * Modern ES6+ implementation for WooCommerce Blocks checkout
 * 
 * @package WooMpesa
 */

const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { createElement, useState, useEffect } = window.wp.element;
const { __ } = window.wp.i18n;
const { getSetting } = window.wc.wcSettings;
const { decodeEntities } = window.wp.htmlEntities;

// Get payment method settings
const settings = getSetting('mpesa_data', {});

/**
 * Phone number validation
 * 
 * @param {string} phone - Phone number to validate
 * @returns {boolean} - Whether phone is valid
 */
const validatePhoneNumber = (phone) => {
    if (!phone) return false;
    
    // Remove whitespace
    const cleanPhone = phone.replace(/\s+/g, '');
    
    // Validate Kenyan phone number format
    return /^(254|0)[17]\d{8}$/.test(cleanPhone);
};

/**
 * Format phone number to M-Pesa format
 * 
 * @param {string} phone - Phone number to format
 * @returns {string} - Formatted phone number
 */
const formatPhoneNumber = (phone) => {
    let formatted = phone.replace(/\D/g, '');
    
    if (formatted.startsWith('0')) {
        formatted = '254' + formatted.substring(1);
    } else if (!formatted.startsWith('254')) {
        formatted = '254' + formatted;
    }
    
    return formatted;
};

/**
 * Phone Input Component
 * 
 * @param {Object} props - Component props
 * @returns {JSX.Element} - Phone input field
 */
const PhoneInput = ({ value, onChange, onBlur, hasError }) => {
    const handleInput = (event) => {
        const input = event.target.value;
        // Only allow numbers
        const numeric = input.replace(/\D/g, '');
        onChange(numeric);
    };

    const inputClass = hasError 
        ? 'wc-block-components-text-input__input has-error' 
        : 'wc-block-components-text-input__input';

    return createElement(
        'div',
        { 
            className: 'wc-block-components-text-input mpesa-payment-gateway-phone-input',
            style: { marginTop: '1rem' }
        },
        createElement(
            'label',
            { 
                htmlFor: 'mpesa-payment-gateway-phone',
                className: 'wc-block-components-text-input__label',
                style: {
                    display: 'block',
                    marginBottom: '0.5rem',
                    fontSize: '14px',
                    fontWeight: '600',
                    position: 'relative',
                    transform: 'none',
                    top: 'auto',
                    left: 'auto'
                }
            },
            settings.phoneLabel || __('M-Pesa Phone Number', 'mpesa-payment-gateway')
        ),
        createElement(
            'input',
            {
                type: 'tel',
                id: 'mpesa-payment-gateway-phone',
                className: inputClass,
                value: value,
                onChange: handleInput,
                onBlur: onBlur,
                placeholder: settings.phonePlaceholder || '254712345678',
                required: true,
                'aria-required': 'true',
                pattern: '[0-9]*',
                inputMode: 'numeric',
                maxLength: 12,
                autoComplete: 'tel',
                style: {
                    width: '100%',
                    padding: '0.75rem',
                    fontSize: '16px',
                    border: hasError ? '1px solid #cc1818' : '1px solid #ddd',
                    borderRadius: '4px',
                    boxSizing: 'border-box'
                }
            }
        )
    );
};

/**
 * Conversion Notice Component
 * 
 * Displays currency conversion information
 * 
 * @returns {JSX.Element|null} - Conversion notice or null
 */
const ConversionNotice = () => {
    const conversionInfo = settings.conversionInfo;
    
    if (!conversionInfo) {
        return null;
    }

    if (conversionInfo.error) {
        return createElement(
            'div',
            {
                className: 'wc-block-components-notice-banner is-info',
                style: {
                    marginBottom: '1rem',
                    padding: '1rem',
                    backgroundColor: '#f0f0f0',
                    border: '1px solid #ddd',
                    borderRadius: '4px'
                }
            },
            createElement('strong', null, __('Currency Conversion Required', 'mpesa-payment-gateway') + ': '),
            conversionInfo.message
        );
    }

    const formattedOriginalAmount = parseFloat(conversionInfo.amount).toLocaleString('en-US', {
        style: 'currency',
        currency: conversionInfo.currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    
    const formattedKesAmount = parseFloat(conversionInfo.kesAmount).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    
    const formattedRate = parseFloat(conversionInfo.rate).toLocaleString('en-US', {
        minimumFractionDigits: 4,
        maximumFractionDigits: 4
    });

    const message = sprintf(
        __('Your order total of %1$s will be charged as KES %2$s (Rate: 1 %3$s = %4$s KES)', 'mpesa-payment-gateway'),
        '<strong>' + formattedOriginalAmount + '</strong>',
        '<strong>' + formattedKesAmount + '</strong>',
        conversionInfo.currency,
        '<strong>' + formattedRate + '</strong>'
    );

    return createElement(
        'div',
        {
            className: 'wc-block-components-notice-banner is-info',
            style: {
                marginBottom: '1rem',
                padding: '1rem',
                backgroundColor: '#e7f5fe',
                border: '1px solid #b8dff5',
                borderRadius: '4px',
                fontSize: '14px'
            },
            dangerouslySetInnerHTML: { __html: message }
        }
    );
};

/**
 * Content Component
 * 
 * Renders the payment method content
 * 
 * @param {Object} props - Component props
 * @returns {JSX.Element} - Payment content
 */
const Content = (props) => {
    const { eventRegistration, emitResponse } = props;
    const { onPaymentSetup, onCheckoutValidation } = eventRegistration;
    
    const [phoneNumber, setPhoneNumber] = useState('');
    const [hasError, setHasError] = useState(false);

    // Setup payment processing
    useEffect(() => {
        const unsubscribe = onPaymentSetup(async () => {
            if (!phoneNumber) {
                return {
                    type: emitResponse.responseTypes.ERROR,
                    message: settings.phoneRequired || __('M-Pesa phone number is required.', 'mpesa-payment-gateway'),
                };
            }

            if (!validatePhoneNumber(phoneNumber)) {
                return {
                    type: emitResponse.responseTypes.ERROR,
                    message: settings.phoneInvalid || __('Please enter a valid M-Pesa phone number.', 'mpesa-payment-gateway'),
                };
            }

            const formattedPhone = formatPhoneNumber(phoneNumber);

            return {
                type: emitResponse.responseTypes.SUCCESS,
                meta: {
                    paymentMethodData: {
                        billing_mpesa_phone: formattedPhone,
                    },
                },
            };
        });

        return unsubscribe;
    }, [
        phoneNumber,
        onPaymentSetup,
        emitResponse.responseTypes.ERROR,
        emitResponse.responseTypes.SUCCESS,
    ]);

    // Validate on blur
    const handleBlur = () => {
        if (phoneNumber && !validatePhoneNumber(phoneNumber)) {
            setHasError(true);
        } else {
            setHasError(false);
        }
    };

    // Handle phone change
    const handlePhoneChange = (value) => {
        setPhoneNumber(value);
        if (hasError && validatePhoneNumber(value)) {
            setHasError(false);
        }
    };

    return createElement(
        'div',
        { className: 'mpesa-payment-gateway-payment-content' },
        settings.description && createElement(
            'p',
            {
                style: { marginBottom: '1rem', lineHeight: '1.6' },
                dangerouslySetInnerHTML: {
                    __html: decodeEntities(settings.description)
                }
            }
        ),
        createElement(ConversionNotice),
        createElement(PhoneInput, {
            value: phoneNumber,
            onChange: handlePhoneChange,
            onBlur: handleBlur,
            hasError: hasError
        })
    );
};

/**
 * Label Component
 * 
 * Renders the payment method label
 * 
 * @param {Object} props - Component props
 * @returns {JSX.Element} - Payment label
 */
const Label = (props) => {
    const { PaymentMethodLabel } = props.components;
    const label = decodeEntities(settings.title) || __('Lipa Na M-Pesa', 'mpesa-payment-gateway');

    return createElement(
        'span',
        { 
            className: 'mpesa-payment-gateway-label', 
            style: { 
                display: 'flex', 
                alignItems: 'center', 
                justifyContent: 'space-between', 
                gap: '10px', 
                width: '100%' 
            } 
        },
        createElement(PaymentMethodLabel, { text: label }),
        settings.icon && createElement('img', {
            src: settings.icon,
            alt: label,
            style: { maxHeight: '30px', width: 'auto' }
        })
    );
};

/**
 * M-Pesa Payment Method Configuration
 */
const MpesaPaymentMethod = {
    name: 'mpesa',
    label: createElement(Label),
    content: createElement(Content),
    edit: createElement(Content),
    canMakePayment: () => true,
    ariaLabel: decodeEntities(settings.title) || __('Lipa Na M-Pesa', 'mpesa-payment-gateway'),
    supports: {
        features: settings.supports || ['products'],
    },
};

// Register the payment method
registerPaymentMethod(MpesaPaymentMethod);

