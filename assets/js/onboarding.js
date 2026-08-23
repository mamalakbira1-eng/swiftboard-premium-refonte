/**
 * SwiftBoard — Onboarding Reddit-like (Wizard 3 étapes)
 *
 * Vanille JS, zéro jQuery. Supporte Google, GitHub, Facebook OAuth réels.
 *
 * @package SwiftBoard
 * @since 7.2.0
 */
(function () {
    'use strict';

    const state = {
        gender: 'autre',
        avatar_id: 1,
        provider: 'email'
    };

    // OAuth public configuration from an inert data-* container.
    const oauthConfig = document.getElementById('swiftboard-oauth-config');
    const oauth = oauthConfig ? {
        google_client_id: oauthConfig.getAttribute('data-google-client-id') || '',
        github_client_id: oauthConfig.getAttribute('data-github-client-id') || '',
        facebook_app_id: oauthConfig.getAttribute('data-facebook-app-id') || ''
    } : {};
    let googleState = '';

    function initOnboarding() {
        const modal = document.getElementById('sb-onboarding-modal');
        if (!modal) return;

        // Load Google Identity Services if configured
        if (oauth.google_client_id) {
            loadGoogleGIS();
        }

        document.addEventListener('click', function (e) {
            const openBtn = e.target.closest('.sb-onboarding-open, [data-open-onboarding], .sb-login-btn');
            if (openBtn) {
                e.preventDefault();
                openModal();
            }

            const closeBtn = e.target.closest('[data-action="close"]');
            if (closeBtn || e.target === modal) {
                closeModal();
            }

            // Étape 1 : Genre
            const genderBtn = e.target.closest('.sb-onb-gender-btn');
            if (genderBtn) {
                const g = genderBtn.getAttribute('data-gender');
                if (g) {
                    state.gender = g;
                    highlightButton('.sb-onb-gender-btn', genderBtn);
                    setTimeout(function () { showStep(2); }, 180);
                }
            }

            const skipGender = e.target.closest('.sb-onb-skip-link');
            if (skipGender) {
                state.gender = 'autre';
                showStep(2);
            }

            // Étape 2 : Avatar
            const avatarBtn = e.target.closest('.sb-onb-avatar-btn');
            if (avatarBtn) {
                const aid = parseInt(avatarBtn.getAttribute('data-avatar-id'), 10) || 1;
                state.avatar_id = aid;
                highlightButton('.sb-onb-avatar-btn', avatarBtn);
                setTimeout(function () { showStep(3); }, 220);
            }

            // Boutons Retour
            const backBtn = e.target.closest('.sb-onb-back-btn');
            if (backBtn) {
                const to = parseInt(backBtn.getAttribute('data-to-step'), 10) || 1;
                showStep(to);
            }

            // Étape 3 : Connexion Sociale
            const socialBtn = e.target.closest('.sb-onb-social-btn');
            if (socialBtn) {
                const provider = socialBtn.getAttribute('data-provider');
                if (provider) {
                    state.provider = provider;
                    triggerSocialLogin(provider);
                }
            }

            // Soumission Email
            const emailSubmit = e.target.closest('#sb-onb-email-submit');
            if (emailSubmit) {
                const emailInput = document.getElementById('sb-onb-email');
                if (emailInput && emailInput.value) {
                    submitAuth('email', { email: emailInput.value, email_verified: true });
                } else {
                    showStatus('Veuillez saisir une adresse e-mail valide.', 'error');
                }
            }
        });
    }

    // =========================================================================
    // GOOGLE — Identity Services (One Tap / Sign-In)
    // =========================================================================
    function loadGoogleGIS() {
        const script = document.createElement('script');
        script.src = 'https://accounts.google.com/gsi/client';
        script.async = true;
        script.defer = true;
        document.head.appendChild(script);
    }

    function triggerGoogleLogin() {
        if (!oauth.google_client_id) {
            showStatus('Google non configuré. Contactez l\'administrateur.', 'error');
            return;
        }

        // Use Google Identity Services
        if (window.google && window.google.accounts) {
            fetch('/wp-json/swiftboard/v1/auth/google-challenge', {
                credentials: 'same-origin',
                cache: 'no-store',
            })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(new Error('challenge')); })
                .then(function (challenge) {
                    googleState = challenge.state || '';
                    if (!googleState) throw new Error('challenge');
                    window.google.accounts.id.initialize({
                        client_id: oauth.google_client_id,
                        nonce: googleState,
                        callback: function (response) {
                            verifyGoogleToken(response.credential, googleState);
                        },
                    });
                    window.google.accounts.id.prompt();
                })
                .catch(function () {
                    showStatus('Impossible d’initialiser la connexion Google.', 'error');
                });
        } else {
            // Fallback: redirect to Google OAuth
            showStatus('Chargement de Google...', 'success');
            setTimeout(function () {
                if (window.google && window.google.accounts) {
                    triggerGoogleLogin();
                } else {
                    showStatus('Google SDK non chargé. Réessayez.', 'error');
                }
            }, 2000);
        }
    }

    function verifyGoogleToken(idToken, stateToken) {
        showStatus('Vérification avec Google...', 'success');
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/wp-json/swiftboard/v1/auth/google-verify', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.onload = function () {
            handleAuthResponse(xhr);
        };
        xhr.onerror = function () {
            showStatus('Erreur réseau.', 'error');
        };
        xhr.send(JSON.stringify({
            id_token: idToken,
            state: stateToken || '',
            gender: state.gender,
            avatar_id: state.avatar_id,
        }));
    }

    // =========================================================================
    // GITHUB — Server-side OAuth redirect
    // =========================================================================
    function triggerGitHubLogin() {
        if (!oauth.github_client_id) {
            showStatus('GitHub non configuré. Contactez l\'administrateur.', 'error');
            return;
        }
        showStatus('Redirection vers GitHub...', 'success');
        window.location.href = '/wp-json/swiftboard/v1/auth/github-login';
    }

    // =========================================================================
    // FACEBOOK — Facebook Login SDK
    // =========================================================================
    function loadFacebookSDK() {
        if (window.FB) return;
        const script = document.createElement('script');
        script.src = 'https://connect.facebook.net/en_US/sdk.js';
        script.async = true;
        script.defer = true;
        script.onload = function () {
            window.fbAsyncInit = function () {
                window.FB.init({
                    appId: oauth.facebook_app_id,
                    cookie: true,
                    xfbml: true,
                    version: 'v18.0',
                });
            };
            window.fbAsyncInit();
        };
        document.head.appendChild(script);
    }

    function triggerFacebookLogin() {
        if (!oauth.facebook_app_id) {
            showStatus('Facebook non configuré. Contactez l\'administrateur.', 'error');
            return;
        }

        // Le callback serveur gère le code, le state et la vérification du token.
        showStatus('Redirection vers Facebook...', 'success');
        window.location.href = '/wp-json/swiftboard/v1/auth/facebook-login';
    }

    // =========================================================================
    // ROUTER — Dispatch to correct provider
    // =========================================================================
    function triggerSocialLogin(provider) {
        showStatus('Connexion sécurisée avec ' + provider.toUpperCase() + '...', 'success');

        switch (provider) {
            case 'google':
                triggerGoogleLogin();
                break;
            case 'github':
                triggerGitHubLogin();
                break;
            case 'facebook':
                triggerFacebookLogin();
                break;
            default:
                showStatus('Fournisseur inconnu: ' + provider, 'error');
        }
    }

    // =========================================================================
    // SHARED HELPERS
    // =========================================================================
    function submitAuth(provider, authData) {
        const payload = {
            email: authData.email,
            email_verified: authData.email_verified !== false,
            name: authData.name || '',
            gender: state.gender,
            avatar_id: state.avatar_id,
        };

        showStatus('Création du compte...', 'success');

        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/wp-json/swiftboard/v1/auth/' + provider, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.onload = function () {
            handleAuthResponse(xhr);
        };
        xhr.onerror = function () {
            showStatus('Erreur réseau.', 'error');
        };
        xhr.send(JSON.stringify(payload));
    }

    function handleAuthResponse(xhr) {
        if (xhr.status === 200) {
            const res = JSON.parse(xhr.responseText);
            if (res && res.success) {
                showStatus('Bienvenue ! Redirection...', 'success');
                setTimeout(function () {
                    window.location.href = res.redirect || '/forums/';
                }, 800);
            } else {
                showStatus('Erreur: ' + (res.message || 'Connexion échouée.'), 'error');
            }
        } else if (xhr.status === 403) {
            showStatus('Email non vérifié par le fournisseur.', 'error');
        } else {
            showStatus('Erreur (' + xhr.status + ').', 'error');
        }
    }

    function openModal() {
        const modal = document.getElementById('sb-onboarding-modal');
        if (modal) {
            modal.removeAttribute('hidden');
            document.body.style.overflow = 'hidden';
            showStep(1);
        }
    }

    function closeModal() {
        const modal = document.getElementById('sb-onboarding-modal');
        if (modal) {
            modal.setAttribute('hidden', '');
            document.body.style.overflow = '';
        }
    }

    function showStep(num) {
        const steps = document.querySelectorAll('.sb-onb-step');
        for (let i = 0; i < steps.length; i++) {
            steps[i].setAttribute('hidden', '');
            steps[i].classList.remove('active');
        }
        const target = document.getElementById('sb-onb-step-' + num);
        if (target) {
            target.removeAttribute('hidden');
            target.classList.add('active');
        }
    }

    function highlightButton(selector, activeBtn) {
        const all = document.querySelectorAll(selector);
        for (let i = 0; i < all.length; i++) {
            all[i].classList.remove('selected');
        }
        activeBtn.classList.add('selected');
    }

    function showStatus(msg, type) {
        const box = document.getElementById('sb-onb-status');
        if (box) {
            box.textContent = msg;
            box.className = 'sb-onb-status ' + type;
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initOnboarding);
    } else {
        initOnboarding();
    }
})();
