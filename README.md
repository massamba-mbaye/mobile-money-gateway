# Plugin de Passerelle de Paiement Mobile Money pour WooCommerce

Un plugin WordPress/WooCommerce qui permet d'accepter les paiements via **Wave** et **Orange Money** dans votre boutique en ligne.

## 🌟 Fonctionnalités

- ✅ **Support Wave Money** - Paiements via Wave (Sénégal)
- ✅ **Support Orange Money** - Paiements via Orange Money (multi-pays)
- ✅ **Interface utilisateur intuitive** - Champs de saisie avec validation en temps réel
- ✅ **Compatibilité blocs WooCommerce** - Fonctionne avec le nouveau checkout en blocs
- ✅ **Gestion des webhooks** - Confirmation automatique des paiements
- ✅ **Mode test et production** - Environnements séparés pour les tests
- ✅ **Multi-devises** - Support XOF (FCFA), EUR, USD
- ✅ **Validation des numéros** - Validation selon les formats locaux
- ✅ **Logs de débogage** - Suivi détaillé des transactions
- ✅ **Remboursements** - Gestion des remboursements (selon l'API)

## 📋 Prérequis

- **WordPress** 5.0 ou supérieur
- **WooCommerce** 3.0 ou supérieur
- **PHP** 7.4 ou supérieur
- **Comptes développeur** chez Wave et/ou Orange Money
- **Certificat SSL** recommandé pour la production

## 🚀 Installation

### Installation automatique

1. Dans votre admin WordPress, allez dans **Extensions > Ajouter**
2. Recherchez "Mobile Money Gateway"
3. Cliquez sur **Installer** puis **Activer**

### Installation manuelle

1. Téléchargez le plugin
2. Décompressez le fichier ZIP
3. Uploadez le dossier dans `/wp-content/plugins/`
4. Activez le plugin dans l'admin WordPress

## ⚙️ Configuration

### 1. Configuration Wave

1. Allez dans **WooCommerce > Réglages > Paiements**
2. Cliquez sur **Wave** pour configurer
3. Renseignez vos informations :
   - **Clé API** : Votre clé API Wave
   - **Clé Secrète** : Votre clé secrète Wave
   - **Mode Test** : Activez pour les tests
   - **Champ téléphone** : Activez pour demander le numéro

#### Obtention des clés Wave
```
1. Inscrivez-vous sur le portail développeur Wave
2. Créez une nouvelle application
3. Copiez vos clés API et secrète
4. Configurez l'URL de webhook : votre-site.com/?wc-api=wc_wave_gateway
```

### 2. Configuration Orange Money

1. Allez dans **WooCommerce > Réglages > Paiements**
2. Cliquez sur **Orange Money** pour configurer
3. Renseignez vos informations :
   - **Clé API** : Votre clé API Orange Money
   - **Clé Secrète** : Votre clé secrète
   - **ID Marchand** : Votre identifiant marchand
   - **Code Pays** : Sélectionnez votre pays
   - **Mode Test** : Activez pour les tests

#### Obtention des clés Orange Money
```
1. Contactez Orange Money Business dans votre pays
2. Demandez l'accès à l'API de paiement
3. Obtenez vos identifiants (API Key, Secret, Merchant ID)
4. Configurez l'URL de webhook : votre-site.com/?wc-api=wc_orange_money_gateway
```

### 3. Configuration des devises

Assurez-vous que votre boutique utilise une devise supportée :
- **XOF (FCFA)** - Recommandé pour l'Afrique de l'Ouest
- **EUR** - Pour les transactions internationales
- **USD** - Support limité selon les pays

## 🛠️ Structure du Plugin

```
mobile-money-gateway/
├── mobile-money-gateway.php          # Fichier principal
├── includes/
│   ├── class-wc-mobile-money-base.php    # Classe de base
│   ├── class-wc-wave-gateway.php         # Passerelle Wave
│   ├── class-wc-orange-money-gateway.php # Passerelle Orange Money
│   ├── class-wave-blocks.php             # Blocs Wave
│   └── class-orange-money-blocks.php     # Blocs Orange Money
├── assets/
│   ├── js/
│   │   ├── admin-settings.js         # Scripts admin
│   │   ├── checkout.js              # Scripts checkout
│   │   ├── wave-checkout.js         # Blocs Wave
│   │   └── orange-money-checkout.js # Blocs Orange Money
│   ├── css/
│   │   └── styles.css              # Styles CSS
│   └── images/
│       ├── wave-logo.png           # Logo Wave
│       └── orange-money-logo.png   # Logo Orange Money
├── languages/                       # Fichiers de traduction
└── README.md                       # Documentation
```

## 🔧 Développement et Personnalisation

### Hooks disponibles

#### Actions
```php
// Avant le traitement du paiement
do_action('mmg_before_payment_processing', $order, $gateway_id);

// Après confirmation du paiement
do_action('mmg_payment_confirmed', $order, $transaction_data);

// En cas d'échec du paiement
do_action('mmg_payment_failed', $order, $error_data);
```

#### Filtres
```php
// Modifier les données envoyées à l'API
$payment_data = apply_filters('mmg_payment_data', $payment_data, $order, $gateway_id);

// Personnaliser la validation du numéro
$is_valid = apply_filters('mmg_validate_phone_number', $is_valid, $phone_number, $gateway_id);

// Modifier les devises supportées
$currencies = apply_filters('mmg_supported_currencies', $currencies, $gateway_id);
```

### Exemple de personnalisation

```php
// Ajouter une validation personnalisée
add_filter('mmg_validate_phone_number', function($is_valid, $phone_number, $gateway_id) {
    if ($gateway_id === 'wave_gateway') {
        // Validation personnalisée pour Wave
        return preg_match('/^(\+221|221)?[73][0-9]{7}$/', $phone_number);
    }
    return $is_valid;
}, 10, 3);

// Modifier les données de paiement
add_filter('mmg_payment_data', function($data, $order, $gateway_id) {
    if ($gateway_id === 'orange_money_gateway') {
        $data['custom_field'] = 'valeur_personnalisée';
    }
    return $data;
}, 10, 3);
```

## 🐛 Débogage

### Activation des logs
1. Dans la configuration de chaque passerelle
2. Activez **"Activer les logs de débogage"**
3. Consultez les logs dans **WooCommerce > Statut > Journaux**

### Logs typiques
```
[INFO] Début du traitement paiement Wave pour commande #123
[DEBUG] Données envoyées à Wave: {"amount":5000,"currency":"XOF"...}
[DEBUG] Réponse de Wave: {"status":"success","payment_url":"..."}
[INFO] Paiement Wave confirmé pour commande #123
[ERROR] Erreur paiement Wave: Invalid phone number format
```

### URLs de test des webhooks
- **Wave** : `votre-site.com/?wc-api=wc_wave_gateway`
- **Orange Money** : `votre-site.com/?wc-api=wc_orange_money_gateway`

## 📱 Formats de numéros supportés

### Wave (Sénégal)
- `+221701234567`
- `221701234567`
- `701234567`
- `77123456` (format court)

### Orange Money
#### Sénégal
- `+221701234567`
- `221701234567`
- `701234567`

#### Côte d'Ivoire
- `+22507123456`
- `22507123456`
- `07123456`

#### Mali
- `+22370123456`
- `22370123456`
- `70123456`

## 🔒 Sécurité

### Validation des webhooks
- Les webhooks sont validés via signature HMAC
- Vérification de l'origine des requêtes
- Validation des montants et références

### Bonnes pratiques
- Utilisez toujours HTTPS en production
- Stockez les clés secrètes de manière sécurisée
- Testez en mode sandbox avant la production
- Surveillez les logs pour détecter les anomalies

## 🆘 Support et FAQ

### Questions fréquentes

**Q: Le plugin fonctionne-t-il avec tous les thèmes ?**
R: Oui, le plugin est compatible avec tous les thèmes respectant les standards WooCommerce.

**Q: Puis-je utiliser les deux passerelles simultanément ?**
R: Oui, vous pouvez activer Wave et Orange Money en même temps.

**Q: Les remboursements sont-ils automatiques ?**
R: Cela dépend de l'API du fournisseur. Wave peut supporter les remboursements automatiques, Orange Money nécessite souvent un traitement manuel.

**Q: Le plugin est-il compatible avec les blocs WooCommerce ?**
R: Oui, le plugin inclut une compatibilité complète avec les blocs de checkout.

### Problèmes courants

#### Paiement en attente indéfiniment
1. Vérifiez les URLs de webhook
2. Consultez les logs de débogage
3. Testez la connectivité réseau

#### Erreur "Devise non supportée"
1. Changez la devise vers XOF (FCFA)
2. Vérifiez la configuration de WooCommerce
3. Contactez le support du fournisseur

## 📄 Licence

Ce plugin est distribué sous licence GPL v2 ou ultérieure.

## 🤝 Contribution

Les contributions sont les bienvenues ! Pour contribuer :

1. Forkez le projet
2. Créez une branche pour votre fonctionnalité
3. Commitez vos changements
4. Poussez vers la branche
5. Ouvrez une Pull Request

## 📞 Contact

Pour toute question ou support :
- Email : support@votre-domaine.com
- Documentation : https://votre-site.com/docs
- GitHub : https://github.com/votre-compte/mobile-money-gateway

---

**Note importante** : Ce plugin nécessite des comptes développeur auprès de Wave et Orange Money. Les APIs et méthodes d'intégration peuvent varier selon les pays et les versions des services.