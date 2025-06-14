/**
 * BookStore Seller Validation Library
 * Advanced client-side validation for seller operations
 * Version: 2.0.0
 */

class SellerValidator {
    constructor() {
        this.config = {
            minPasswordLength: 8,
            maxFileSize: 5 * 1024 * 1024, // 5MB
            allowedImageTypes: ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'],
            maxTitleLength: 255,
            maxDescriptionLength: 2000,
            minPrice: 0.01,
            maxPrice: 99999.99,
            maxAuthorLength: 100,
            debounceDelay: 300
        };
        
        this.patterns = {
            email: /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/,
            phone: /^(\+?6?01)[0-46-9]-*[0-9]{7,8}$/,
            password: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/,
            isbn: /^(?:ISBN(?:-1[03])?:? )?(?=[0-9X]{10}$|(?=(?:[0-9]+[- ]){3})[- 0-9X]{13}$|97[89][0-9]{10}$|(?=(?:[0-9]+[- ]){4})[- 0-9]{17}$)(?:97[89][- ]?)?[0-9]{1,5}[- ]?[0-9]+[- ]?[0-9]+[- ]?[0-9X]$/,
            url: /^https?:\/\/(www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~#?&//=]*)$/
        };
        
        this.messages = {
            required: 'This field is required',
            email: 'Please enter a valid email address',
            password: 'Password must contain at least 8 characters with uppercase, lowercase, number and special character',
            passwordMatch: 'Passwords do not match',
            phone: 'Please enter a valid Malaysian phone number',
            price: 'Please enter a valid price between RM 0.01 and RM 99,999.99',
            fileSize: 'File size must be less than 5MB',
            fileType: 'Please select a valid image file (JPEG, PNG, GIF, WebP)',
            titleLength: 'Title must be between 1 and 255 characters',
            descriptionLength: 'Description cannot exceed 2000 characters',
            isbn: 'Please enter a valid ISBN',
            url: 'Please enter a valid URL'
        };
        
        this.init();
    }
    
    init() {
        this.setupRealTimeValidation();
        this.setupFormValidation();
        this.setupFileValidation();
        this.setupPasswordStrength();
    }
    
    // Real-time validation setup
    setupRealTimeValidation() {
        const inputs = document.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            const debounced = this.debounce(() => {
                this.validateField(input);
            }, this.config.debounceDelay);
            
            input.addEventListener('input', debounced);
            input.addEventListener('blur', () => this.validateField(input));
        });
    }
    
    // Form validation setup
    setupFormValidation() {
        const forms = document.querySelectorAll('form[data-validate="true"]');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                    this.showFormErrors(form);
                }
            });
        });
    }
    
    // File validation setup
    setupFileValidation() {
        const fileInputs = document.querySelectorAll('input[type="file"]');
        fileInputs.forEach(input => {
            input.addEventListener('change', (e) => {
                this.validateFile(input);
                if (input.files[0]) {
                    this.previewImage(input);
                }
            });
        });
    }
    
    // Password strength indicator
    setupPasswordStrength() {
        const passwordInputs = document.querySelectorAll('input[type="password"][data-strength="true"]');
        passwordInputs.forEach(input => {
            const strengthIndicator = this.createStrengthIndicator(input);
            input.addEventListener('input', () => {
                this.updatePasswordStrength(input, strengthIndicator);
            });
        });
    }
    
    // Validate individual field
    validateField(field) {
        const value = field.value.trim();
        const fieldType = field.dataset.validate || field.type;
        const isRequired = field.hasAttribute('required');
        
        let isValid = true;
        let message = '';
        
        // Required field check
        if (isRequired && !value) {
            isValid = false;
            message = this.messages.required;
        } else if (value) {
            // Type-specific validation
            switch (fieldType) {
                case 'email':
                    if (!this.patterns.email.test(value)) {
                        isValid = false;
                        message = this.messages.email;
                    }
                    break;
                    
                case 'password':
                    if (value.length < this.config.minPasswordLength || !this.patterns.password.test(value)) {
                        isValid = false;
                        message = this.messages.password;
                    }
                    break;
                    
                case 'confirm-password':
                    const originalPassword = document.querySelector('input[type="password"]:not([data-validate="confirm-password"])');
                    if (originalPassword && value !== originalPassword.value) {
                        isValid = false;
                        message = this.messages.passwordMatch;
                    }
                    break;
                    
                case 'phone':
                    if (!this.patterns.phone.test(value)) {
                        isValid = false;
                        message = this.messages.phone;
                    }
                    break;
                    
                case 'price':
                    const price = parseFloat(value);
                    if (isNaN(price) || price < this.config.minPrice || price > this.config.maxPrice) {
                        isValid = false;
                        message = this.messages.price;
                    }
                    break;
                    
                case 'title':
                    if (value.length > this.config.maxTitleLength) {
                        isValid = false;
                        message = this.messages.titleLength;
                    }
                    break;
                    
                case 'description':
                    if (value.length > this.config.maxDescriptionLength) {
                        isValid = false;
                        message = this.messages.descriptionLength;
                    }
                    break;
                    
                case 'isbn':
                    if (!this.patterns.isbn.test(value.replace(/[- ]/g, ''))) {
                        isValid = false;
                        message = this.messages.isbn;
                    }
                    break;
                    
                case 'url':
                    if (!this.patterns.url.test(value)) {
                        isValid = false;
                        message = this.messages.url;
                    }
                    break;
            }
        }
        
        // Custom validation
        if (isValid && field.dataset.customValidation) {
            const customResult = this.runCustomValidation(field, value);
            isValid = customResult.isValid;
            message = customResult.message;
        }
        
        this.displayFieldValidation(field, isValid, message);
        return isValid;
    }
    
    // Validate file input
    validateFile(input) {
        const file = input.files[0];
        if (!file) return true;
        
        let isValid = true;
        let message = '';
        
        // File size validation
        if (file.size > this.config.maxFileSize) {
            isValid = false;
            message = this.messages.fileSize;
        }
        
        // File type validation
        if (isValid && !this.config.allowedImageTypes.includes(file.type)) {
            isValid = false;
            message = this.messages.fileType;
        }
        
        this.displayFieldValidation(input, isValid, message);
        return isValid;
    }
    
    // Validate entire form
    validateForm(form) {
        const fields = form.querySelectorAll('input, textarea, select');
        let isFormValid = true;
        
        fields.forEach(field => {
            if (!this.validateField(field)) {
                isFormValid = false;
            }
        });
        
        // File inputs validation
        const fileInputs = form.querySelectorAll('input[type="file"]');
        fileInputs.forEach(input => {
            if (!this.validateFile(input)) {
                isFormValid = false;
            }
        });
        
        return isFormValid;
    }
    
    // Display field validation result
    displayFieldValidation(field, isValid, message) {
        const fieldGroup = field.closest('.form-group, .mb-3, .form-floating') || field.parentElement;
        let errorElement = fieldGroup.querySelector('.validation-error');
        
        // Remove existing validation classes
        field.classList.remove('is-valid', 'is-invalid');
        
        if (!isValid && message) {
            // Add error styling
            field.classList.add('is-invalid');
            
            // Create or update error message
            if (!errorElement) {
                errorElement = document.createElement('div');
                errorElement.className = 'validation-error invalid-feedback';
                fieldGroup.appendChild(errorElement);
            }
            
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        } else if (isValid && field.value.trim()) {
            // Add success styling
            field.classList.add('is-valid');
            
            if (errorElement) {
                errorElement.style.display = 'none';
            }
        } else {
            // Neutral state
            if (errorElement) {
                errorElement.style.display = 'none';
            }
        }
    }
    
    // Show form-level errors
    showFormErrors(form) {
        const firstInvalidField = form.querySelector('.is-invalid');
        if (firstInvalidField) {
            firstInvalidField.focus();
            firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        // Show toast notification
        this.showToast('Please correct the errors in the form', 'error');
    }
    
    // Create password strength indicator
    createStrengthIndicator(passwordInput) {
        const container = passwordInput.parentElement;
        
        const strengthContainer = document.createElement('div');
        strengthContainer.className = 'password-strength mt-2';
        
        const strengthBar = document.createElement('div');
        strengthBar.className = 'strength-bar';
        
        const strengthText = document.createElement('div');
        strengthText.className = 'strength-text small';
        
        strengthContainer.appendChild(strengthBar);
        strengthContainer.appendChild(strengthText);
        container.appendChild(strengthContainer);
        
        return { bar: strengthBar, text: strengthText };
    }
    
    // Update password strength
    updatePasswordStrength(input, indicator) {
        const password = input.value;
        const strength = this.calculatePasswordStrength(password);
        
        // Update strength bar
        indicator.bar.className = `strength-bar strength-${strength.level}`;
        indicator.bar.style.width = `${strength.percentage}%`;
        
        // Update strength text
        indicator.text.textContent = strength.text;
        indicator.text.className = `strength-text small text-${strength.color}`;
    }
    
    // Calculate password strength
    calculatePasswordStrength(password) {
        let score = 0;
        let feedback = [];
        
        if (password.length >= 8) score += 25;
        else feedback.push('At least 8 characters');
        
        if (/[a-z]/.test(password)) score += 25;
        else feedback.push('Lowercase letter');
        
        if (/[A-Z]/.test(password)) score += 25;
        else feedback.push('Uppercase letter');
        
        if (/\d/.test(password)) score += 25;
        else feedback.push('Number');
        
        if (/[@$!%*?&]/.test(password)) score += 25;
        else feedback.push('Special character');
        
        // Bonus points
        if (password.length >= 12) score += 10;
        if (/[^a-zA-Z0-9@$!%*?&]/.test(password)) score += 5;
        
        let level, text, color;
        
        if (score >= 100) {
            level = 'excellent';
            text = 'Excellent password!';
            color = 'success';
        } else if (score >= 75) {
            level = 'good';
            text = 'Good password';
            color = 'success';
        } else if (score >= 50) {
            level = 'medium';
            text = 'Medium strength';
            color = 'warning';
        } else if (score >= 25) {
            level = 'weak';
            text = 'Weak password';
            color = 'danger';
        } else {
            level = 'very-weak';
            text = 'Very weak password';
            color = 'danger';
        }
        
        if (feedback.length > 0) {
            text += ` (Missing: ${feedback.join(', ')})`;
        }
        
        return {
            score,
            percentage: Math.min(score, 100),
            level,
            text,
            color
        };
    }
    
    // Preview uploaded image
    previewImage(input) {
        const file = input.files[0];
        if (!file) return;
        
        const reader = new FileReader();
        reader.onload = (e) => {
            let preview = input.parentElement.querySelector('.image-preview');
            
            if (!preview) {
                preview = document.createElement('div');
                preview.className = 'image-preview mt-3';
                input.parentElement.appendChild(preview);
            }
            
            preview.innerHTML = `
                <div class="preview-container">
                    <img src="${e.target.result}" alt="Preview" class="img-fluid rounded shadow" style="max-height: 200px;">
                    <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2" onclick="removeImagePreview(this)">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            `;
        };
        
        reader.readAsDataURL(file);
    }
    
    // Custom validation runner
    runCustomValidation(field, value) {
        const validationType = field.dataset.customValidation;
        
        switch (validationType) {
            case 'unique-email':
                return this.validateUniqueEmail(value);
            case 'book-exists':
                return this.validateBookExists(value);
            case 'positive-number':
                return this.validatePositiveNumber(value);
            default:
                return { isValid: true, message: '' };
        }
    }
    
    // Async email uniqueness validation
    async validateUniqueEmail(email) {
        try {
            const response = await fetch('check_email_availability.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `email=${encodeURIComponent(email)}`
            });
            
            const result = await response.json();
            return {
                isValid: result.available,
                message: result.available ? '' : 'Email is already registered'
            };
        } catch (error) {
            return { isValid: true, message: '' }; // Fail silently for network errors
        }
    }
    
    // Validate positive number
    validatePositiveNumber(value) {
        const num = parseFloat(value);
        return {
            isValid: !isNaN(num) && num > 0,
            message: num <= 0 ? 'Must be a positive number' : ''
        };
    }
    
    // Utility: Debounce function
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Show toast notification
    showToast(message, type = 'info') {
        // Create toast container if it doesn't exist
        let toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            document.body.appendChild(toastContainer);
        }
        
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type} border-0`;
        toast.setAttribute('role', 'alert');
        
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        toastContainer.appendChild(toast);
        
        // Show toast
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        // Remove toast element after it's hidden
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }
    
    // Public method to validate a specific field
    validateSingleField(fieldId) {
        const field = document.getElementById(fieldId);
        if (field) {
            return this.validateField(field);
        }
        return false;
    }
    
    // Public method to validate form by ID
    validateFormById(formId) {
        const form = document.getElementById(formId);
        if (form) {
            return this.validateForm(form);
        }
        return false;
    }
    
    // Public method to reset form validation
    resetFormValidation(formId) {
        const form = document.getElementById(formId);
        if (form) {
            const fields = form.querySelectorAll('input, textarea, select');
            fields.forEach(field => {
                field.classList.remove('is-valid', 'is-invalid');
                const errorElement = field.parentElement.querySelector('.validation-error');
                if (errorElement) {
                    errorElement.style.display = 'none';
                }
            });
        }
    }
}

// Advanced Form Handlers
class SellerFormHandlers {
    constructor(validator) {
        this.validator = validator;
        this.init();
    }
    
    init() {
        this.setupBookForm();
        this.setupRegistrationForm();
        this.setupLoginForm();
        this.setupProfileForm();
    }
    
    setupBookForm() {
        const bookForm = document.getElementById('bookForm');
        if (!bookForm) return;
        
        // Auto-generate slug from title
        const titleInput = bookForm.querySelector('#book_title');
        const slugPreview = bookForm.querySelector('#slug-preview');
        
        if (titleInput && slugPreview) {
            titleInput.addEventListener('input', () => {
                const slug = this.generateSlug(titleInput.value);
                slugPreview.textContent = slug;
            });
        }
        
        // Price formatting
        const priceInput = bookForm.querySelector('#book_price');
        if (priceInput) {
            priceInput.addEventListener('input', (e) => {
                let value = e.target.value.replace(/[^\d.]/g, '');
                const parts = value.split('.');
                if (parts.length > 2) {
                    value = parts[0] + '.' + parts.slice(1).join('');
                }
                if (parts[1] && parts[1].length > 2) {
                    value = parts[0] + '.' + parts[1].substring(0, 2);
                }
                e.target.value = value;
            });
        }
        
        // Character counters
        this.setupCharacterCounters(bookForm);
    }
    
    setupRegistrationForm() {
        const regForm = document.getElementById('registrationForm');
        if (!regForm) return;
        
        // Real-time email availability check
        const emailInput = regForm.querySelector('#seller_email');
        if (emailInput) {
            const debouncedCheck = this.validator.debounce(async () => {
                if (emailInput.value && this.validator.patterns.email.test(emailInput.value)) {
                    await this.checkEmailAvailability(emailInput);
                }
            }, 500);
            
            emailInput.addEventListener('input', debouncedCheck);
        }
    }
    
    setupLoginForm() {
        const loginForm = document.getElementById('loginForm');
        if (!loginForm) return;
        
        // Remember me functionality
        const rememberMe = loginForm.querySelector('#remember_me');
        const emailInput = loginForm.querySelector('#seller_email');
        
        if (rememberMe && emailInput) {
            // Load saved email
            const savedEmail = localStorage.getItem('seller_email');
            if (savedEmail) {
                emailInput.value = savedEmail;
                rememberMe.checked = true;
            }
            
            // Save email on form submit
            loginForm.addEventListener('submit', () => {
                if (rememberMe.checked) {
                    localStorage.setItem('seller_email', emailInput.value);
                } else {
                    localStorage.removeItem('seller_email');
                }
            });
        }
    }
    
    setupProfileForm() {
        const profileForm = document.getElementById('profileForm');
        if (!profileForm) return;
        
        // Profile image preview with crop functionality
        const imageInput = profileForm.querySelector('#profile_image');
        if (imageInput) {
            imageInput.addEventListener('change', () => {
                this.setupImageCropper(imageInput);
            });
        }
    }
    
    setupCharacterCounters(form) {
        const fieldsWithCounters = form.querySelectorAll('[data-max-length]');
        
        fieldsWithCounters.forEach(field => {
            const maxLength = parseInt(field.dataset.maxLength);
            const counter = document.createElement('div');
            counter.className = 'character-counter small text-muted mt-1';
            field.parentElement.appendChild(counter);
            
            const updateCounter = () => {
                const remaining = maxLength - field.value.length;
                counter.textContent = `${field.value.length}/${maxLength} characters`;
                counter.className = `character-counter small mt-1 ${remaining < 10 ? 'text-danger' : 'text-muted'}`;
            };
            
            field.addEventListener('input', updateCounter);
            updateCounter(); // Initial update
        });
    }
    
    generateSlug(title) {
        return title
            .toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '');
    }
    
    async checkEmailAvailability(emailInput) {
        const email = emailInput.value;
        const indicator = emailInput.parentElement.querySelector('.availability-indicator') || 
                          this.createAvailabilityIndicator(emailInput);
        
        indicator.innerHTML = '<i class="spinner-border spinner-border-sm text-primary" role="status"></i> Checking...';
        
        try {
            const response = await fetch('check_email_availability.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `email=${encodeURIComponent(email)}`
            });
            
            const result = await response.json();
            
            if (result.available) {
                indicator.innerHTML = '<i class="bi bi-check-circle text-success"></i> Available';
                emailInput.classList.remove('is-invalid');
                emailInput.classList.add('is-valid');
            } else {
                indicator.innerHTML = '<i class="bi bi-x-circle text-danger"></i> Already taken';
                emailInput.classList.remove('is-valid');
                emailInput.classList.add('is-invalid');
            }
        } catch (error) {
            indicator.innerHTML = '<i class="bi bi-exclamation-triangle text-warning"></i> Check failed';
        }
    }
    
    createAvailabilityIndicator(input) {
        const indicator = document.createElement('div');
        indicator.className = 'availability-indicator small mt-1';
        input.parentElement.appendChild(indicator);
        return indicator;
    }
    
    setupImageCropper(input) {
        const file = input.files[0];
        if (!file) return;
        
        // Create modal for image cropping
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Crop Image</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="image-crop-container">
                            <img id="cropImage" style="max-width: 100%;">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="cropConfirm">Crop & Save</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        const reader = new FileReader();
        reader.onload = (e) => {
            const cropImage = modal.querySelector('#cropImage');
            cropImage.src = e.target.result;
            
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            // Initialize cropper (you would need to include Cropper.js library)
            // const cropper = new Cropper(cropImage, { aspectRatio: 1 });
        };
        
        reader.readAsDataURL(file);
    }
}

// AJAX Form Submission Handler
class AjaxFormHandler {
    constructor(validator) {
        this.validator = validator;
        this.init();
    }
    
    init() {
        const ajaxForms = document.querySelectorAll('form[data-ajax="true"]');
        ajaxForms.forEach(form => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleAjaxSubmit(form);
            });
        });
    }
    
    async handleAjaxSubmit(form) {
        // Validate form first
        if (!this.validator.validateForm(form)) {
            this.validator.showFormErrors(form);
            return;
        }
        
        const submitButton = form.querySelector('[type="submit"]');
        const originalText = submitButton.textContent;
        
        // Show loading state
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="spinner-border spinner-border-sm me-2"></i>Processing...';
        
        try {
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: form.method || 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.validator.showToast(result.message || 'Operation completed successfully', 'success');
                
                // Handle redirect
                if (result.redirect) {
                    setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 1500);
                }
                
                // Reset form if specified
                if (result.resetForm) {
                    form.reset();
                    this.validator.resetFormValidation(form.id);
                }
            } else {
                this.validator.showToast(result.message || 'Operation failed', 'error');
                
                // Handle field-specific errors
                if (result.errors) {
                    this.showFieldErrors(form, result.errors);
                }
            }
        } catch (error) {
            this.validator.showToast('Network error occurred', 'error');
        } finally {
            // Restore button state
            submitButton.disabled = false;
            submitButton.textContent = originalText;
        }
    }
    
    showFieldErrors(form, errors) {
        Object.keys(errors).forEach(fieldName => {
            const field = form.querySelector(`[name="${fieldName}"]`);
            if (field) {
                this.validator.displayFieldValidation(field, false, errors[fieldName]);
            }
        });
    }
}

// Utility Functions
const SellerUtils = {
    // Format currency
    formatCurrency(amount) {
        return new Intl.NumberFormat('ms-MY', {
            style: 'currency',
            currency: 'MYR'
        }).format(amount);
    },
    
    // Format date
    formatDate(date) {
        return new Intl.DateTimeFormat('en-MY', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        }).format(new Date(date));
    },
    
    // Generate random string
    generateRandomString(length = 10) {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let result = '';
        for (let i = 0; i < length; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
    },
    
    // Sanitize HTML
    sanitizeHtml(html) {
        const temp = document.createElement('div');
        temp.textContent = html;
        return temp.innerHTML;
    }
};

// Global functions for backward compatibility
function removeImagePreview(button) {
    const preview = button.closest('.image-preview');
    if (preview) {
        const input = preview.parentElement.querySelector('input[type="file"]');
        if (input) input.value = '';
        preview.remove();
    }
}

// Initialize when DOM is loaded
let sellerValidator, sellerFormHandlers, ajaxFormHandler;

document.addEventListener('DOMContentLoaded', () => {
    sellerValidator = new SellerValidator();
    sellerFormHandlers = new SellerFormHandlers(sellerValidator);
    ajaxFormHandler = new AjaxFormHandler(sellerValidator);
    
    // Add CSS for validation styles
    const style = document.createElement('style');
    style.textContent = `
        .validation-error {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: #dc3545;
        }
        
        .strength-bar {
            height: 4px;
            background-color: #e9ecef;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
        
        .strength-very-weak { background-color: #dc3545; }
        .strength-weak { background-color: #fd7e14; }
        .strength-medium { background-color: #ffc107; }
        .strength-good { background-color: #20c997; }
        .strength-excellent { background-color: #28a745; }
        
        .image-preview {
            position: relative;
            display: inline-block;
        }
        
        .preview-container {
            position: relative;
        }
        
        .character-counter {
            text-align: right;
        }
        
        .availability-indicator {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .image-crop-container {
            max-height: 400px;
            overflow: hidden;
        }
        
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }
    `;
    document.head.appendChild(style);
});

// Export for module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { SellerValidator, SellerFormHandlers, AjaxFormHandler, SellerUtils };
}