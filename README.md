# BileMo
Projet 7 de la formation **PHP/Symfony** d'OpenClassrooms : Créez un web service exposant une API

Ce projet a été développé avec PHP **8.1.4** et Symfony **6.1.5**
## Installer le projet localement
Pour installer le projet sur votre machine, suivez ces étapes :
- Installez un environnement PHP & MySQL *(par exemple via [XAMPP](https://www.apachefriends.org/))*
- Installez [Composer](https://getcomposer.org/download/)
### 1) Clonez le projet et installez les dépendances :
> git clone https://github.com/GGOx94/BileMo.git

> composer install
### 3) Changez les variables d'environnement dans le fichier **.env**
Modifiez le chemin d'accès à la base de données :
>DATABASE_URL="mysql://**db_user**:**db_password**@127.0.0.1:3306/**db_name**?serverVersion=5.7&charset=utf8mb4"

### 4) Base de données et jeu de démonstration
Créez la base de données, initialisez le schéma et chargez les données de démonstration :
>php bin/console doctrine:database:create

>php bin/console doctrine:schema:up --force

>php bin/console doctrine:fixtures:load

### 5) Clés privées & publiques
Générez les clés de l'API, avec pour passphrase la valeur de la variable d'environnement "JWT_PASSPHRASE" présente dans le fichier .env ("ChangeMe" par défaut) :
>openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096

>openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout

## Tout est prêt !
Vous pouvez lancer le serveur :
>symfony server:start

Les comptes utilisateur et administrateur de test sont :
>client0@demo.com / Secret123

>admin@bilemo.com / Secret123

Vous pouvez désormais, à l'aide de Postman par exemple, demander un token à l'API en tant qu'utilisateur :
>GET http://localhost:8000/api/login_check

Avec, dans le corps de la requête :
>{
"username": "client0@demo.com",
"password": "Secret123"
}

D'autres routes et fonctionnalités sont disponibles, rendez-vous sur la documentation de l'API pour les consulter.
Lien de la documentation API par défaut :
> http://localhost:8000/api/doc