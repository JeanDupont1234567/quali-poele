document.addEventListener('DOMContentLoaded', function() {
    console.log("Document chargé, initialisation des fonctions...");
    
    // Vérifier d'abord si les éléments principaux existent
    const formContainer = document.querySelector('.form-container');
    if (formContainer) {
        // Rendre visible directement au cas où
        formContainer.style.opacity = '1';
        formContainer.style.transform = 'translateY(0)';
        formContainer.classList.add('visible');
    }
    
    // Initialiser les animations et comportements seulement si les éléments existent
    try {
        // Fonctions principales
        initHeroVideo();
        initSmoothScroll();
        initFormValidation();
        initScrollAnimations();
        initVisitorCounter();
        initFormProgress();
        
        // Animation des entrées des cartes d'arguments
        animateArgumentCards();
        
        console.log("Initialisation terminée.");
    } catch (error) {
        console.error("Erreur lors de l'initialisation:", error);
    }
});

// Animation des cartes d'argument
function animateArgumentCards() {
    const argumentCards = document.querySelectorAll('.argument-card');
    argumentCards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                card.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100);
        }, index * 150);
    });
}

// Gestion optimisée de la vidéo d'arrière-plan
function initHeroVideo() {
    const video = document.querySelector('.hero-video');
    if (!video) {
        console.error("Élément vidéo non trouvé");
        return;
    }
    
    // Vérifie que la source vidéo existe et affiche le chemin pour le débogage
    const source = video.querySelector('source');
    if (source) {
        console.log("Vidéo trouvée, source:", source.src);
        
        // Forcer le chargement et la lecture de la vidéo
        video.load();
        
        // Créer un gestionnaire pour l'événement d'erreur
        video.onerror = function(e) {
            console.error("Erreur de chargement vidéo:", e);
        };
        
        // Gestionnaire pour canplaythrough
        video.addEventListener('canplaythrough', function() {
            console.log("Vidéo chargée et prête à être lue");
            video.classList.add('loaded');
            document.querySelector('.hero-content')?.classList.add('visible');
        });
        
        // Tentative de lecture automatique
        const playPromise = video.play();
        if (playPromise !== undefined) {
            playPromise
                .then(() => console.log("Lecture de la vidéo démarrée avec succès"))
                .catch(error => console.error("Erreur lors de la lecture automatique:", error));
        }
    } else {
        console.error("Source vidéo non trouvée");
    }
}

// Défilement fluide pour les ancres
function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                window.scrollTo({
                    top: targetElement.offsetTop - 60,
                    behavior: 'smooth'
                });
            }
        });
    });
}

// Validation du formulaire simplifiée
function initFormValidation() {
    const form = document.querySelector('.contact-form');
    if (!form) return;
    
    // Ajouter la classe 'input-focused' quand un champ est en focus
    form.querySelectorAll('input, textarea, select').forEach(field => {
        field.addEventListener('focus', () => {
            const formGroup = field.closest('.form-group');
            if (formGroup) {
                formGroup.classList.add('input-focused');
                // Ajouter une classe spéciale pour le champ projet
                if (field.id === 'message') {
                    formGroup.classList.add('project-focused');
                }
            }
        });
        
        field.addEventListener('blur', () => {
            const formGroup = field.closest('.form-group');
            if (formGroup) {
                formGroup.classList.remove('input-focused');
                // Retirer la classe spéciale pour le champ projet
                if (field.id === 'message') {
                    formGroup.classList.remove('project-focused');
                }
            }
        });
        
        // Gérer l'état rempli pour le champ projet
        if (field.id === 'message') {
            field.addEventListener('input', () => {
                const formGroup = field.closest('.form-group');
                if (formGroup) {
                    if (field.value.trim() !== '') {
                        formGroup.classList.add('filled');
                    } else {
                        formGroup.classList.remove('filled');
                    }
                }
            });
        }
    });
    
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Vérifier la validité
        let isValid = true;
        form.querySelectorAll('[required]').forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('error');
            } else {
                field.classList.remove('error');
            }
        });
        
        if (!isValid) {
            console.log("Formulaire invalide");
            return;
        }
        
        // Désactiver le bouton pendant l'envoi
        const submitButton = form.querySelector('.submit-button');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Envoi en cours...';
        }
        
        try {
            // Envoyer les données via Formspree
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                // Afficher le message de succès
                form.innerHTML = `
                    <div class="success-message">
                        <div class="success-confetti" id="confetti-container"></div>
                        <svg viewBox="0 0 24 24" width="80" height="80">
                            <circle cx="12" cy="12" r="11" fill="none" stroke="#EEEEEE" stroke-width="1"/>
                            <path class="checkmark-path" fill="none" stroke="#FF6B35" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" d="M6,12 L10,16 L18,8"/>
                        </svg>
                        <h3>Demande envoyée avec succès !</h3>
                        <p>Un artisan certifié près de chez vous vous contactera dans les plus brefs délais.<br>Merci de votre confiance.</p>
                    </div>
                `;
                
                // Créer les confettis
                createConfetti();
                
                // Réinitialiser le formulaire
                form.reset();
            } else {
                throw new Error('Erreur lors de l\'envoi du formulaire');
            }
        } catch (error) {
            console.error('Erreur:', error);
            // Afficher un message d'erreur à l'utilisateur
            const errorMessage = document.createElement('div');
            errorMessage.className = 'error-message';
            errorMessage.innerHTML = `
                <p>Une erreur est survenue lors de l'envoi du formulaire. Veuillez réessayer ou nous contacter directement.</p>
            `;
            form.insertBefore(errorMessage, submitButton);
            
            // Réactiver le bouton
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = 'RECEVOIR MON DEVIS GRATUIT';
            }
        }
    });
}

// Animation des éléments au défilement - simplifiée
function initScrollAnimations() {
    const animatedElements = document.querySelectorAll('.animate-on-scroll, .benefit-card, .product-category, .form-container');
    
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });
        
        animatedElements.forEach(element => observer.observe(element));
    } else {
        // Fallback pour les navigateurs qui ne supportent pas IntersectionObserver
        animatedElements.forEach(element => element.classList.add('visible'));
    }
}

// Compteur de visiteurs optimisé
function initVisitorCounter() {
    const visitorCount = document.getElementById('visitor-count');
    if (!visitorCount) return;
    
    // Ajustement du nombre de visiteurs en fonction de l'heure de la journée
    const currentHour = new Date().getHours();
    
    // Calcul du nombre de visiteurs selon l'heure
    let baseCount;
    if (currentHour >= 22 || currentHour < 6) {
        baseCount = Math.floor(Math.random() * 5) + 5; // 5-10 visiteurs (nuit)
    } else if ((currentHour >= 6 && currentHour < 9) || (currentHour >= 18 && currentHour < 20)) {
        baseCount = Math.floor(Math.random() * 8) + 10; // 10-18 visiteurs (matin tôt/début de soirée)
    } else if ((currentHour >= 9 && currentHour < 12) || (currentHour >= 14 && currentHour < 18)) {
        baseCount = Math.floor(Math.random() * 10) + 15; // 15-25 visiteurs (matin/après-midi)
    } else {
        baseCount = Math.floor(Math.random() * 7) + 10; // 10-17 visiteurs (midi/soirée)
    }
    
    visitorCount.textContent = baseCount;
    
    // Mise à jour du compteur toutes les 30 secondes avec des variations
    setInterval(() => {
        const variation = Math.floor(Math.random() * 3) - 1; // -1, 0, ou +1
        let newCount = Math.max(5, baseCount + variation);
        
        visitorCount.style.transition = 'transform 0.3s ease, color 0.3s ease';
        visitorCount.style.color = variation > 0 ? '#4CAF50' : (variation < 0 ? '#FF6B35' : '#FFB627');
        visitorCount.style.transform = variation > 0 ? 'scale(1.2)' : (variation < 0 ? 'scale(0.9)' : 'scale(1)');
        visitorCount.textContent = newCount;
        
        setTimeout(() => {
            visitorCount.style.color = '#FFB627';
            visitorCount.style.transform = 'scale(1)';
        }, 500);
    }, 30000);
}

// Barre de progression du formulaire - optimisée
function initFormProgress() {
    const form = document.getElementById('contact-form');
    const progressBar = document.getElementById('form-progress');
    
    if (!form || !progressBar) return;
    
    // Identifier tous les champs du formulaire sauf le champ optionnel
    const formFields = Array.from(form.querySelectorAll('input:not([type="hidden"]):not([type="submit"]), select, textarea'))
        .filter(field => field.id !== 'message');
    
    // Champ de projet optionnel traité séparément
    const optionalField = document.getElementById('message');
    
    // Fonction pour mettre à jour la barre de progression
    const updateProgress = () => {
        // Compter les champs requis remplis
        const filledRequiredFields = formFields.filter(field => {
            if (field.type === 'checkbox') return field.checked;
            return field.value.trim() !== '';
        }).length;
        
        // Calculer le pourcentage
        const percentage = Math.floor((filledRequiredFields / formFields.length) * 100);
        progressBar.style.width = `${percentage}%`;
        
        // Ajouter les classes appropriées en fonction du pourcentage
        progressBar.classList.remove('milestone-25', 'milestone-50', 'milestone-75', 'milestone-100');
        
        if (percentage >= 100) {
            progressBar.classList.add('milestone-100');
            // Ajouter un message de complétion
            if (!document.querySelector('.form-complete-message')) {
                const completeMessage = document.createElement('div');
                completeMessage.className = 'form-complete-message';
                completeMessage.innerHTML = '<strong>🎉 Parfait ! Vous êtes à un clic de recevoir votre devis personnalisé !</strong>';
                
                const submitButton = form.querySelector('.submit-button');
                if (submitButton) {
                    submitButton.classList.add('pulse-button');
                    submitButton.parentNode.insertBefore(completeMessage, submitButton);
                }
            }
        } else if (percentage >= 75) {
            progressBar.classList.add('milestone-75');
        } else if (percentage >= 50) {
            progressBar.classList.add('milestone-50');
        } else if (percentage >= 25) {
            progressBar.classList.add('milestone-25');
        }
        
        // Gérer les classes des champs
        formFields.forEach(field => {
            const fieldGroup = field.closest('.form-group');
            if (!fieldGroup) return;
            
            if ((field.type === 'checkbox' && field.checked) || 
                (field.type !== 'checkbox' && field.value.trim() !== '')) {
                fieldGroup.classList.add('filled');
            } else {
                fieldGroup.classList.remove('filled');
            }
        });
        
        // Traiter le champ optionnel séparément
        if (optionalField) {
            const fieldGroup = optionalField.closest('.form-group');
            if (fieldGroup) {
                if (optionalField.value.trim() !== '') {
                    fieldGroup.classList.add('filled');
                } else {
                    fieldGroup.classList.remove('filled');
                }
            }
        }
    };
    
    // Attacher les événements à chaque champ du formulaire
    [...formFields, optionalField].filter(Boolean).forEach(field => {
        field.addEventListener('input', updateProgress);
        field.addEventListener('change', updateProgress);
    });
    
    // Initialisation
    updateProgress();
}

// Fonction pour créer des confettis
function createConfetti() {
    const container = document.getElementById('confetti-container');
    if (!container) return;
    
    const colors = ['#FF6B35', '#C1121F', '#FFB627', '#4CAF50', '#1D1D1D'];
    const shapes = ['square', 'circle'];
    
    // Créer 100 confettis
    for (let i = 0; i < 100; i++) {
        const confetti = document.createElement('div');
        confetti.className = 'confetti';
        
        // Propriétés aléatoires
        const color = colors[Math.floor(Math.random() * colors.length)];
        const shape = shapes[Math.floor(Math.random() * shapes.length)];
        const size = Math.random() * 10 + 5;
        const left = Math.random() * 100;
        const tx = Math.random() * 100 - 50; // déplacement en vw
        const r = Math.random() * 360; // rotation en degrés
        const duration = Math.random() * 3 + 2;
        const delay = Math.random() * 1.5;
        
        // Appliquer les styles
        Object.assign(confetti.style, {
            backgroundColor: color,
            width: `${size}px`,
            height: `${size}px`,
            left: `${left}%`,
            borderRadius: shape === 'circle' ? '50%' : '0',
            animationDuration: `${duration}s`,
            animationDelay: `${delay}s`
        });
        
        // Variables CSS pour l'animation
        confetti.style.setProperty('--tx', tx);
        confetti.style.setProperty('--r', r);
        
        container.appendChild(confetti);
    }
} 