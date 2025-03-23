# Quali'Poêle - Landing Page pour Ventes de Poêles à Bois et à Granulés

## Description

Site web de génération de leads (prospects) pour la vente et l'installation de poêles à bois et à granulés. Le site met l'accent sur l'efficacité énergétique, le confort et l'esthétique des produits, avec un design épuré et orienté conversion.

## Structure du Projet

```
Quali'Poêle/
├── css/
│   ├── landing.css        # Styles principaux optimisés
│   └── optimized.css      # Ancien fichier de styles
├── img/
│   ├── icons/             # Icônes SVG pour les arguments de vente
│   ├── hero-fallback.svg  # Image de fallback pour la section héro
│   └── logo.svg           # Logo du site
├── js/
│   └── landing.js         # JavaScript pour animations et interactions
├── video/
│   └── README.md          # Instructions pour la vidéo d'arrière-plan
├── index-conversion.html  # Page d'atterrissage optimisée
└── README.md              # Ce fichier de documentation
```

## Fonctionnalités

- **Design Minimaliste** : Interface épurée sans menu supérieur pour éviter les distractions
- **Expérience Immersive** : Vidéo d'arrière-plan de flammes avec overlay
- **Optimisation Mobile** : Responsive design pour tous les appareils
- **Optimisation de Conversion** : Formulaire optimisé pour maximiser le taux de conversion
- **Performance** : Chargement optimisé des ressources, lazy loading des images
- **Accessibilité** : Conforme aux normes d'accessibilité web

## Mise en Place

1. Téléchargez une vidéo de flammes en suivant les instructions dans `video/README.md`
2. Placez la vidéo optimisée dans le dossier `video/`
3. Personnalisez le contenu du fichier `index-conversion.html` selon vos besoins
4. Déployez l'ensemble du dossier sur votre serveur web

## Personnalisation

### Couleurs

Les variables CSS dans `css/landing.css` permettent de modifier facilement les couleurs principales :

```css
:root {
  --primary-color: #FF6B35;    /* Orange/rouge principal */
  --secondary-color: #C1121F;  /* Rouge foncé */
  --dark-color: #1D1D1D;       /* Presque noir */
  --light-color: #F8F8F8;      /* Blanc cassé */
  --accent-color: #FFB627;     /* Jaune/orange chaleureux */
}
```

### Textes et Images

Toutes les sections sont clairement commentées dans le HTML pour faciliter les modifications.

## Optimisation pour les Moteurs de Recherche (SEO)

Le site inclut :
- Balises meta optimisées
- Structure sémantique HTML5
- Données structurées pour les produits
- Optimisation des images avec attributs alt
- Balisage correct des titres (H1, H2, H3)

## Performance

Pour analyser et améliorer la performance du site :
1. Utilisez Google PageSpeed Insights
2. Suivez les recommandations spécifiques à votre hébergement
3. Optimisez davantage les images si nécessaire

## Crédits

Développé par [Votre Nom/Entreprise]

## Licence

[Précisez votre licence ici] 