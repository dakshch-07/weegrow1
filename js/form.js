document.addEventListener('DOMContentLoaded', () => {
    const contactForm = document.getElementById('contactForm');
    if (!contactForm) return;

    let csrfToken = '';

    // Fetch CSRF token dynamically on load
    async function fetchCSRF() {
        try {
            const res = await fetch('php/csrf.php', { credentials: 'include' });
            const data = await res.json();
            if (data && data.csrf_token) {
                csrfToken = data.csrf_token;
            }
        } catch (e) {
            console.warn('Failed to load CSRF token:', e);
        }
    }
    fetchCSRF();

    const toastContainer = document.createElement('div');
    toastContainer.id = 'toast-container';
    document.body.appendChild(toastContainer);

    const showToast = (message) => {
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.innerText = message;
        toastContainer.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            toast.style.transition = 'opacity 0.3s, transform 0.3s';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    };

    // Clear error on focus or change
    const inputs = contactForm.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        const clearError = () => {
            input.classList.remove('error');
            const group = input.closest('.form-group');
            if (group) group.classList.remove('has-error');
        };
        input.addEventListener('focus', clearError);
        input.addEventListener('change', clearError);
    });

    contactForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        let isValid = true;
        const formData = {
            name: contactForm.name.value.trim(),
            email: contactForm.email.value.trim(),
            phone: contactForm.phone ? contactForm.phone.value.trim() : '',
            business_type: contactForm.business_type ? contactForm.business_type.value : '',
            package: contactForm.package ? contactForm.package.value : '',
            message: contactForm.message.value.trim(),
            privacy_consent: contactForm.privacy_consent ? contactForm.privacy_consent.checked : false
        };

        const setError = (fieldName, message) => {
            const field = contactForm[fieldName];
            if (!field) return;
            field.classList.add('error');
            const group = field.closest('.form-group');
            if (group) {
                group.classList.add('has-error');
                const msgEl = group.querySelector('.error-msg');
                if (msgEl) msgEl.innerText = message;
            }
            isValid = false;
        };

        if (!formData.name) setError('name', 'Name is required');
        if (!formData.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) setError('email', 'Valid email is required');
        if (formData.phone && !/^[0-9\-\+\s]{7,15}$/.test(formData.phone)) setError('phone', 'Invalid phone number');
        if (!formData.message || formData.message.length < 10) setError('message', 'Message must be at least 10 characters');
        
        // GDPR Validation
        if (!formData.privacy_consent) {
            setError('privacy_consent', 'You must consent to privacy guidelines to submit');
        }

        if (!isValid) return;

        const submitBtn = contactForm.querySelector('button[type="submit"]');
        const spinner = submitBtn.querySelector('.spinner');
        const btnText = submitBtn.querySelector('.btn-text');
        
        submitBtn.disabled = true;
        if(spinner) spinner.style.display = 'inline-block';
        if(btnText) btnText.innerText = 'Sending...';

        try {
            const response = await fetch('php/contact.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                contactForm.style.display = 'none';
                const successMsg = document.getElementById('formSuccess');
                if (successMsg) {
                    successMsg.innerHTML = `
                        <div class="glass-modal-success animate-scale-up" style="padding: 30px; text-align: center; border-radius: 16px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(10px); box-shadow: 0 8px 32px 0 rgba(0,0,0,0.37); margin-bottom: 20px;">
                            <div style="font-size: 48px; margin-bottom: 20px;">🎉</div>
                            <h3 style="color: var(--color-cyan); margin-bottom: 10px; font-size: 22px;">Message Sent Successfully!</h3>
                            <p style="margin-bottom: 25px; opacity: 0.8; font-size: 15px; line-height: 1.6;">Thanks, ${formData.name}! We have received your inquiry and saved it securely. We will reach back to you shortly.</p>
                            <div style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                                <p style="font-size: 13px; opacity: 0.6; margin-bottom: 15px;">Want a faster response? Chat directly with us on WhatsApp:</p>
                                <a href="https://wa.me/918828965062?text=${encodeURIComponent('Hi WeeGROW team! I just submitted the form. My name is ' + formData.name + '. I am interested in the ' + (formData.package || 'custom') + ' package for my ' + (formData.business_type || 'business') + '.')}" class="btn btn-whatsapp" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-weight: 600; padding: 12px 24px; border-radius: 8px; background: #25D366; color: #fff; text-decoration: none; width: 100%; box-shadow: 0 4px 15px rgba(37,211,102,0.3);">
                                    <svg viewBox="0 0 24 24" style="width: 20px; height: 20px; fill: #fff;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.397-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                    Chat with us on WhatsApp &rarr;
                                </a>
                            </div>
                        </div>
                    `;
                    successMsg.style.display = 'block';
                    if (window.lenis && typeof window.lenis.scrollTo === 'function') {
                        window.lenis.scrollTo(successMsg, { offset: -120 });
                    } else {
                        successMsg.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            } else {
                throw new Error(result.error || 'Server error');
            }
        } catch (error) {
            console.error('Submission failed:', error);
            // Display friendly failure with fallback WhatsApp form
            contactForm.style.display = 'none';
            const successMsg = document.getElementById('formSuccess');
            if (successMsg) {
                successMsg.innerHTML = `
                    <div class="glass-modal-fallback animate-scale-up" style="padding: 30px; text-align: center; border-radius: 16px; background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.2); backdrop-filter: blur(10px); box-shadow: 0 8px 32px 0 rgba(0,0,0,0.37); margin-bottom: 20px;">
                        <div style="font-size: 48px; margin-bottom: 20px;">⚠️</div>
                        <h3 style="color: #EF4444; margin-bottom: 10px; font-size: 22px;">Connection Issue</h3>
                        <p style="margin-bottom: 25px; opacity: 0.8; font-size: 15px; line-height: 1.6;">Don't worry! Your details are safe. You can instantly submit this request directly to our founders via WhatsApp below.</p>
                        <div style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                            <a href="https://wa.me/918828965062?text=${encodeURIComponent('Hi WeeGROW founders! My name is ' + formData.name + '. I want to enquire about a website package.\n\nDetails:\nEmail: ' + formData.email + '\nPhone: ' + (formData.phone || 'N/A') + '\nBusiness: ' + (formData.business_type || 'N/A') + '\nPackage: ' + (formData.package || 'Custom') + '\nMessage: ' + formData.message)}" class="btn btn-whatsapp" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-weight: 600; padding: 14px 28px; border-radius: 8px; background: #25D366; color: #fff; text-decoration: none; font-size: 16px; box-shadow: 0 4px 15px rgba(37,211,102,0.3); width: 100%;">
                                <svg viewBox="0 0 24 24" style="width: 22px; height: 22px; fill: #fff;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.397-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                Submit via WhatsApp &rarr;
                            </a>
                        </div>
                    </div>
                `;
                successMsg.style.display = 'block';
                if (window.lenis && typeof window.lenis.scrollTo === 'function') {
                    window.lenis.scrollTo(successMsg, { offset: -120 });
                } else {
                    successMsg.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        } finally {
            if (submitBtn.disabled === true) {
                submitBtn.disabled = false;
                if(spinner) spinner.style.display = 'none';
                if(btnText) btnText.innerText = 'Send Message';
            }
        }
    });
});
