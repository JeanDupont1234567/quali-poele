/**
 * Script de gestion du formulaire de qualification
 * Quali'Poêle
 */

document.addEventListener('DOMContentLoaded', function() {
    // Récupération des éléments du DOM
    const form = document.getElementById('qualification-form');
    const steps = document.querySelectorAll('.form-step');
    const stepIndicators = document.querySelectorAll('.step-indicator');
    const nextButtons = document.querySelectorAll('.btn-next');
    const prevButtons = document.querySelectorAll('.btn-prev');
    const progressBar = document.querySelector('.progress-bar-inner');
    const loadingSpinner = document.querySelector('.loading-spinner');
    const resultsSection = document.getElementById('results-section');
    const aidesContainer = document.getElementById('aides-container');
    const totalAides = document.getElementById('total-aides');
    const csrfToken = document.getElementById('csrf_token').value;
    
    // Variables de gestion des étapes
    let currentStep = 0;
    const totalSteps = steps.length;
    
    // Initialiser les événements des boutons
    initNavigation();
    
    // Initialiser la validation des champs
    initValidation();
    
    // Gérer la soumission du formulaire
    initFormSubmission();
    
    /**
     * Initialise les boutons de navigation entre les étapes
     */
    function initNavigation() {
        // Boutons "Suivant"
        nextButtons.forEach(button => {
            button.addEventListener('click', function() {
                if (validateStep(currentStep)) {
                    goToStep(currentStep + 1);
                }
            });
        });
        
        // Boutons "Précédent"
        prevButtons.forEach(button => {
            button.addEventListener('click', function() {
                goToStep(currentStep - 1);
            });
        });
        
        // Mise à jour de la barre de progression initiale
        updateProgressBar();
    }
    
    /**
     * Initialise la validation des champs du formulaire
     */
    function initValidation() {
        // Validation des champs requis au changement
        const requiredInputs = form.querySelectorAll('[required]');
        requiredInputs.forEach(input => {
            input.addEventListener('change', function() {
                if (this.value.trim() === '') {
                    this.classList.add('error');
                } else {
                    this.classList.remove('error');
                }
            });
        });
        
        // Validation des emails
        const emailInputs = form.querySelectorAll('input[type="email"]');
        emailInputs.forEach(input => {
            input.addEventListener('change', function() {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (this.value.trim() !== '' && !emailRegex.test(this.value.trim())) {
                    this.classList.add('error');
                    showError(this, 'Format d\'email invalide');
                } else {
                    this.classList.remove('error');
                    clearError(this);
                }
            });
        });
        
        // Validation des téléphones
        const phoneInputs = form.querySelectorAll('input[name="telephone"]');
        phoneInputs.forEach(input => {
            input.addEventListener('change', function() {
                const phoneRegex = /^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/;
                if (this.value.trim() !== '' && !phoneRegex.test(this.value.trim())) {
                    this.classList.add('error');
                    showError(this, 'Format de téléphone invalide');
                } else {
                    this.classList.remove('error');
                    clearError(this);
                }
            });
        });
        
        // Validation des codes postaux
        const cpInputs = form.querySelectorAll('input[name="code_postal"]');
        cpInputs.forEach(input => {
            input.addEventListener('change', function() {
                const cpRegex = /^[0-9]{5}$/;
                if (this.value.trim() !== '' && !cpRegex.test(this.value.trim())) {
                    this.classList.add('error');
                    showError(this, 'Code postal invalide (5 chiffres)');
                } else {
                    this.classList.remove('error');
                    clearError(this);
                }
            });
        });
    }
    
    /**
     * Initialise la soumission du formulaire
     */
    function initFormSubmission() {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (validateStep(currentStep)) {
                submitForm();
            }
        });
    }
    
    /**
     * Change l'étape active du formulaire
     * @param {number} stepIndex - Index de l'étape à afficher
     */
    function goToStep(stepIndex) {
        // Vérifier les limites
        if (stepIndex < 0 || stepIndex >= totalSteps) {
            return;
        }
        
        // Masquer l'étape actuelle
        steps[currentStep].classList.remove('active');
        if (stepIndicators[currentStep]) {
            stepIndicators[currentStep].classList.remove('active');
        }
        
        // Afficher la nouvelle étape
        steps[stepIndex].classList.add('active');
        if (stepIndicators[stepIndex]) {
            stepIndicators[stepIndex].classList.add('active');
        }
        
        // Mettre à jour l'étape courante
        currentStep = stepIndex;
        
        // Mettre à jour la barre de progression
        updateProgressBar();
        
        // Faire défiler vers le haut du formulaire
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    
    /**
     * Met à jour la barre de progression
     */
    function updateProgressBar() {
        const progress = ((currentStep + 1) / totalSteps) * 100;
        progressBar.style.width = `${progress}%`;
    }
    
    /**
     * Valide les champs de l'étape courante
     * @param {number} stepIndex - Index de l'étape à valider
     * @returns {boolean} - True si tous les champs sont valides
     */
    function validateStep(stepIndex) {
        const currentStepElement = steps[stepIndex];
        const requiredFields = currentStepElement.querySelectorAll('[required]');
        let isValid = true;
        
        // Réinitialiser les erreurs
        currentStepElement.querySelectorAll('.error-message').forEach(el => el.remove());
        
        // Vérifier chaque champ requis
        requiredFields.forEach(field => {
            if (field.value.trim() === '') {
                field.classList.add('error');
                showError(field, 'Ce champ est obligatoire');
                isValid = false;
            } else {
                field.classList.remove('error');
                
                // Validation spécifique pour certains types de champs
                if (field.type === 'email') {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(field.value.trim())) {
                        field.classList.add('error');
                        showError(field, 'Format d\'email invalide');
                        isValid = false;
                    }
                }
                
                if (field.name === 'telephone') {
                    const phoneRegex = /^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/;
                    if (!phoneRegex.test(field.value.trim())) {
                        field.classList.add('error');
                        showError(field, 'Format de téléphone invalide');
                        isValid = false;
                    }
                }
                
                if (field.name === 'code_postal') {
                    const cpRegex = /^[0-9]{5}$/;
                    if (!cpRegex.test(field.value.trim())) {
                        field.classList.add('error');
                        showError(field, 'Code postal invalide (5 chiffres)');
                        isValid = false;
                    }
                }
            }
        });
        
        return isValid;
    }
    
    /**
     * Affiche un message d'erreur sous un champ
     * @param {Element} field - Champ avec erreur
     * @param {string} message - Message d'erreur à afficher
     */
    function showError(field, message) {
        // Supprimer les messages d'erreur existants
        clearError(field);
        
        // Créer un nouvel élément pour le message d'erreur
        const errorElement = document.createElement('div');
        errorElement.className = 'error-message';
        errorElement.innerText = message;
        
        // Insérer après le champ
        field.parentNode.insertBefore(errorElement, field.nextSibling);
    }
    
    /**
     * Supprime les messages d'erreur d'un champ
     * @param {Element} field - Champ à nettoyer
     */
    function clearError(field) {
        const existingError = field.parentNode.querySelector('.error-message');
        if (existingError) {
            existingError.remove();
        }
    }
    
    /**
     * Soumet le formulaire via AJAX
     */
    function submitForm() {
        // Afficher le spinner de chargement
        loadingSpinner.style.display = 'flex';
        
        // Créer un objet FormData pour les données du formulaire
        const formData = new FormData(form);
        formData.append('csrf_token', csrfToken);
        
        // Envoyer la requête AJAX
        fetch('process_qualification.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // Masquer le spinner
            loadingSpinner.style.display = 'none';
            
            if (data.success) {
                // Afficher les résultats
                showResults(data);
            } else {
                // Afficher les erreurs
                alert(data.message || 'Une erreur est survenue lors du traitement de votre demande.');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            loadingSpinner.style.display = 'none';
            alert('Une erreur est survenue lors de la communication avec le serveur. Veuillez réessayer ultérieurement.');
        });
    }
    
    /**
     * Affiche les résultats d'éligibilité
     * @param {Object} data - Données de résultat du serveur
     */
    function showResults(data) {
        // Masquer le formulaire
        form.style.display = 'none';
        
        // Afficher la section des résultats
        resultsSection.style.display = 'block';
        
        // Mettre à jour le montant total des aides
        totalAides.textContent = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(data.data.montant_total);
        
        // Générer les cartes d'aides
        aidesContainer.innerHTML = '';
        
        data.data.aides.forEach(aide => {
            const aideCard = document.createElement('div');
            aideCard.className = 'aide-card';
            
            const montantFormate = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(aide.montant);
            
            aideCard.innerHTML = `
                <h3>${aide.nom}</h3>
                <div class="aide-montant">${montantFormate}</div>
                <p>${aide.description}</p>
            `;
            
            aidesContainer.appendChild(aideCard);
        });
        
        // Faire défiler vers les résultats
        resultsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
});
