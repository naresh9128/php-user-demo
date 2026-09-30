# PHP and MySQL DevOps Lab — complete deployment guide

This is the next exercise after your HTML project. It uses your existing Ubuntu EC2 instance, Jenkins, Git, Docker and Ansible. PHP runs in Apache on port 8081; MySQL is a separate container with a permanent named volume. Your original HTML site on port 80 can continue running.

## What the application does

- Register with name, email, phone and password (12–72 bytes).
- Login, view a dashboard and logout.
- Every user can view and edit their own profile.
- Administrators can list, view, edit and delete ordinary user accounts.
- Public registration always creates an ordinary user. Admin accounts are created by promoting an existing account from the EC2 terminal.
- Passwords are hashed; SQL uses prepared statements; form changes require CSRF tokens. Admin accounts cannot be deleted through the interface.

This is a learning application. Limit the HTTP port to My IP and use sample information. Add HTTPS, secure cookies, login rate limiting and backups before using real user information. COOKIE_SECURE is 0 for this HTTP exercise; set it to 1 when serving through HTTPS.

## 1. Extract the project on Windows

Extract the ZIP into C:\Users\Hp\Desktop. The project folder should be C:\Users\Hp\Desktop\php-user-demo and contain Dockerfile, Jenkinsfile, compose.yml, app, database, ansible and tools. Do not put this inside your old devops-html-lab project. Files have their correct names; do not add .txt.

## 2. Create the GitHub repository

Sign in to https://github.com/new. Name: php-user-demo. For this sample, choose Public. Leave README, .gitignore and license initialization unchecked. Click Create repository.

In Windows PowerShell:

```powershell
cd "$HOME\Desktop\php-user-demo"
git init
git add .
git commit -m "Add PHP MySQL user portal"
git branch -M main
git remote add origin https://github.com/naresh9128/php-user-demo.git
git push -u origin main
```

Your GitHub authentication and Git identity from the first exercise can be reused. Confirm the folders and files appear at https://github.com/naresh9128/php-user-demo.

## 3. Prepare the EC2 tools

In your root EC2 SSH terminal (the one with root@ip-...):

```bash
apt update
apt install -y docker-compose-v2 docker-buildx ansible openssl
docker compose version
sudo -u jenkins docker version
ansible --version
```

These package names are for the Ubuntu Docker packages used by your current server. Do not mix them with Docker CE packages from a different repository. If apt cannot find a package, stop and share that error.

## 4. Create the server-only database password file

Run these commands once in the root EC2 terminal. They generate random passwords without printing them. Do not rerun this over an existing password file: initialization passwords do not automatically update an existing MySQL data volume.

```bash
if [ -e /opt/php-user-demo.env ]; then
  echo "Password file already exists. Keep it unchanged."
else
  umask 077
  printf 'DB_PASSWORD=%s\nMYSQL_ROOT_PASSWORD=%s\n' "$(openssl rand -hex 32)" "$(openssl rand -hex 32)" > /opt/php-user-demo.env
  chown root:jenkins /opt/php-user-demo.env
  chmod 640 /opt/php-user-demo.env
fi
```

Jenkins reads this file during deployment. It is outside GitHub. Never commit it. Do not change its password values after the first MySQL initialization without changing the corresponding database users too.

## 5. Add the application port in AWS

AWS EC2 > Instances > select your lab instance > Security > click its security group > Edit inbound rules > Add rule:

- Type: Custom TCP
- Port: 8081
- Source: My IP

Save. Keep SSH 22 and Jenkins 8080 restricted to My IP. MySQL has no host port mapping; do not open port 3306. If your desktop internet IP changes, update My IP rules.

## 6. Create a separate Jenkins pipeline

Open Jenkins at http://65.1.92.252:8080 (use the instance's current public IP if it changed).

1. New Item > name php-user-demo > Pipeline > OK.
2. Definition: Pipeline script from SCM.
3. SCM: Git.
4. Repository URL: https://github.com/naresh9128/php-user-demo.git.
5. Credentials: none for this public sample repository.
6. Branch Specifier: */main.
7. Script Path: Jenkinsfile.
8. Save > Build Now.
9. Open the build number > Console Output.

The pipeline checks out Git, builds PHP with the PDO MySQL extension, checks every PHP file's syntax, deploys with Ansible and Docker Compose, and checks that the application can query its database. The first build downloads PHP and MySQL images and initializes the database, so it takes longer. Wait for Finished: SUCCESS.

## 7. Register and log in

Open http://65.1.92.252:8081/ (replace IP if it changed).

Click Register. Enter a sample name, valid email, phone and a password of at least 12 characters. Submit. Login with that email and password. Check Dashboard, My profile, Edit details and Logout. This account initially has the user role.

## 8. Enable administrator access

Register the account you want to use as administrator first. Then run on EC2, replacing admin@example.com with that exact registered email:

```bash
printf '%s\n' 'admin@example.com' | docker compose --project-name php-user-demo --env-file /opt/php-user-demo.env -f /var/lib/jenkins/workspace/php-user-demo/compose.yml exec -T web php /opt/tools/promote.php
```

Expected message: Administrator access enabled. Refresh the dashboard or log in again. Manage users will appear. Register a second sample account, then use the admin account to View, Edit and Delete that second account. Delete requires a confirmation form. Ordinary users must not be able to open Manage users.

## 9. Test automatic CI/CD and persistent data

Change the dashboard greeting text in app/index.php on your desktop. Save. In Windows PowerShell:

```powershell
cd "$HOME\Desktop\php-user-demo"
git add app/index.php
git commit -m "Update dashboard greeting"
git push
```

Jenkins polls Git about every two minutes. Wait for the next successful build and refresh the website. Your previously registered users should still exist. The php_user_demo_mysql_data volume stores the database independently of the PHP container. Deployments do not delete that volume. Session files live in the PHP container, so you may need to log in again after a deployment. This simple lab may briefly interrupt the website while the PHP container is replaced.

## 10. Troubleshooting on EC2

```bash
docker compose --project-name php-user-demo --env-file /opt/php-user-demo.env -f /var/lib/jenkins/workspace/php-user-demo/compose.yml ps
docker compose --project-name php-user-demo --env-file /opt/php-user-demo.env -f /var/lib/jenkins/workspace/php-user-demo/compose.yml logs --tail=80 web db
curl --fail http://localhost:8081/health.php
```

Health response: OK. If localhost works but your desktop cannot connect, check port 8081's security group rule and current instance IP. If Compose says permission denied reading the env file, check root:jenkins ownership and mode 640. If MySQL stays unhealthy, read its logs; keep the password file unchanged. Do not run docker compose down -v or docker volume rm on the database volume unless you intentionally want to erase all accounts.

## 11. Back up the database

Run as root on EC2:

```bash
mkdir -p /root/php-user-demo-backups
docker compose --project-name php-user-demo --env-file /opt/php-user-demo.env -f /var/lib/jenkins/workspace/php-user-demo/compose.yml exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysqldump --no-tablespaces -u "$MYSQL_USER" "$MYSQL_DATABASE"' > /root/php-user-demo-backups/users-backup.sql
```

Copy backups off the instance before terminating it. A named volume survives container replacement, but does not protect against deleting the instance or its disk. Stop unused EC2 instances to reduce compute usage; storage and some other resources may still be charged. Keep your existing billing alert.

## Files explained

- app/bootstrap.php: sessions, database connection, validation, CSRF helpers.
- app/index.php: registration, login, dashboard, profile and admin CRUD routes.
- app/health.php: database availability check.
- app/style.css: layout.
- database/01-schema.sql: creates users on first database initialization.
- tools/promote.php: CLI-only administrator promotion.
- Dockerfile: Apache and PHP image with pdo_mysql.
- compose.yml: PHP/MySQL services, startup health checks and database volume.
- ansible/deploy.yml: starts the services from the Jenkins checkout.
- Jenkinsfile: automated checkout, image build, syntax validation, deployment and health check.

Schema initialization runs only when the database volume is empty. Later table changes need an explicit migration; editing 01-schema.sql will not change an already initialized database.

## Documentation

https://docs.docker.com/compose/how-tos/startup-order/
https://docs.docker.com/compose/gettingstarted/
https://hub.docker.com/_/php
https://hub.docker.com/_/mysql
