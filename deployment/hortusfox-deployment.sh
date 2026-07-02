#!/bin/bash

#
# Automatically deploy a HortusFox workspace on a DigitalOcean droplet
#
# Usage:
# ./hortusfox-deployment.sh admin@example.com password domainname.com UTC "From Name" hosting@example.com smtp.example.com 587 login@example.com PASSWORD
#

COMPOSE_DOWNLOAD_LINK="{{URL_TO_COMPOSE_FILE}}"

DEPLOY_APP_ADMIN_EMAIL="$1"
DEPLOY_APP_ADMIN_PASSWORD="$2"
DEPLOY_DOMAIN_NAME="$3"
DEPLOY_APP_TIMEZONE="$4"
DEPLOY_APP_UPDATEDEPS="true"
DEPLOY_CERTBOT_EMAIL="hello@hortusfox.com"
DEPLOY_APP_CONTAINER_NAME="hortusfox-web-app-1"
DEPLOY_DB_USERNAME="user"
DEPLOY_DB_PASSWORD=$(openssl rand -base64 12)
DEPLOY_MARIADB_ROOT_PASSWORD=$(openssl rand -base64 12)
DEPLOY_MARIADB_USER="$DEPLOY_DB_USERNAME"
DEPLOY_MARIADB_PASSWORD="$DEPLOY_DB_PASSWORD"
DEPLOY_SMTP_AUTH=1
DEPLOY_SMTP_FROMNAME="$5"
DEPLOY_SMTP_FROMADDRESS="$6"
DEPLOY_SMTP_HOST="$7"
DEPLOY_SMTP_PORT=$8
DEPLOY_SMTP_USERNAME="$9"
DEPLOY_SMTP_PASSWORD="${10}"
DEPLOY_SMTP_ENCRYPTION="tls"

sudo apt update
sudo apt install ca-certificates curl -y
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc

# Add the repository to Apt sources:
sudo tee /etc/apt/sources.list.d/docker.sources <<EOF
Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: $(. /etc/os-release && echo "${UBUNTU_CODENAME:-$VERSION_CODENAME}")
Components: stable
Signed-By: /etc/apt/keyrings/docker.asc
EOF

sudo apt update
sudo apt install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin -y

git clone https://github.com/danielbrendel/hortusfox-web.git
cd hortusfox-web

rm -f docker-compose.yml
wget -O docker-compose.yml $COMPOSE_DOWNLOAD_LINK

cat <<-EOF > .env.deployment

    DEPLOY_APP_ADMIN_EMAIL="$DEPLOY_APP_ADMIN_EMAIL"
    DEPLOY_APP_ADMIN_PASSWORD="$DEPLOY_APP_ADMIN_PASSWORD"
    DEPLOY_APP_TIMEZONE="$DEPLOY_APP_TIMEZONE"
	DEPLOY_APP_UPDATEDEPS="$DEPLOY_APP_UPDATEDEPS"
    DEPLOY_DB_USERNAME="$DEPLOY_DB_USERNAME"
    DEPLOY_DB_PASSWORD="$DEPLOY_DB_PASSWORD"
    DEPLOY_MARIADB_ROOT_PASSWORD="$DEPLOY_MARIADB_ROOT_PASSWORD"
    DEPLOY_MARIADB_USER="$DEPLOY_MARIADB_USER"
    DEPLOY_MARIADB_PASSWORD="$DEPLOY_MARIADB_PASSWORD"
	DEPLOY_SMTP_AUTH=$DEPLOY_SMTP_AUTH
	DEPLOY_SMTP_FROMNAME="$DEPLOY_SMTP_FROMNAME"
	DEPLOY_SMTP_FROMADDRESS="$DEPLOY_SMTP_FROMADDRESS"
	DEPLOY_SMTP_HOST="$DEPLOY_SMTP_HOST"
	DEPLOY_SMTP_PORT=$DEPLOY_SMTP_PORT
	DEPLOY_SMTP_USERNAME="$DEPLOY_SMTP_USERNAME"
	DEPLOY_SMTP_PASSWORD="$DEPLOY_SMTP_PASSWORD"
	DEPLOY_SMTP_ENCRYPTION="$DEPLOY_SMTP_ENCRYPTION"
EOF

rm -f 99-php.ini

cat <<-EOF > 99-php.ini

    upload_max_filesize = 5000M
    post_max_size = 5500M
    memory_limit = 1024M
    max_execution_time = 0
    max_input_time = -1
EOF

cat <<-EOF > hortusfox-ssl.conf

<VirtualHost *:80>
	ServerName $DEPLOY_DOMAIN_NAME
	Redirect permanent / https://$DEPLOY_DOMAIN_NAME
</VirtualHost>

<VirtualHost *:443>
	ServerName $DEPLOY_DOMAIN_NAME
	DocumentRoot /var/www/html/public

	SSLEngine on
	SSLCertificateFile /etc/letsencrypt/live/$DEPLOY_DOMAIN_NAME/fullchain.pem
	SSLCertificateKeyFile /etc/letsencrypt/live/$DEPLOY_DOMAIN_NAME/privkey.pem

	<Directory /var/www/html/public>
		AllowOverride All
		Require all granted
	</Directory>

	ErrorLog /var/log/apache2/error.log
	CustomLog /var/log/apache2/access.log combined
</VirtualHost>

EOF

cat <<-EOF > renew-ssl.sh

#!/bin/bash

$(which docker) stop $DEPLOY_APP_CONTAINER_NAME
$(which docker) run --rm -p 80:80 -v "/etc/letsencrypt:/etc/letsencrypt" -v "/var/lib/letsencrypt:/var/lib/letsencrypt" certbot/certbot renew --non-interactive
$(which docker) start $DEPLOY_APP_CONTAINER_NAME
$(which docker) exec $DEPLOY_APP_CONTAINER_NAME service apache2 reload

EOF

chmod +x renew-ssl.sh

docker compose pull
docker run -it --rm -p 80:80 -v "/etc/letsencrypt:/etc/letsencrypt" -v "/var/lib/letsencrypt:/var/lib/letsencrypt" certbot/certbot certonly --standalone -d $DEPLOY_DOMAIN_NAME --email $DEPLOY_CERTBOT_EMAIL --agree-tos --no-eff-email
docker compose --env-file ./.env.deployment up -d

docker cp 99-php.ini $DEPLOY_APP_CONTAINER_NAME:/var/www/html/99-php.ini
docker cp 99-php.ini $DEPLOY_APP_CONTAINER_NAME:/usr/local/etc/php/conf.d/99-php.ini

docker exec -it $DEPLOY_APP_CONTAINER_NAME a2enmod ssl
docker exec -it $DEPLOY_APP_CONTAINER_NAME service apache2 restart

(crontab -l 2>/dev/null; echo "0 0 1 * * cd $(pwd) && $(which docker) compose pull && $(which docker) compose --env-file ./.env.deployment up -d") | crontab -
(crontab -l 2>/dev/null; echo "0 0 1 * * cd $(pwd) && ./renew-ssl.sh >> ssl-renewal.log") | crontab -
(crontab -l 2>/dev/null; echo "0 0 * * * curl -L https://$DEPLOY_DOMAIN_NAME/cronjob/tasks/overdue?cronpw=BCRoXcXi9Jm3qBIB") | crontab -
(crontab -l 2>/dev/null; echo "0 0 * * * curl -L https://$DEPLOY_DOMAIN_NAME/cronjob/tasks/recurring?cronpw=BCRoXcXi9Jm3qBIB") | crontab -
(crontab -l 2>/dev/null; echo "0 0 * * * curl -L https://$DEPLOY_DOMAIN_NAME/cronjob/tasks/tomorrow?cronpw=BCRoXcXi9Jm3qBIB") | crontab -
