import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';

const loginInitialState = {
    email: '',
    password: '',
};

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const humanNamePattern = /^[\p{L}\p{M}]+(?:[ .'-][\p{L}\p{M}]+)*$/u;
const passwordRules = [
    {
        key: 'uppercase',
        label: 'One capital letter',
        isValid: (password) => /[A-Z]/.test(password),
    },
    {
        key: 'number',
        label: 'One number',
        isValid: (password) => /[0-9]/.test(password),
    },
    {
        key: 'symbol',
        label: 'One symbol',
        isValid: (password) => /[^A-Za-z0-9]/.test(password),
    },
];
const loginPanelStatusCodes = [419, 429, 503];

function normalizeErrors(errors) {
    return Object.fromEntries(
        Object.entries(errors ?? {}).map(([fieldName, fieldErrors]) => [
            fieldName,
            Array.isArray(fieldErrors) ? fieldErrors[0] : String(fieldErrors),
        ]),
    );
}

function buildSignupInitialState(signupChallenge) {
    return {
        firstName: '',
        lastName: '',
        country: '',
        countryCode: '',
        phoneNumber: '',
        email: '',
        password: '',
        acceptedTerms: false,
        companyWebsite: '',
        signupStartedAt: signupChallenge.signupStartedAt,
        signupChallengeNonce: signupChallenge.signupChallengeNonce,
        signupChallengeToken: signupChallenge.signupChallengeToken,
    };
}

function validateSignupForm(formData, countryOptions) {
    const validationErrors = {};
    const selectedCountryOption = countryOptions.find((countryOption) => countryOption.value === formData.country);

    if (formData.firstName.trim().length <= 3) {
        validationErrors.firstName = 'First name must be longer than 3 characters.';
    } else if (!humanNamePattern.test(formData.firstName.trim())) {
        validationErrors.firstName = 'First name may only contain letters, spaces, hyphens, apostrophes, and periods.';
    }

    if (formData.lastName.trim().length <= 3) {
        validationErrors.lastName = 'Last name must be longer than 3 characters.';
    } else if (!humanNamePattern.test(formData.lastName.trim())) {
        validationErrors.lastName = 'Last name may only contain letters, spaces, hyphens, apostrophes, and periods.';
    }

    if (!formData.country) {
        validationErrors.country = 'Please choose your country.';
    }

    if (!/^[0-9]+$/.test(formData.countryCode)) {
        validationErrors.countryCode = 'Country code must contain numbers only.';
    } else if (selectedCountryOption && formData.countryCode !== selectedCountryOption.countryCode) {
        validationErrors.countryCode = 'Country code must match the selected country.';
    }

    if (!/^[0-9]{6,15}$/.test(formData.phoneNumber)) {
        validationErrors.phoneNumber = 'Phone number must contain 6 to 15 digits.';
    }

    if (!emailPattern.test(formData.email)) {
        validationErrors.email = 'Please enter a valid email address.';
    }

    const failedPasswordRule = passwordRules.find((passwordRule) => !passwordRule.isValid(formData.password));

    if (formData.password.length < 8) {
        validationErrors.password = 'Password must be at least 8 characters.';
    } else if (failedPasswordRule) {
        validationErrors.password = `Password must include ${failedPasswordRule.label.toLowerCase()}.`;
    }

    if (!formData.acceptedTerms) {
        validationErrors.acceptedTerms = 'Please accept the Privacy Policy and Terms and Conditions.';
    }

    return validationErrors;
}

function validateLoginForm(formData) {
    const validationErrors = {};

    if (!formData.email.trim()) {
        validationErrors.email = 'Please enter your email address.';
    }

    if (!formData.password) {
        validationErrors.password = 'Please enter your password.';
    }

    return validationErrors;
}

function loginPayloadFromForm(loginForm, currentFormData) {
    const submittedFormData = new FormData(loginForm);

    return {
        email: String(submittedFormData.get('email') ?? currentFormData.email).trim(),
        password: String(submittedFormData.get('password') ?? currentFormData.password),
    };
}

function firstErrorMessage(errors) {
    return Object.values(errors).find((errorMessage) => typeof errorMessage === 'string' && errorMessage.trim() !== '');
}

function responseFallbackMessage(responseStatus) {
    const fallbackMessages = {
        419: 'Your session has expired. Please refresh the page and try again.',
        429: 'Too many attempts. Please wait a moment and try again.',
        503: 'This feature is currently unavailable. Please try again later.',
    };

    return fallbackMessages[responseStatus] || 'Please check the form and try again.';
}

function responseErrorMessage({ responseBody, responseStatus, errors }) {
    const responseMessage = typeof responseBody?.message === 'string' ? responseBody.message.trim() : '';
    const fieldErrorMessage = firstErrorMessage(errors);

    if (fieldErrorMessage && (!responseMessage || responseMessage === 'The given data was invalid.')) {
        return fieldErrorMessage;
    }

    return responseMessage || fieldErrorMessage || responseFallbackMessage(responseStatus);
}

function isCredentialError(errorMessage) {
    return typeof errorMessage === 'string' && errorMessage.includes('email or password');
}

function loginFieldErrorsForDisplay(formErrors) {
    if (isCredentialError(formErrors.email) || isCredentialError(formErrors.password)) {
        return {};
    }

    return formErrors;
}

function shouldRenderLoginStatusPanel({ statusCode, statusMessage, statusType }) {
    if (!statusMessage) {
        return false;
    }

    if (statusType === 'success') {
        return true;
    }

    return statusType === 'error' && loginPanelStatusCodes.includes(statusCode);
}

async function submitJson({ endpoint, payload, abortSignal, csrfToken }) {
    const headers = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }

    const response = await fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers,
        body: JSON.stringify(payload),
        signal: abortSignal,
    });

    const responseContentType = response.headers.get('Content-Type') || '';
    const responseText = await response.text();
    let responseBody = {};

    try {
        responseBody = responseText ? JSON.parse(responseText) : {};
    } catch {
        responseBody = {};
    }

    if (response.redirected || !responseContentType.includes('application/json')) {
        const requestError = new Error(
            response.redirected
                ? 'The server redirected the request. Please refresh the page and try again.'
                : responseFallbackMessage(response.status),
        );
        requestError.errors = {};
        requestError.status = response.status;
        throw requestError;
    }

    if (!response.ok) {
        const errors = normalizeErrors(responseBody.errors);
        const requestError = new Error(responseErrorMessage({
            errors,
            responseBody,
            responseStatus: response.status,
        }));
        requestError.errors = errors;
        requestError.status = response.status;
        throw requestError;
    }

    return responseBody;
}

async function requestSignupChallenge({ abortSignal } = {}) {
    const response = await fetch('/api/signup-challenge', {
        headers: {
            Accept: 'application/json',
        },
        signal: abortSignal,
    });

    const responseBody = await response.json().catch(() => ({}));

    if (
        !response.ok ||
        !['signupStartedAt', 'signupChallengeNonce', 'signupChallengeToken'].every(
            (challengeKey) => typeof responseBody?.[challengeKey] === 'string',
        )
    ) {
        throw new Error('Please refresh the page and try again.');
    }

    return responseBody;
}

function FieldError({ message }) {
    if (!message) {
        return null;
    }

    return <p className="field-error">{message}</p>;
}

function EyeIcon({ isPasswordVisible }) {
    return (
        <svg aria-hidden="true" className="eye-icon" focusable="false" viewBox="0 0 24 24">
            <path d="M2.8 12s3.3-5.5 9.2-5.5S21.2 12 21.2 12s-3.3 5.5-9.2 5.5S2.8 12 2.8 12Z" />
            <path d="M12 9.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z" />
            {isPasswordVisible && <path d="M4.2 4.2 19.8 19.8" />}
        </svg>
    );
}

function TextInput({
    autoComplete,
    className = '',
    errorMessage,
    inputMode,
    name,
    onChange,
    pattern,
    placeholder,
    readOnly = false,
    type = 'text',
    value,
}) {
    return (
        <label className={`input-field ${className}`}>
            <span className="sr-only">{placeholder}</span>
            <input
                aria-invalid={Boolean(errorMessage)}
                autoComplete={autoComplete}
                className="form-control"
                inputMode={inputMode}
                name={name}
                onChange={onChange}
                pattern={pattern}
                placeholder={placeholder}
                readOnly={readOnly}
                type={type}
                value={value}
            />
            <FieldError message={errorMessage} />
        </label>
    );
}

function PasswordInput({ autoComplete, errorMessage, name, onChange, value }) {
    const [isPasswordVisible, setIsPasswordVisible] = useState(false);

    return (
        <label className="input-field password-field">
            <span className="sr-only">Password</span>
            <span className="password-control">
                <input
                    aria-invalid={Boolean(errorMessage)}
                    autoComplete={autoComplete}
                    className="form-control"
                    name={name}
                    onChange={onChange}
                    placeholder="Password"
                    type={isPasswordVisible ? 'text' : 'password'}
                    value={value}
                />
                <button
                    aria-label={isPasswordVisible ? 'Hide password' : 'Show password'}
                    className="password-toggle"
                    onClick={() => setIsPasswordVisible((currentIsPasswordVisible) => !currentIsPasswordVisible)}
                    type="button"
                >
                    <EyeIcon isPasswordVisible={isPasswordVisible} />
                </button>
            </span>
            <FieldError message={errorMessage} />
        </label>
    );
}

function HfmLogo() {
    return (
        <a aria-label="HFM home" className="hfm-logo" href="/register">
            <span className="logo-member">Member of HF Markets Group</span>
            <span className="logo-main">
                <span>HF</span>
                <span className="logo-emphasis">M</span>
            </span>
            <span className="logo-subtitle">HF MARKETS</span>
        </a>
    );
}

function ScreenHeader({ actionHref, actionLabel, actionVariant }) {
    return (
        <header className="assessment-header">
            <div className="header-inner">
                <HfmLogo />
                <a className={`screen-action ${actionVariant}`} href={actionHref}>
                    {actionLabel}
                </a>
            </div>
        </header>
    );
}

function SignupForm({ countryOptions, csrfToken, signupChallenge }) {
    const [currentSignupChallenge, setCurrentSignupChallenge] = useState(signupChallenge);
    const [formData, setFormData] = useState(() => buildSignupInitialState(signupChallenge));
    const [formErrors, setFormErrors] = useState({});
    const [statusMessage, setStatusMessage] = useState('');
    const [statusType, setStatusType] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const submitControllerReference = useRef(null);
    const isSubmittingReference = useRef(false);
    const requestSequenceReference = useRef(0);

    const selectedCountryOption = useMemo(
        () => countryOptions.find((countryOption) => countryOption.value === formData.country),
        [countryOptions, formData.country],
    );

    useEffect(() => {
        return () => {
            submitControllerReference.current?.abort();
        };
    }, []);

    function updateField(fieldName, value) {
        setFormData((currentFormData) => ({
            ...currentFormData,
            [fieldName]: value,
        }));

        setFormErrors((currentFormErrors) => ({
            ...currentFormErrors,
            [fieldName]: undefined,
        }));
    }

    function updateCountry(value) {
        const nextCountryOption = countryOptions.find((countryOption) => countryOption.value === value);

        setFormData((currentFormData) => ({
            ...currentFormData,
            country: value,
            countryCode: nextCountryOption?.countryCode ?? '',
        }));

        setFormErrors((currentFormErrors) => ({
            ...currentFormErrors,
            country: undefined,
            countryCode: undefined,
        }));
    }

    async function refreshSignupChallenge({ abortSignal, preserveFormValues }) {
        const nextSignupChallenge = await requestSignupChallenge({ abortSignal });

        setCurrentSignupChallenge(nextSignupChallenge);
        setFormData((currentFormData) =>
            preserveFormValues
                ? {
                      ...currentFormData,
                      signupStartedAt: nextSignupChallenge.signupStartedAt,
                      signupChallengeNonce: nextSignupChallenge.signupChallengeNonce,
                      signupChallengeToken: nextSignupChallenge.signupChallengeToken,
                  }
                : buildSignupInitialState(nextSignupChallenge),
        );

        return nextSignupChallenge;
    }

    async function handleSubmit(submitEvent) {
        submitEvent.preventDefault();

        if (isSubmittingReference.current) {
            return;
        }

        const validationErrors = validateSignupForm(formData, countryOptions);

        if (Object.keys(validationErrors).length > 0) {
            setFormErrors(validationErrors);
            setStatusMessage('');
            setStatusType('');
            return;
        }

        isSubmittingReference.current = true;
        const abortController = new AbortController();
        submitControllerReference.current = abortController;
        const requestSequence = requestSequenceReference.current + 1;
        requestSequenceReference.current = requestSequence;

        setIsSubmitting(true);
        setStatusMessage('');
        setStatusType('');

        try {
            const responseBody = await submitJson({
                endpoint: '/register',
                payload: formData,
                abortSignal: abortController.signal,
                csrfToken,
            });

            if (requestSequenceReference.current !== requestSequence) {
                return;
            }

            setStatusMessage(responseBody.message);
            setStatusType('success');
            setFormErrors({});
            try {
                await refreshSignupChallenge({
                    abortSignal: abortController.signal,
                    preserveFormValues: false,
                });
            } catch {
                setFormData(buildSignupInitialState(currentSignupChallenge));
            }
        } catch (requestError) {
            if (requestError.name === 'AbortError' || requestSequenceReference.current !== requestSequence) {
                return;
            }

            setFormErrors(requestError.errors ?? {});
            setStatusMessage(requestError.message);
            setStatusType('error');

            if (requestError.errors?.signupChallengeToken) {
                await refreshSignupChallenge({
                    abortSignal: abortController.signal,
                    preserveFormValues: true,
                }).catch(() => undefined);
            }
        } finally {
            if (requestSequenceReference.current === requestSequence) {
                isSubmittingReference.current = false;
                setIsSubmitting(false);
            }
        }
    }

    return (
        <form autoComplete="off" className="auth-card register-card" noValidate onSubmit={handleSubmit}>
            <label aria-hidden="true" className="spam-trap">
                Company website
                <input
                    autoComplete="off"
                    name="companyWebsite"
                    onChange={(event) => updateField('companyWebsite', event.target.value)}
                    tabIndex="-1"
                    value={formData.companyWebsite}
                />
            </label>

            <h2 className="auth-card-title">Lorem ipsum dolor sit amet</h2>

            {statusMessage && (
                <div
                    className={`form-status ${statusType === 'error' ? 'error-status' : ''}`}
                    role={statusType === 'error' ? 'alert' : 'status'}
                >
                    {statusMessage}
                </div>
            )}

            <div className="register-grid">
                <TextInput
                    autoComplete="given-name"
                    errorMessage={formErrors.firstName}
                    name="firstName"
                    onChange={(event) => updateField('firstName', event.target.value)}
                    placeholder="First Name"
                    value={formData.firstName}
                />

                <TextInput
                    autoComplete="family-name"
                    errorMessage={formErrors.lastName}
                    name="lastName"
                    onChange={(event) => updateField('lastName', event.target.value)}
                    placeholder="Last Name"
                    value={formData.lastName}
                />
            </div>

            <div className="country-phone-grid">
                <label className="input-field select-field">
                    <span className="sr-only">Country</span>
                    <select
                        aria-invalid={Boolean(formErrors.country)}
                        className={formData.country ? 'form-control' : 'form-control is-placeholder'}
                        name="country"
                        onChange={(event) => updateCountry(event.target.value)}
                        value={formData.country}
                    >
                        <option value="">Country</option>
                        {countryOptions.map((countryOption) => (
                            <option key={countryOption.value} value={countryOption.value}>
                                {countryOption.label}
                            </option>
                        ))}
                    </select>
                    <FieldError message={formErrors.country} />
                </label>

                <div className="phone-combo">
                    <TextInput
                        className="code-field"
                        errorMessage={formErrors.countryCode}
                        inputMode="numeric"
                        name="countryCode"
                        onChange={(event) => updateField('countryCode', event.target.value.replace(/\D/g, ''))}
                        pattern="[0-9]*"
                        placeholder="Code"
                        readOnly
                        value={formData.countryCode}
                    />

                    <TextInput
                        autoComplete="tel-national"
                        errorMessage={formErrors.phoneNumber}
                        inputMode="numeric"
                        name="phoneNumber"
                        onChange={(event) => updateField('phoneNumber', event.target.value.replace(/\D/g, ''))}
                        placeholder={selectedCountryOption?.placeholder ?? 'Phone'}
                        type="tel"
                        value={formData.phoneNumber}
                    />
                </div>
            </div>

            <div className="register-grid">
                <TextInput
                    autoComplete="email"
                    errorMessage={formErrors.email}
                    name="email"
                    onChange={(event) => updateField('email', event.target.value)}
                    placeholder="Email"
                    type="email"
                    value={formData.email}
                />

                <PasswordInput
                    autoComplete="new-password"
                    errorMessage={formErrors.password}
                    name="password"
                    onChange={(event) => updateField('password', event.target.value)}
                    value={formData.password}
                />
            </div>

            <label className="terms-row">
                <input
                    checked={formData.acceptedTerms}
                    name="acceptedTerms"
                    onChange={(event) => updateField('acceptedTerms', event.target.checked)}
                    type="checkbox"
                />
                <span>
                    I have read and accepted the <a href="#privacy">Privacy Policy</a> and{' '}
                    <a href="#terms">Terms and Conditions</a>
                </span>
            </label>
            <FieldError message={formErrors.acceptedTerms} />

            <button className="join-button" disabled={isSubmitting} type="submit">
                {isSubmitting ? 'JOINING...' : 'JOIN NOW'}
            </button>
        </form>
    );
}

function LoginForm({ csrfToken }) {
    const [formData, setFormData] = useState(loginInitialState);
    const [formErrors, setFormErrors] = useState({});
    const [statusMessage, setStatusMessage] = useState('');
    const [statusType, setStatusType] = useState('');
    const [statusCode, setStatusCode] = useState(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const submitControllerReference = useRef(null);
    const requestSequenceReference = useRef(0);

    useEffect(() => {
        return () => {
            submitControllerReference.current?.abort();
        };
    }, []);

    function updateField(fieldName, value) {
        setFormData((currentFormData) => ({
            ...currentFormData,
            [fieldName]: value,
        }));

        setFormErrors((currentFormErrors) => ({
            ...currentFormErrors,
            [fieldName]: undefined,
        }));

        if (statusType === 'error') {
            setStatusMessage('');
            setStatusType('');
            setStatusCode(null);
        }
    }

    async function handleSubmit(submitEvent) {
        submitEvent.preventDefault();

        let requestSequence = null;

        try {
            const submittedFormData = loginPayloadFromForm(submitEvent.currentTarget, formData);
            const validationErrors = validateLoginForm(submittedFormData);

            setFormData(submittedFormData);

            if (Object.keys(validationErrors).length > 0) {
                setFormErrors(validationErrors);
                setStatusMessage(firstErrorMessage(validationErrors) || '');
                setStatusType('error');
                setStatusCode(null);
                return;
            }

            submitControllerReference.current?.abort();
            const abortController = new AbortController();
            submitControllerReference.current = abortController;
            requestSequence = requestSequenceReference.current + 1;
            requestSequenceReference.current = requestSequence;

            setIsSubmitting(true);
            setStatusMessage('');
            setStatusType('');
            setStatusCode(null);

            const responseBody = await submitJson({
                endpoint: '/login',
                payload: submittedFormData,
                abortSignal: abortController.signal,
                csrfToken,
            });

            if (requestSequenceReference.current !== requestSequence) {
                return;
            }

            setStatusMessage(responseBody.message);
            setStatusType('success');
            setStatusCode(null);
            setFormErrors({});
        } catch (requestError) {
            if (requestError?.name === 'AbortError' || (requestSequence !== null && requestSequenceReference.current !== requestSequence)) {
                return;
            }

            const nextFormErrors = requestError?.errors ?? {};
            const nextStatusMessage = requestError instanceof Error && requestError.message
                ? requestError.message
                : 'We could not submit your login details. Please try again.';

            setFormErrors(nextFormErrors);
            setStatusMessage(nextStatusMessage);
            setStatusType('error');
            setStatusCode(requestError?.status ?? null);
        } finally {
            if (requestSequence === null || requestSequenceReference.current === requestSequence) {
                setIsSubmitting(false);
            }
        }
    }

    const displayedFormErrors = loginFieldErrorsForDisplay(formErrors);
    const loginFeedbackMessage = statusMessage || displayedFormErrors.email || displayedFormErrors.password || '';
    const loginFeedbackType = statusType || (loginFeedbackMessage ? 'error' : '');
    const renderLoginStatusPanel = shouldRenderLoginStatusPanel({
        statusCode,
        statusMessage: loginFeedbackMessage,
        statusType: loginFeedbackType,
    });

    return (
        <form action="/login" autoComplete="off" className="auth-card login-card" method="post" noValidate onSubmit={handleSubmit}>
            <input name="_token" type="hidden" value={csrfToken} />
            <h2 className="auth-card-title">Lorem ipsum dolor sit amet</h2>

            {renderLoginStatusPanel && (
                <div
                    className={`form-status compact login-status-panel ${
                        loginFeedbackType === 'error' ? 'error-status' : ''
                    }`}
                    role={loginFeedbackType === 'error' ? 'alert' : 'status'}
                >
                    {loginFeedbackMessage}
                </div>
            )}

            {!renderLoginStatusPanel && loginFeedbackType !== 'success' && (
                <div
                    aria-live="assertive"
                    className={`login-feedback ${loginFeedbackMessage ? 'is-visible' : ''} ${
                        loginFeedbackType ? `is-${loginFeedbackType}` : ''
                    }`}
                    role={loginFeedbackMessage ? 'alert' : undefined}
                >
                    {loginFeedbackMessage}
                </div>
            )}

            <TextInput
                autoComplete="off"
                errorMessage={displayedFormErrors.email}
                name="email"
                onChange={(event) => updateField('email', event.target.value)}
                placeholder="Email"
                type="email"
                value={formData.email}
            />

            <PasswordInput
                autoComplete="current-password"
                errorMessage={displayedFormErrors.password}
                name="password"
                onChange={(event) => updateField('password', event.target.value)}
                value={formData.password}
            />

            <button className="login-button" disabled={isSubmitting} type="submit">
                {isSubmitting ? 'LOGGING IN...' : 'LOGIN'}
            </button>

            <div aria-hidden="true" className="login-submit-feedback" />
        </form>
    );
}

function AuthScreen({ authPage, countryOptions, csrfToken, signupChallenge }) {
    const isRegisterPage = authPage === 'register';

    return (
        <main className={`figma-page ${isRegisterPage ? 'register-page' : 'login-page'}`}>
            <section className={`assessment-screen ${isRegisterPage ? 'register-screen' : 'login-screen'}`}>
                <ScreenHeader
                    actionHref={isRegisterPage ? '/login' : '/register'}
                    actionLabel={isRegisterPage ? 'Login' : 'Register'}
                    actionVariant={isRegisterPage ? 'outline-red' : 'solid-green'}
                />

                <div className="screen-stage">
                    <div className="screen-content">
                        <h1 className="screen-title">{isRegisterPage ? 'REGISTER' : 'LOGIN'}</h1>
                        {isRegisterPage ? (
                            <SignupForm
                                countryOptions={countryOptions}
                                csrfToken={csrfToken}
                                signupChallenge={signupChallenge}
                            />
                        ) : (
                            <LoginForm csrfToken={csrfToken} />
                        )}
                    </div>
                </div>
            </section>
        </main>
    );
}

function readCountryOptions(applicationElement) {
    try {
        const parsedCountryOptions = JSON.parse(applicationElement?.dataset.countryOptions || '[]');

        if (!Array.isArray(parsedCountryOptions)) {
            return [];
        }

        return parsedCountryOptions.filter((countryOption) =>
            ['value', 'label', 'countryCode', 'dialingPrefix', 'placeholder'].every(
                (countryOptionKey) => typeof countryOption?.[countryOptionKey] === 'string',
            ),
        );
    } catch {
        return [];
    }
}

function readAuthPage(applicationElement) {
    const requestedAuthPage = applicationElement?.dataset.authPage;

    return requestedAuthPage === 'login' ? 'login' : 'register';
}

function readSignupChallenge(applicationElement) {
    try {
        const parsedSignupChallenge = JSON.parse(applicationElement?.dataset.signupChallenge || '{}');

        if (
            ['signupStartedAt', 'signupChallengeNonce', 'signupChallengeToken'].every(
                (challengeKey) => typeof parsedSignupChallenge?.[challengeKey] === 'string',
            )
        ) {
            return parsedSignupChallenge;
        }
    } catch {
        return {
            signupStartedAt: '',
            signupChallengeNonce: '',
            signupChallengeToken: '',
        };
    }

    return {
        signupStartedAt: '',
        signupChallengeNonce: '',
        signupChallengeToken: '',
    };
}

function readCsrfToken(applicationElement) {
    const csrfToken = applicationElement?.dataset.csrfToken;

    return typeof csrfToken === 'string' ? csrfToken : '';
}

const applicationElement = document.getElementById('app');

if (applicationElement) {
    createRoot(applicationElement).render(
        <AuthScreen
            authPage={readAuthPage(applicationElement)}
            csrfToken={readCsrfToken(applicationElement)}
            countryOptions={readCountryOptions(applicationElement)}
            signupChallenge={readSignupChallenge(applicationElement)}
        />,
    );
}
